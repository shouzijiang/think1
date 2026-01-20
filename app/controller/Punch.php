<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\PunchService;
use think\Request;

/**
 * 打卡控制器
 */
class Punch extends BaseController
{
    protected $punchService;
    
    protected function initialize()
    {
        parent::initialize();
        $this->punchService = new PunchService();
    }
    
    /**
     * 提交打卡记录
     */
    public function submit(Request $request)
    {
        $userId = $request->user_id ?? 0;
        
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        
        $timestamp = $request->post('timestamp');
        if ($timestamp !== null) {
            $timestamp = (int)$timestamp;
        }
        
        $result = $this->punchService->submit($userId, $timestamp);
        
        return ResponseHelper::success($result, '打卡成功');
    }
    
    /**
     * 获取打卡记录列表
     */
    public function records(Request $request)
    {
        $userId = $request->user_id ?? 0;
        
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        
        $page = (int)$request->get('page', 1);
        $pageSize = (int)$request->get('page_size', 20);
        $startDate = $request->get('start_date', '');
        $endDate = $request->get('end_date', '');
        
        if ($pageSize > 100) {
            $pageSize = 100;
        }
        
        $result = $this->punchService->getRecords($userId, $page, $pageSize, $startDate ?: null, $endDate ?: null);
        
        return ResponseHelper::success($result);
    }
    
    /**
     * 获取打卡统计数据
     */
    public function statistics(Request $request)
    {
        $userId = $request->user_id ?? 0;
        
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        
        $date = $request->get('date', '');
        
        $result = $this->punchService->getStatistics($userId, $date ?: null);
        
        return ResponseHelper::success($result);
    }
}

