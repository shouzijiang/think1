<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;

/**
 * 全平台每日视频单价：手填当日总收入，按全站 reward_video 成功次数自动换算。
 */
class ChannelUnitPriceService
{
    /** 单条视频单价小数位（截断，不四舍五入） */
    public const UNIT_PRICE_SCALE = 4;

    /**
     * 统计某日全站激励视频成功领取次数（单价除数）。
     */
    public function countRewardVideoClaims(string $statDate): int
    {
        return (int) Db::name('pun_reward_claim_record')
            ->where('claim_type', 'reward_video')
            ->where('claim_date', $statDate)
            ->where('status', 'success')
            ->count();
    }

    /**
     * 同步某日单价与次数快照。
     * - video_total_amount > 0：按 总价÷次数 重算单价
     * - 否则且 $useDefaultWhenNoTotal：使用 config 默认单价（默认 0.01），total 保持 0 待手改表
     *
     * @return array{stat_date:string,video_total_amount:string,video_claim_count:int,video_unit_price:string,used_default:bool}
     */
    public function syncUnitPriceForDate(string $statDate, bool $useDefaultWhenNoTotal = false): array
    {
        $date = $this->normalizeDate($statDate);
        $row = Db::name('pun_game_channel_unit_price')
            ->where('stat_date', $date)
            ->find();

        $total = (float) ($row['video_total_amount'] ?? 0);
        $claimCount = $this->countRewardVideoClaims($date);
        $usedDefault = false;

        if ($total > 0) {
            $unitPrice = $this->calcUnitPrice($total, $claimCount);
        } elseif ($useDefaultWhenNoTotal) {
            $unitPrice = $this->getDefaultVideoUnitPrice();
            $usedDefault = true;
        } elseif (!$row) {
            throw new \InvalidArgumentException("未找到 {$date} 的单价记录，请先录入 video_total_amount 或使用默认定时同步");
        } else {
            throw new \InvalidArgumentException("{$date} 的 video_total_amount 须大于 0，或带 --total 录入");
        }

        $payload = [
            'video_unit_price'  => $unitPrice,
            'video_claim_count' => $claimCount,
        ];

        if ($row) {
            Db::name('pun_game_channel_unit_price')
                ->where('stat_date', $date)
                ->update($payload);
        } else {
            Db::name('pun_game_channel_unit_price')->insert(array_merge($payload, [
                'stat_date'          => $date,
                'video_total_amount' => 0,
            ]));
        }

        return [
            'stat_date'           => $date,
            'video_total_amount'  => number_format($total > 0 ? $total : 0, 2, '.', ''),
            'video_claim_count'   => $claimCount,
            'video_unit_price'    => $this->formatUnitPrice($unitPrice),
            'used_default'        => $usedDefault,
        ];
    }

    public function getDefaultVideoUnitPrice(): float
    {
        return (float) Config::get('pun_streamer.default_video_unit_price', 0.01);
    }

    /**
     * 录入当日总收入并同步单价。
     *
     * @return array{stat_date:string,video_total_amount:string,video_claim_count:int,video_unit_price:string}
     */
    public function upsertTotalAndSync(string $statDate, float $videoTotalAmount, ?string $remark = null): array
    {
        $date = $this->normalizeDate($statDate);
        if ($videoTotalAmount <= 0) {
            throw new \InvalidArgumentException('video_total_amount 须大于 0');
        }

        $existing = Db::name('pun_game_channel_unit_price')
            ->where('stat_date', $date)
            ->find();

        $payload = [
            'video_total_amount' => round($videoTotalAmount, 2),
        ];
        if ($remark !== null && $remark !== '') {
            $payload['remark'] = $remark;
        }

        if ($existing) {
            Db::name('pun_game_channel_unit_price')
                ->where('stat_date', $date)
                ->update($payload);
        } else {
            $payload['stat_date'] = $date;
            $payload['video_unit_price'] = 0;
            $payload['video_claim_count'] = 0;
            Db::name('pun_game_channel_unit_price')->insert($payload);
        }

        return $this->syncUnitPriceForDate($date, false);
    }

    /**
     * 重算所有已录入 video_total_amount 的日期。
     *
     * @return list<array{stat_date:string,video_total_amount:string,video_claim_count:int,video_unit_price:string}>
     */
    public function syncAllConfiguredDates(): array
    {
        $rows = Db::name('pun_game_channel_unit_price')
            ->where('video_total_amount', '>', 0)
            ->order('stat_date', 'asc')
            ->column('stat_date');

        $results = [];
        foreach ($rows as $date) {
            $results[] = $this->syncUnitPriceForDate((string) $date);
        }

        return $results;
    }

    private function normalizeDate(string $statDate): string
    {
        $date = trim($statDate);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('stat_date 须为 YYYY-MM-DD');
        }

        return $date;
    }

    /**
     * 总价 ÷ 次数，保留 UNIT_PRICE_SCALE 位小数（截断，不四舍五入）。
     */
    public function calcUnitPrice(float $total, int $claimCount): float
    {
        if ($claimCount <= 0 || $total <= 0) {
            return 0.0;
        }

        $totalStr = number_format($total, 2, '.', '');

        return (float) bcdiv($totalStr, (string) $claimCount, self::UNIT_PRICE_SCALE);
    }

    public function formatUnitPrice(float $unitPrice): string
    {
        return number_format($unitPrice, self::UNIT_PRICE_SCALE, '.', '');
    }
}
