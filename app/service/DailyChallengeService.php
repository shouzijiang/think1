<?php

namespace app\service;

use think\facade\Db;

/**
 * 每日挑战 — 独立服务
 */
class DailyChallengeService
{
    /** 通关奖励答案次数 */
    private const PASS_REWARD = 5;

    /** 通关时间上限（毫秒） */
    private const TIME_LIMIT_MS = 120000;

    /** 通关题数要求 */
    private const PASS_SCORE = 10;

    // ─── 公共方法 ────────────────────────────────────────────

    /**
     * 获取每日挑战配置（无需登录，供首页倒计时）。
     */
    public function getConfig(int $userId = 0): array
    {
        $row = Db::name('pun_daily_challenge')
            ->where('challenge_date', $this->todayShanghai())
            ->find();

        $openTime  = '18:00';
        $closeTime = '23:00';
        $levels    = [];
        if ($row) {
            $openTime  = substr((string) ($row['open_time'] ?? '18:00:00'), 0, 5);
            $closeTime = substr((string) ($row['close_time'] ?? '23:00:00'), 0, 5);
            $levelIds  = is_string($row['level_ids'] ?? null) ? json_decode($row['level_ids'], true) : ($row['level_ids'] ?? []);
            $levels    = is_array($levelIds) ? $levelIds : [];
        }

        $now   = new \DateTime('now', new \DateTimeZone('Asia/Shanghai'));
        $nowSec = ((int) $now->format('G')) * 3600 + ((int) $now->format('i')) * 60 + (int) $now->format('s');
        [$oh, $om] = array_map('intval', explode(':', $openTime));
        [$ch, $cm] = array_map('intval', explode(':', $closeTime));
        $openSec  = $oh * 3600 + $om * 60;
        $closeSec = $ch * 3600 + $cm * 60;
        $closed   = ($nowSec < $openSec || $nowSec >= $closeSec);

        $alreadyPassed = false;
        if ($userId > 0) {
            $alreadyPassed = (bool) Db::name('pun_reward_claim_record')
                ->where('user_id', $userId)
                ->where('claim_type', 'daily_challenge')
                ->where('claim_date', $this->todayShanghai())
                ->where('status', 'success')
                ->find();
        }

        return [
            'openTime'      => $openTime,
            'closeTime'     => $closeTime,
            'levels'        => $levels,
            'closed'        => $closed,
            'alreadyPassed' => $alreadyPassed,
            'message'       => $closed ? ('每日挑战仅在 ' . $openTime . '-' . $closeTime . ' 开放') : '',
        ];
    }

    /**
     * 开始每日挑战：返回当天题目；若已通关则标记 alreadyPassed。
     *
     * @throws \RuntimeException
     */
    public function start(int $userId): array
    {
        $today = $this->todayShanghai();
        $row   = Db::name('pun_daily_challenge')->where('challenge_date', $today)->find();
        if (!$row) {
            throw new \RuntimeException('今日题目尚未生成，请稍后再试');
        }

        $range  = $this->timeRange($row);
        $now    = new \DateTime('now', new \DateTimeZone('Asia/Shanghai'));
        $nowSec = ((int) $now->format('G')) * 3600 + ((int) $now->format('i')) * 60 + (int) $now->format('s');

        if ($nowSec < $range['open'] || $nowSec >= $range['close']) {
            $openLabel  = substr((string) ($row['open_time'] ?? '18:00:00'), 0, 5);
            $closeLabel = substr((string) ($row['close_time'] ?? '23:00:00'), 0, 5);
            throw new \RuntimeException('每日挑战仅在 ' . $openLabel . '-' . $closeLabel . ' 开放');
        }

        $levelIds = is_string($row['level_ids']) ? json_decode($row['level_ids'], true) : $row['level_ids'];
        $levels   = is_array($levelIds) ? $levelIds : [];
        if (empty($levels)) {
            throw new \RuntimeException('今日题目数据异常');
        }

        $alreadyPassed = (bool) Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', 'daily_challenge')
            ->where('claim_date', $today)
            ->where('status', 'success')
            ->find();

        return [
            'levels'        => $levels,
            'alreadyPassed' => $alreadyPassed,
            'openTime'      => substr((string) ($row['open_time'] ?? '18:00:00'), 0, 5),
            'closeTime'     => substr((string) ($row['close_time'] ?? '23:00:00'), 0, 5),
            'createdAt'     => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * 结算并发放奖励。校验 score=10 & time≤5min，防重复领奖。
     */
    public function finish(int $userId, int $score, int $totalTimeMs): array
    {
        $today  = $this->todayShanghai();
        $passed = ($score >= self::PASS_SCORE && $totalTimeMs <= self::TIME_LIMIT_MS);

        if (!$passed) {
            return ['passed' => false, 'score' => $score, 'totalTimeMs' => $totalTimeMs, 'hintReward' => 0];
        }

        $already = Db::name('pun_reward_claim_record')
            ->where('user_id', $userId)
            ->where('claim_type', 'daily_challenge')
            ->where('claim_date', $today)
            ->where('status', 'success')
            ->find();
        if ($already) {
            return ['passed' => true, 'score' => $score, 'totalTimeMs' => $totalTimeMs, 'hintReward' => 0];
        }

        Db::startTrans();
        try {
            $this->increaseHintQuota($userId, self::PASS_REWARD);

            Db::name('pun_reward_claim_record')->insert([
                'user_id'    => $userId,
                'claim_type' => 'daily_challenge',
                'claim_date' => $today,
                'add_quota'  => self::PASS_REWARD,
                'status'     => 'success',
                'reason'     => '',
                'meta_json'  => json_encode([
                    'score'         => $score,
                    'total_time_ms' => $totalTimeMs,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return ['passed' => true, 'score' => $score, 'totalTimeMs' => $totalTimeMs, 'hintReward' => self::PASS_REWARD];
    }

    // ─── 内部方法 ────────────────────────────────────────────

    private function todayShanghai(): string
    {
        return (new \DateTime('now', new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
    }

    /** @return array{open:int, close:int} */
    private function timeRange(array $row): array
    {
        $openHh  = (int) substr((string) ($row['open_time'] ?? '18:00:00'), 0, 2);
        $openMm  = (int) substr((string) ($row['open_time'] ?? '18:00:00'), 3, 2);
        $closeHh = (int) substr((string) ($row['close_time'] ?? '23:00:00'), 0, 2);
        $closeMm = (int) substr((string) ($row['close_time'] ?? '23:00:00'), 3, 2);
        return [
            'open'  => $openHh * 3600 + $openMm * 60,
            'close' => $closeHh * 3600 + $closeMm * 60,
        ];
    }

    private function increaseHintQuota(int $userId, int $delta): void
    {
        $row = Db::name('pun_user_hint_quota')->where('user_id', $userId)->find();
        if (!$row) {
            Db::name('pun_user_hint_quota')->insert([
                'user_id'    => $userId,
                'quota'      => \app\model\PunUserHintQuota::DEFAULT_QUOTA,
                'total_used' => 0,
            ]);
        }
        $row = Db::name('pun_user_hint_quota')->where('user_id', $userId)->lock(true)->find();
        if (!$row) {
            throw new \RuntimeException('揭字配额数据异常');
        }
        $newQuota = (int) $row['quota'] + $delta;
        Db::name('pun_user_hint_quota')->where('user_id', $userId)->update(['quota' => $newQuota]);
    }
}
