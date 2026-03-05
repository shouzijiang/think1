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
        echo 1;
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        $level = (int) $request->post('level', 0);
        $userAnswer = $request->post('userAnswer', []);
        if (!is_array($userAnswer)) {
            $userAnswer = [];
        }
        if ($level < 1 || $level > 253) {
            return ResponseHelper::badRequest('关卡号需在 1~253 之间');
        }
        $result = $this->punService->submitAnswer($userId, $level, $userAnswer);
        return ResponseHelper::success($result);
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
}
