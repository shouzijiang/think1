<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\CronService;
use think\facade\Log;
use think\Request;

/**
 * 定时任务控制器
 */
class Cron extends BaseController
{
    protected $cronService;
    
    protected function initialize()
    {
        parent::initialize();
        $this->cronService = new CronService();
    }

    /**
     * 发送提醒消息（定时任务）
     * GET 可选参数 user_id：仅对该用户尝试发送（仍须满足订阅与未领取等条件）
     */
    public function sendRemind(Request $request)
    {
        $raw = $request->get('user_id');
        $targetUserId = null;
        if ($raw !== null && $raw !== '') {
            $targetUserId = (int) $raw;
            if ($targetUserId <= 0) {
                return ResponseHelper::badRequest('user_id 无效');
            }
        }

        $result = $this->cronService->sendRemind($targetUserId);
        return ResponseHelper::success($result, '定时任务执行完成');
    }
}

