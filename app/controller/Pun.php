<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\PunService;
use think\Request;
use think\facade\Log;

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
        $mode = (string) $request->post('gameTier', 'beginner');
        $modeNorm = \app\service\PunService::normalizeMode($mode);

        // 对战模式：题库由房间决定，前端传 questionBank 参数
        $questionBank = (string) $request->post('questionBank', '');
        $levelConfigMap = [
            'intermediate' => 'pun_levels_issue2',
            'xhs'          => 'pun_levels_issue3',
            'beginner'     => 'pun_levels',
        ];
        if ($modeNorm === 'battle') {
            $battleConfig = in_array($questionBank, ['mid', 'intermediate'], true) ? 'pun_levels_issue2' : 'pun_levels_issue3';
            $levels = \think\facade\Config::get($battleConfig, []);
        } else {
            $levels = \think\facade\Config::get($levelConfigMap[$modeNorm] ?? 'pun_levels', []);
        }
        if (!isset($levels[$level])) {
            return ResponseHelper::badRequest('关卡不存在');
        }
        try {
            $result = $this->punService->submitAnswer($userId, $level, $userAnswer, (string) $mode, $questionBank);
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
     * 跳关（扣除查看答案次数，标记24小时）
     * POST /pun/level/skip
     * Body: { "level": 1, "gameTier": "mid"|"xhs"|"beginner" }
     */
    public function skipLevel(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $level = (int) $request->post('level', 0);
        $mode = (string) $request->post('gameTier', 'beginner');
        if ($level <= 0) {
            return ResponseHelper::badRequest('关卡参数错误');
        }

        try {
            $result = $this->punService->skipLevel((int) $userId, $level, $mode);
            return ResponseHelper::success($result);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('pun/level/skip 异常: ' . $e->getMessage());
            return ResponseHelper::error('跳关失败', 500);
        }
    }

    /**
     * 统一领取接口：分享/激励视频/每日任务领取
     * POST /pun/reward/claim
     * Body: { type: share|reward_video|daily_noon_hint_5, add?: number, subscribeStatus?: accept|reject, templateId?: string, launchScene?: string|number }
     * permanent_my_mini_program_hint_3：需传 launchScene，且为微信「我的小程序」/抖音「我的收藏」对应场景值。
     * daily_noon_hint_5：每自然日限一次 +5 次揭字，不限具体时段，需 subscribe accept。
     */
    public function claimReward(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }

        $type = (string) $request->post('type', '');
        $add = (int) $request->post('add', 1);
        if ($add <= 0) {
            $add = 1;
        }
        $subscribeStatus = (string) $request->post('subscribeStatus', '');
        $templateId = (string) $request->post('templateId', '');
        $launchScene = $request->post('launchScene', null);

        try {
            $result = $this->punService->claimReward(
                (int) $userId,
                $type,
                $add,
                [
                    'subscribeStatus' => $subscribeStatus,
                    'templateId' => $templateId,
                    'launchScene' => $launchScene,
                ]
            );
            return ResponseHelper::success($result);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \think\facade\Log::error('pun/reward/claim 异常: ' . $e->getMessage());
            return ResponseHelper::error('奖励发放失败', 500);
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
