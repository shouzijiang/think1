<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\SubscribeService;
use think\Request;

/**
 * 订阅消息控制器
 */
class Subscribe extends BaseController
{
    protected $subscribeService;
    
    protected function initialize()
    {
        parent::initialize();
        $this->subscribeService = new SubscribeService();
    }
    
    /**
     * 保存订阅消息授权
     */
    public function save(Request $request)
    {
        $userId = $request->user_id ?? 0;
        
        if (!$userId) {
            return ResponseHelper::unauthorized();
        }
        
        $templateId = $request->post('template_id', '');
        $subscribeStatus = $request->post('subscribe_status', '');
        
        if (empty($templateId)) {
            return ResponseHelper::badRequest('参数错误：template_id不能为空');
        }
        if (empty($subscribeStatus)) {
            return ResponseHelper::badRequest('参数错误：subscribe_status不能为空');
        }
        
        if (!in_array($subscribeStatus, ['accept', 'reject'])) {
            return ResponseHelper::badRequest('参数错误：subscribe_status应为accept或reject');
        }
        
        $result = $this->subscribeService->save($userId, $templateId, $subscribeStatus);
        
        return ResponseHelper::success($result, '保存成功');
    }
}

