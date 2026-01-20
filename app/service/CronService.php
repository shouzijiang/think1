<?php

namespace app\service;

use app\common\WechatHelper;
use app\model\MessageLog;
use app\model\User;
use app\model\UserSetting;
use app\model\UserSubscribe;
use think\facade\Log;

/**
 * 定时任务服务类
 */
class CronService
{
    /**
     * 发送提醒消息
     * @return array
     */
    public function sendRemind(): array
    {
        $successCount = 0;
        $failCount = 0;
        
        // 查询所有启用提醒的用户
        $users = User::alias('u')
            ->join('user_settings us', 'u.id = us.user_id')
            ->where('us.enabled', 1)
            ->field('u.id, u.openid, us.work_start_time, us.work_end_time, us.remind_interval, us.last_remind_time')
            ->select();
        
        $now = time() * 1000; // 当前时间戳（毫秒）
        
        foreach ($users as $user) {
            // 检查是否在工作时间内
            if (!$this->isWorkTime($user->work_start_time, $user->work_end_time)) {
                continue;
            }
            
            // 检查是否到了提醒时间
            $lastRemindTime = $user->last_remind_time ?? 0;
            $intervalMs = $user->remind_interval * 60 * 60 * 1000;
            
            if ($now - $lastRemindTime < $intervalMs) {
                continue; // 还没到提醒时间
            }
            
            // 查询用户是否授权了订阅消息
            $subscribe = UserSubscribe::where('user_id', $user->id)
                ->where('subscribe_status', 'accept')
                ->order('updated_at', 'desc')
                ->find();
            
            if (!$subscribe) {
                continue; // 用户未授权订阅消息
            }
            
            $templateId = $subscribe->template_id;
            
            // 发送订阅消息
            $messageData = [
                'thing1' => ['value' => '久坐提醒'],
                'time2' => ['value' => date('Y年m月d日 H:i')],
                'thing3' => ['value' => '您已经坐了很久了，站起来活动一下吧！']
            ];
            
            $result = WechatHelper::sendSubscribeMessage(
                $user->openid,
                $templateId,
                $messageData
            );
            
            // 更新最后提醒时间
            if ($result['success']) {
                UserSetting::where('user_id', $user->id)
                    ->update(['last_remind_time' => $now]);
                
                // 记录发送日志
                MessageLog::create([
                    'user_id' => $user->id,
                    'template_id' => $templateId,
                    'send_status' => 'success',
                    'send_time' => date('Y-m-d H:i:s'),
                ]);
                
                $successCount++;
            } else {
                // 记录失败日志
                MessageLog::create([
                    'user_id' => $user->id,
                    'template_id' => $templateId,
                    'send_status' => 'failed',
                    'error_msg' => $result['error'] ?? '未知错误',
                    'send_time' => date('Y-m-d H:i:s'),
                ]);
                
                $failCount++;
                Log::error('发送订阅消息失败', [
                    'user_id' => $user->id,
                    'error' => $result['error'] ?? '未知错误'
                ]);
            }
        }
        
        return [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'total_checked' => count($users),
        ];
    }
    
    /**
     * 判断是否在工作时间内
     * @param string $workStartTime
     * @param string $workEndTime
     * @return bool
     */
    private function isWorkTime(string $workStartTime, string $workEndTime): bool
    {
        $now = date('H:i');
        return $now >= $workStartTime && $now <= $workEndTime;
    }
}

