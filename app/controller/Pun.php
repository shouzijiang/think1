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
        
        if ($isIntermediate) {
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
}
