<?php
declare (strict_types = 1);

namespace app\controller;

use think\Request;
use app\common\ResponseHelper;
use app\service\BattleService;

class PunBattle
{
    protected $battleService;

    public function __construct(BattleService $battleService)
    {
        $this->battleService = $battleService;
    }

    /**
     * 创建对战房间
     */
    public function createRoom(Request $request)
    {
        $userId = (int) $request->user_id; // 来源于 Auth 中间件
        $questionBank = $request->post('questionBank', 'xhs');
        
        try {
            $roomInfo = $this->battleService->createRoom($userId, (string) $questionBank);
            return ResponseHelper::success($roomInfo, '创建房间成功');
        } catch (\Exception $e) {
            return ResponseHelper::error('创建房间失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新房间题库
     */
    public function updateBank(Request $request)
    {
        $userId = (int) $request->user_id;
        $roomId = (string) $request->post('roomId', '');
        $questionBank = (string) $request->post('questionBank', 'xhs');
        try {
            $this->battleService->updateBank($userId, $roomId, $questionBank);
            return ResponseHelper::success([], '题库已更新');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * 获取对战历史记录
     */
    public function history(Request $request)
    {
        $userId = (int) $request->user_id;
        $page = max(1, (int) $request->param('page', 1));
        $pageSize = max(1, min(100, (int) $request->param('page_size', 20)));
        
        $data = $this->battleService->getHistory($userId, $page, $pageSize);
        return ResponseHelper::success($data, 'success');
    }

    /**
     * 1V1 全局对战排行榜 GET /pun/battle/rank（无需登录）
     */
    public function battleRank(Request $request)
    {
        $page = max(1, (int) $request->param('page', 1));
        $pageSize = max(1, min(100, (int) $request->param('page_size', 20)));

        try {
            $data = $this->battleService->getBattleRankList($page, $pageSize);
            return ResponseHelper::success($data, 'success');
        } catch (\Throwable $e) {
            return ResponseHelper::error('获取对战排行失败: ' . $e->getMessage());
        }
    }
}
