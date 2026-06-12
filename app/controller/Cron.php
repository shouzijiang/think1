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

    /**
     * 每日凌晨 4:10：预生成当天每日挑战题目
     */
    public function genDailyChallenge()
    {
        try {
            $result = $this->cronService->genDailyChallenge();
            return ResponseHelper::success($result, $result['generated'] ? '已生成' : '已存在');
        } catch (\Throwable $e) {
            \think\facade\Log::error('cron/gen-daily-challenge 异常: ' . $e->getMessage());
            return ResponseHelper::error('生成失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 每日凌晨：同步昨日全站视频单价（替代 php think pun:sync-channel-unit-price --yesterday）
     */
    public function syncChannelUnitPrice()
    {
        try {
            $result = $this->cronService->syncChannelUnitPriceYesterday();
            return ResponseHelper::success($result, '同步完成');
        } catch (\Throwable $e) {
            \think\facade\Log::error('cron/sync-channel-unit-price 异常: ' . $e->getMessage());
            return ResponseHelper::error('同步失败: ' . $e->getMessage(), 500);
        }
    }
}

