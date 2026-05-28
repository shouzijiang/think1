<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;

/**
 * 邀请人收益结算：仅 reward_video 计收益；全平台按日 video_unit_price + 打款截止日。
 * 金额累加用 BCMath；展示截断小数位，不四舍五入。
 */
class StreamerSettlementService
{
    /**
     * @return array{
     *   lastSettledDate:?string,
     *   totalPaid:string,
     *   totalGross:string,
     *   settledGross:string,
     *   unsettledGross:string,
     *   balance:string,
     *   withdrawMin:string,
     *   canWithdraw:bool
     * }
     */
    public function getEarningsSummary(int $streamerUserId, string $channel): array
    {
        $lastEnd = $this->getLastSettledDate($streamerUserId);
        $totalPaid = $this->getTotalPaid($streamerUserId);
        $totalGross = $this->sumGrossForRange($channel, null, null);
        $settledGross = $lastEnd !== null
            ? $this->sumGrossForRange($channel, null, $lastEnd)
            : '0.0000';
        $unsettledGross = $lastEnd !== null
            ? $this->sumGrossForRange($channel, $lastEnd, null)
            : $totalGross;
        $balance = bcsub($totalGross, $this->moneyStr($totalPaid), $this->calcScale());
        $withdrawMin = trim((string) Config::get('pun_streamer.withdraw_min_amount', '1'));

        return [
            'lastSettledDate' => $lastEnd !== null ? (string) $lastEnd : null,
            'totalPaid'       => $this->formatDisplayAmount($this->moneyStr($totalPaid)),
            'totalGross'      => $this->formatDisplayAmount($totalGross),
            'settledGross'    => $this->formatDisplayAmount($settledGross),
            'unsettledGross'  => $this->formatDisplayAmount($unsettledGross),
            'balance'         => $this->formatDisplayAmount($balance),
            'withdrawMin'     => $withdrawMin,
            'canWithdraw'     => bccomp($balance, $withdrawMin, $this->calcScale()) >= 0,
        ];
    }

    public function getLastSettledDate(int $streamerUserId): ?string
    {
        $row = Db::name('pun_game_streamer_payout')
            ->where('streamer_user_id', $streamerUserId)
            ->field("DATE_FORMAT(period_end, '%Y-%m-%d') AS settled_date")
            ->order('period_end', 'desc')
            ->find();

        return $this->normalizeDateYmd($row['settled_date'] ?? null);
    }

    private function normalizeDateYmd(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        $s = trim((string) $val);
        if ($s === '' || $s === '0000-00-00') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s)) {
            return $s;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return null;
    }

    public function getTotalPaid(int $streamerUserId): float
    {
        return (float) Db::name('pun_game_streamer_payout')
            ->where('streamer_user_id', $streamerUserId)
            ->sum('paid_amount');
    }

    /**
     * 汇总某 channel 在日期区间内的应得（仅 reward_video；BCMath 累加，不四舍五入）。
     */
    public function sumGrossForRange(string $channel, ?string $afterDate, ?string $throughDate): string
    {
        $dailyRows = $this->fetchDailyVideoCounts($channel, $afterDate, $throughDate);
        if ($dailyRows === []) {
            return $this->zeroAmount();
        }

        $dates = array_column($dailyRows, 'stat_date');
        $priceMap = $this->fetchVideoUnitPriceMap($dates);
        $defaultVideo = $this->unitPriceStr(
            (float) Config::get('pun_streamer.default_video_unit_price', 0.01)
        );
        $scale = $this->calcScale();
        $gross = $this->zeroAmount();

        foreach ($dailyRows as $row) {
            $date = (string) $row['stat_date'];
            $videoCnt = (int) $row['video_count'];
            $unit = isset($priceMap[$date])
                ? $this->unitPriceStr($priceMap[$date])
                : $defaultVideo;
            $dayGross = bcmul((string) $videoCnt, $unit, $scale);
            $gross = bcadd($gross, $dayGross, $scale);
        }

        return $gross;
    }

    /**
     * 展示用金额：截断到 display_amount_scale 位，不四舍五入。
     */
    public function formatDisplayAmount(string $amount): string
    {
        $scale = $this->displayScale();
        $amount = trim($amount);
        if ($amount === '' || !is_numeric($amount)) {
            return '0.' . str_repeat('0', $scale);
        }

        $negative = str_starts_with($amount, '-');
        if ($negative) {
            $amount = ltrim($amount, '-');
        }

        if (!str_contains($amount, '.')) {
            $out = $amount . '.' . str_repeat('0', $scale);
        } else {
            [$int, $dec] = explode('.', $amount, 2);
            $dec = substr($dec . str_repeat('0', $scale), 0, $scale);
            $out = $int . '.' . $dec;
        }

        return ($negative ? '-' : '') . $out;
    }

    /**
     * @return list<array{stat_date:string,video_count:int}>
     */
    private function fetchDailyVideoCounts(string $channel, ?string $afterDate, ?string $throughDate): array
    {
        $query = Db::name('pun_game_channel_events')
            ->where('channel', $channel)
            ->where('event_type', 'reward_video');

        if ($afterDate !== null && $afterDate !== '') {
            $query->whereRaw('DATE(created_at) > ?', [$afterDate]);
        }
        if ($throughDate !== null && $throughDate !== '') {
            $query->whereRaw('DATE(created_at) <= ?', [$throughDate]);
        }

        $rows = $query->field([
            'DATE(created_at) as stat_date',
            'COUNT(*) as video_count',
        ])
            ->group('stat_date')
            ->order('stat_date', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'stat_date'   => substr((string) ($row['stat_date'] ?? ''), 0, 10),
                'video_count' => (int) ($row['video_count'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @param list<string> $dates
     * @return array<string, float>
     */
    private function fetchVideoUnitPriceMap(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $rows = Db::name('pun_game_channel_unit_price')
            ->whereIn('stat_date', $dates)
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $date = substr((string) ($row['stat_date'] ?? ''), 0, 10);
            if ($date === '') {
                continue;
            }
            $map[$date] = (float) ($row['video_unit_price'] ?? 0);
        }

        return $map;
    }

    private function calcScale(): int
    {
        return (int) Config::get('pun_streamer.calc_amount_scale', 4);
    }

    private function displayScale(): int
    {
        return max(0, (int) Config::get('pun_streamer.display_amount_scale', 3));
    }

    private function zeroAmount(): string
    {
        return '0.' . str_repeat('0', $this->calcScale());
    }

    private function moneyStr(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function unitPriceStr(float $unit): string
    {
        return number_format($unit, $this->calcScale(), '.', '');
    }
}
