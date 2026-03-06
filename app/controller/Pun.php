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
        $result = $this->punService->getRankList($page, $pageSize);
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
        $totalLevels = count(\think\facade\Config::get('pun_levels', []));
        if ($level < 1 || $level > $totalLevels) {
            return ResponseHelper::badRequest('关卡号需在 1~' . $totalLevels . ' 之间');
        }
        try {
            $result = $this->punService->submitAnswer($userId, $level, $userAnswer);
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
        $result = $this->punService->getLevelProgress($userId);
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
