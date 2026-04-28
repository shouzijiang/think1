<?php

namespace app\service;

use app\common\WechatHelper;
use app\model\MessageLog;
use app\model\User;
use think\facade\Log;

/**
 * 定时任务服务类
 */
class CronService
{
    private const DAILY_REWARD_TYPE = 'daily_noon_hint_5';

    /**
     * 发送「每日领奖」提醒：仅给已订阅且今日未登录、未领取的用户
     * @return array
     */
    public function sendRemind(): array
    {
        $successCount = 0;
        $failCount = 0;

        $tz = new \DateTimeZone('Asia/Shanghai');
        $today = (new \DateTime('now', $tz))->format('Y-m-d');
        $timeStr = (new \DateTime('now', $tz))->format('Y年m月d日 H:i');
        $templateId = PunService::DAILY_NOON_TEMPLATE_ID;

        $users = User::alias('u')
            ->join('user_subscribes s', 'u.id = s.user_id')
            ->leftJoin('pun_reward_claim_record r', "u.id = r.user_id AND r.claim_type = '" . self::DAILY_REWARD_TYPE . "' AND r.claim_date = '" . $today . "' AND r.status = 'success'")
            ->where('s.template_id', $templateId)
            ->where('s.subscribe_status', 'accept')
            ->whereNull('r.id')
            ->where(function ($q) use ($today) {
                $q->whereNull('u.last_login_at')
                    ->whereOr("DATE(u.last_login_at) < '{$today}'");
            })
            ->field('u.id, u.openid')
            ->group('u.id, u.openid')
            ->select();

        foreach ($users as $user) {
            $messageData = [
                'thing1'  => ['value' => '奖励领取'],
                'thing4'  => ['value' => '请尽快领取，每日限一次'],
                'thing5'  => ['value' => '每日12点可领取5次查看答案次数，分享也可领取次数'],
                'time8'   => ['value' => $timeStr],
            ];

            $result = WechatHelper::sendSubscribeMessage((string) $user->openid, $templateId, $messageData, 'pages/index/index');

            if ($result['success']) {
                MessageLog::create([
                    'user_id' => $user->id,
                    'template_id' => $templateId,
                    'send_status' => 'success',
                    'send_time' => date('Y-m-d H:i:s'),
                ]);
                $successCount++;
            } else {
                MessageLog::create([
                    'user_id' => $user->id,
                    'template_id' => $templateId,
                    'send_status' => 'failed',
                    'error_msg' => $result['error'] ?? '未知错误',
                    'send_time' => date('Y-m-d H:i:s'),
                ]);
                $failCount++;
                Log::error('发送订阅消息失败 user_id=' . $user->id . ' error=' . ($result['error'] ?? '未知错误'));
            }
        }

        Log::info('每日领奖提醒执行完成 success_count=' . $successCount . ' fail_count=' . $failCount . ' total_checked=' . count($users));
        return [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'total_checked' => count($users),
        ];
    }
}

