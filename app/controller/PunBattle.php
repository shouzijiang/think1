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
        
        try {
            $roomInfo = $this->battleService->createRoom($userId);
            return ResponseHelper::success($roomInfo, '创建房间成功');
        } catch (\Exception $e) {
            return ResponseHelper::error('创建房间失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取对战历史记录
     */
    public function history(Request $request)
    {
        $userId = (int) $request->user_id;
        $page = (int) $request->param('page', 1);
        $pageSize = (int) $request->param('page_size', 20);
        
        $data = $this->battleService->getHistory($userId, $page, $pageSize);
        return ResponseHelper::success($data, 'success');
    }
}
