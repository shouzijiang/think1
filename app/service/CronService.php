<?php

namespace app\service;

use app\common\WechatHelper;
use app\model\MessageLog;
use app\model\User;
use app\model\UserSubscribe;
use think\facade\Log;

/**
 * 定时任务服务类
 */
class CronService
{
    private const DAILY_REWARD_TYPE = 'daily_noon_hint_5';

    /**
     * 发送「每日领奖」提醒：仅给已订阅且今日未登录、未领取的用户
     *
     * @param int|null $targetUserId 传正整数时只对该用户尝试发送（仍须满足订阅 accept、当日未成功领取等条件）
     * @return array{success_count:int,fail_count:int,total_checked:int,target_user_id?:int,matched_user_ids?:int[]}
     */
    public function sendRemind(?int $targetUserId = null): array
    {
        $successCount = 0;
        $failCount = 0;

        $tz = new \DateTimeZone('Asia/Shanghai');
        $today = (new \DateTime('now', $tz))->format('Y-m-d');
        $timeStr = (new \DateTime('now', $tz))->format('Y年m月d日 H:i');
        $templateId = PunService::DAILY_NOON_TEMPLATE_ID;

        $query = User::alias('u')
            ->join('user_subscribes s', 'u.id = s.user_id')
            ->leftJoin('pun_reward_claim_record r', "u.id = r.user_id AND r.claim_type = '" . self::DAILY_REWARD_TYPE . "' AND r.claim_date = '" . $today . "' AND r.status = 'success'")
            ->where('s.template_id', $templateId)
            ->where('s.subscribe_status', 'accept')
            ->whereNull('r.id')
            ->where(function ($q) use ($today) {
                $q->whereNull('u.last_login_at')
                    ->whereOr("DATE(u.last_login_at) < '{$today}'");
            });

        if ($targetUserId !== null && $targetUserId > 0) {
            $query->where('u.id', $targetUserId);
        }

        $users = $query->field('u.id, u.openid')
            ->group('u.id, u.openid')
            ->select();

        $matchedIds = [];
        foreach ($users as $row) {
            $matchedIds[] = (int) $row->id;
        }
        if ($targetUserId !== null && $targetUserId > 0) {
            Log::info('send-remind 单用户模式 target_user_id=' . $targetUserId . ' matched_ids=' . json_encode($matchedIds, JSON_UNESCAPED_UNICODE));
        }

        foreach ($users as $user) {
            // 订阅消息 thing* 单字段最多约 20 字，超长会报 data.thingN.value invalid
            $messageData = [
                'thing1'  => ['value' => '每日上线可领查看答案次数'],
                'thing4'  => ['value' => '请尽快领取，每日限一次'],
                'thing5'  => ['value' => '如果您已经领取奖励请忽略此消息'],
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
                $errText = (string) ($result['error'] ?? '未知错误');
                $errCode = isset($result['errcode']) ? (int) $result['errcode'] : 0;
                MessageLog::create([
                    'user_id' => $user->id,
                    'template_id' => $templateId,
                    'send_status' => 'failed',
                    'error_msg' => $errText,
                    'send_time' => date('Y-m-d H:i:s'),
                ]);
                $failCount++;
                Log::error('发送订阅消息失败 user_id=' . $user->id . ' error=' . $errText);
                $this->markSubscribeRejectedIfUserRefused((int) $user->id, $templateId, $errText, $errCode);
            }
        }

        $logSuffix = $targetUserId !== null && $targetUserId > 0 ? ' target_user_id=' . $targetUserId : '';
        Log::info('每日领奖提醒执行完成 success_count=' . $successCount . ' fail_count=' . $failCount . ' total_checked=' . count($users) . $logSuffix);

        $out = [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'total_checked' => count($users),
        ];
        if ($targetUserId !== null && $targetUserId > 0) {
            $out['target_user_id'] = $targetUserId;
            $out['matched_user_ids'] = $matchedIds;
        }

        return $out;
    }

    /**
     * 微信返回用户拒绝接收时，将库内该模板订阅改为 reject，避免定时任务反复命中同一用户。
     * 常见：errmsg 含 user refuse to accept the msg；errcode 43101（以微信文档为准）。
     */
    private function markSubscribeRejectedIfUserRefused(int $userId, string $templateId, string $errorMsg, int $errCode): void
    {
        if ($templateId === '') {
            return;
        }
        $lower = strtolower($errorMsg);
        $isRefuse = ($errCode === 43101)
            || str_contains($lower, 'user refuse')
            || str_contains($lower, 'refuse to accept');
        if (!$isRefuse) {
            return;
        }
        $n = UserSubscribe::where('user_id', $userId)
            ->where('template_id', $templateId)
            ->where('subscribe_status', 'accept')
            ->update(['subscribe_status' => 'reject']);
        if ($n > 0) {
            Log::info('订阅消息用户拒绝，已同步 user_subscribes 为 reject user_id=' . $userId . ' template_id=' . $templateId);
        }
    }
}

