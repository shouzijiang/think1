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
 */
class PunSyncChannelUnitPrice extends Command
{
    protected function configure(): void
    {
        $this->setName('pun:sync-channel-unit-price')
            ->setDescription('同步全平台每日视频单价（总价 ÷ 次数，截断4位不四舍五入）')
            ->addOption('date', null, Option::VALUE_REQUIRED, '统计日 YYYY-MM-DD')
            ->addOption('total', null, Option::VALUE_REQUIRED, '当日视频总收入（与 --date 合用可录入并同步）')
            ->addOption('all', null, Option::VALUE_NONE, '重算所有已录入 video_total_amount 的日期')
            ->addOption('remark', null, Option::VALUE_OPTIONAL, '备注（配合 --date --total）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $service = new ChannelUnitPriceService();
        $syncAll = (bool) $input->getOption('all');
        $date = trim((string) $input->getOption('date'));
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
                $output->writeln('<error>请指定 --date=YYYY-MM-DD，或使用 --all</error>');
                return 1;
            }

            if ($total !== null) {
                $remark = $input->getOption('remark');
                $row = $service->upsertTotalAndSync($date, $total, is_string($remark) ? $remark : null);
            } else {
                $row = $service->syncUnitPriceForDate($date);
            }

            $output->writeln($this->formatRow($row));
            $output->writeln('<info>完成</info>');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    /**
     * @param array{stat_date:string,video_total_amount:string,video_claim_count:int,video_unit_price:string} $row
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
