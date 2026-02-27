<?php

namespace app\controller;

use app\BaseController;
use app\common\ResponseHelper;
use app\service\CronService;
use think\facade\Log;

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

    /**《
     * 发送提醒消息（定时任务）
     */
    public function sendRemind()
    {
        $result = $this->cronService->sendRemind();
        Log::info('定时任务 sendRemind 执行完成:' . 1);
        return ResponseHelper::success($result, '定时任务执行完成');
    }
}

