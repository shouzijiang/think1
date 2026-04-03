<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\PunService;
use think\Request;

/**
 * 谐音梗图游戏 - 接口
 */
class Pun extends BaseController
{
    protected $punService;

    protected function initialize()
    {
        parent::initialize();
        $this->punService = new PunService();
    }

    /**
     * 首页更新说明 GET /pun/changelog/latest（无需登录）
     */
    public function changelogLatest()
    {
        $data = $this->punService->getLatestChangelog();
        return ResponseHelper::success($data);
    }

    /**
     * 首页进度统计 GET /pun/stats/home（无需登录）
     * data: { players: int, answers: int }
     */
    public function homeStats()
    {
        $data = $this->punService->getHomeProgressStats();
        return ResponseHelper::success($data);
    }

    /**
     * 排行榜列表 GET /pun/rank/list?page=1&page_size=20
     */
    public function rankList(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('page_size', 20);
        $mode = $request->get('gameTier', 'beginner');
        $result = $this->punService->getRankList($page, $pageSize, (string) $mode);
        return ResponseHelper::success($result);
    }

    /**
     * 提交答案 POST /pun/answer/submit
     * Body: { "level": 36, "userAnswer": ["弟", "分"] }
     */
    public function submitAnswer(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $level = (int) $request->post('level', 0);
        $userAnswer = $request->post('userAnswer', []);
        if (!is_array($userAnswer)) {
            $userAnswer = [];
        }
        $mode = $request->post('gameTier', 'beginner');
        $modeNorm = is_string($mode) ? strtolower(trim($mode)) : '';
        $isIntermediate = in_array($modeNorm, ['issue2', 'intermediate', 'mid', 'middle', '2', '中级', '中級'], true);
        $isBattle = ($modeNorm === 'battle');
        
        if ($isIntermediate || $isBattle) {
            $levels = \think\facade\Config::get('pun_levels_issue2', []);
            if (!isset($levels[$level])) {
                return ResponseHelper::badRequest('关卡不存在');
            }
        } else {
            $levels = \think\facade\Config::get('pun_levels', []);
            if (!isset($levels[$level])) {
                return ResponseHelper::badRequest('关卡不存在');
            }
        }
        try {
            $result = $this->punService->submitAnswer($userId, $level, $userAnswer, (string) $mode);
            return ResponseHelper::success($result);
        } catch (\Throwable $e) {
            \think\facade\Log::error('pun/answer/submit 异常: ' . $e->getMessage() . ' trace: ' . $e->getTraceAsString());
            return ResponseHelper::error('提交失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 分步揭字提示 POST /pun/level/reveal-hint
     * Body: { "level": 1, "gameTier": "mid"|"battle"|"beginner", "roomId"?: "", "questionIndex"?: 0 }
     */
    public function revealHint(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $level = (int) $request->post('level', 0);
        $mode = (string) $request->post('gameTier', 'beginner');
        $roomId = $request->post('roomId', '');
        $qRaw = $request->post('questionIndex');
        $questionIndex = ($qRaw === null || $qRaw === '') ? null : (int) $qRaw;

        try {
            $data = $this->punService->revealHint(
                (int) $userId,
                $level,
                $mode,
                is_string($roomId) && $roomId !== '' ? $roomId : null,
                $questionIndex
            );

            return ResponseHelper::success($data);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \think\facade\Log::error('pun/level/reveal-hint 异常: ' . $e->getMessage());

            return ResponseHelper::error('获取提示失败', 500);
        }
    }

    /**
     * 当前用户关卡进度 GET /pun/level/progress
     */
    public function levelProgress(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $mode = $request->get('gameTier', 'beginner');
        $result = $this->punService->getLevelProgress($userId, (string) $mode);
        return ResponseHelper::success($result);
    }

    /**
     * 提交意见反馈 POST /pun/feedback/submit
     * Body: { "type"?: "bug"|"suggest"|"other", "content": "...", "contact"?: "..." }
     */
    public function feedbackSubmit(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $type = $request->post('type', '');
        $content = $request->post('content', '');
        $contact = $request->post('contact', '');
        if (!is_string($type)) {
            $type = '';
        }
        if (!is_string($content)) {
            $content = '';
        }
        if (!is_string($contact)) {
            $contact = '';
        }
        $result = $this->punService->submitFeedback($userId, $type, $content, $contact);
        if (!empty($result['error'])) {
            return ResponseHelper::badRequest($result['error']);
        }
        return ResponseHelper::success(null, '感谢反馈，我们会尽快处理');
    }

    /**
     * 论坛 - 获取帖子列表
     * GET /pun/forum/list?page=1&page_size=20
     */
    public function forumList(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('page_size', 20);
        $result = $this->punService->getForumList($page, $pageSize);
        return ResponseHelper::success($result);
    }

    /**
     * 论坛 - 获取帖子详情及回复
     * GET /pun/forum/detail?id=1&page=1&page_size=20
     */
    public function forumDetail(Request $request)
    {
        $id = (int) $request->get('id', 0);
        if ($id <= 0) {
            return ResponseHelper::badRequest('参数错误');
        }
        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('page_size', 20);

        $result = $this->punService->getForumTopicDetail($id, $page, $pageSize);
        if (!$result) {
            return ResponseHelper::error('帖子不存在或已被删除', 404);
        }
        return ResponseHelper::success($result);
    }

    /**
     * 论坛 - 发布帖子
     * POST /pun/forum/topic/create
     * Body: { "title": "标题可选", "content": "帖子内容必填" }
     */
    public function forumTopicCreate(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }

        $title = $request->post('title', '');
        $content = $request->post('content', '');

        if (!is_string($title)) $title = '';
        if (!is_string($content)) $content = '';

        $result = $this->punService->createForumTopic($userId, $content, $title);
        if (!empty($result['error'])) {
            return ResponseHelper::badRequest($result['error']);
        }
        return ResponseHelper::success(['id' => $result['id']], '发布成功');
    }

    /**
     * 论坛 - 回复帖子/回复评论
     * POST /pun/forum/reply/create
     * Body: { "topic_id": 1, "content": "回复内容", "reply_to_id": 0 }
     */
    public function forumReplyCreate(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }

        $topicId = (int) $request->post('topic_id', 0);
        $replyToId = (int) $request->post('reply_to_id', 0);
        $content = $request->post('content', '');

        if ($topicId <= 0) {
            return ResponseHelper::badRequest('帖子ID错误');
        }
        if (!is_string($content)) {
            $content = '';
        }

        try {
            $result = $this->punService->createForumReply($userId, $topicId, $content, $replyToId);
            if (!empty($result['error'])) {
                return ResponseHelper::badRequest($result['error']);
            }
            return ResponseHelper::success(null, '回复成功');
        } catch (\Throwable $e) {
            \think\facade\Log::error('pun/forum/reply/create 异常: ' . $e->getMessage());
            return ResponseHelper::error('回复失败，请稍后重试', 500);
        }
    }
}
