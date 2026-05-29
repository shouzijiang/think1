<?php

declare(strict_types=1);

namespace app\command;

use app\service\ChannelUnitPriceService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 按全站 reward_video 成功次数，将 video_total_amount 换算为 video_unit_price。
 * 未录入总收入时默认使用 config/pun_streamer.php 的 default_video_unit_price（0.01）。
 */
class PunSyncChannelUnitPrice extends Command
{
    protected function configure(): void
    {
        $this->setName('pun:sync-channel-unit-price')
            ->setDescription('同步全平台每日视频单价（有总价则总价÷次数；否则默认单价 0.01）')
            ->addOption('date', null, Option::VALUE_REQUIRED, '统计日 YYYY-MM-DD')
            ->addOption('yesterday', null, Option::VALUE_NONE, '统计日取昨日（Asia/Shanghai），供 crontab 使用')
            ->addOption('total', null, Option::VALUE_REQUIRED, '当日视频总收入（与 --date 合用可录入并同步）')
            ->addOption('all', null, Option::VALUE_NONE, '重算所有已录入 video_total_amount 的日期')
            ->addOption('remark', null, Option::VALUE_OPTIONAL, '备注（配合 --date --total）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $service = new ChannelUnitPriceService();
        $syncAll = (bool) $input->getOption('all');
        $date = $this->resolveStatDate($input);
        $totalRaw = $input->getOption('total');
        $total = $totalRaw !== null && $totalRaw !== '' ? (float) $totalRaw : null;

        try {
            if ($syncAll) {
                $rows = $service->syncAllConfiguredDates();
                if ($rows === []) {
                    $output->writeln('<comment>无已录入 video_total_amount 的日期</comment>');
                    return 0;
                }
                foreach ($rows as $row) {
                    $output->writeln($this->formatRow($row));
                }
                $output->writeln('<info>完成，共 ' . count($rows) . ' 天</info>');
                return 0;
            }

            if ($date === '') {
                $output->writeln('<error>请指定 --date=YYYY-MM-DD、--yesterday，或使用 --all</error>');
                return 1;
            }

            if ($total !== null) {
                $remark = $input->getOption('remark');
                $row = $service->upsertTotalAndSync($date, $total, is_string($remark) ? $remark : null);
            } else {
                // 未传 --total：有 video_total_amount 则重算，否则按默认单价 0.01 写入
                $row = $service->syncUnitPriceForDate($date, true);
            }

            $output->writeln($this->formatRow($row));
            if (!empty($row['used_default'])) {
                $output->writeln('<comment>未录入 video_total_amount，已使用默认单价；改表后可重跑本命令重算</comment>');
            }
            $output->writeln('<info>完成</info>');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    private function resolveStatDate(Input $input): string
    {
        if ((bool) $input->getOption('yesterday')) {
            return (new \DateTime('yesterday', new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
        }

        return trim((string) $input->getOption('date'));
    }

    /**
     * @param array{stat_date:string,video_total_amount:string,video_claim_count:int,video_unit_price:string,used_default?:bool} $row
     */
    private function formatRow(array $row): string
    {
        return sprintf(
            '%s total=%s claims=%d unit=%s',
            $row['stat_date'],
            $row['video_total_amount'],
            $row['video_claim_count'],
            $row['video_unit_price']
        );
    }
}
