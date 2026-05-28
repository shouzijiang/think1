<?php

declare(strict_types=1);

namespace app\command;

use app\service\PunLevelAiExplainService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class PunGenerateLevelExplain extends Command
{
    protected function configure(): void
    {
        $this->setName('pun:generate-level-explain')
            ->setDescription('批量预生成关卡 AI 趣味解读（缺失写入，已有跳过）')
            ->addOption('tier', null, Option::VALUE_REQUIRED, 'beginner|mid|xhs|all', 'all')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '最多新生成条数，0 不限制', '0')
            ->addOption('force', null, Option::VALUE_NONE, '已有记录也重新生成');
    }

    protected function execute(Input $input, Output $output): int
    {
        $tierOpt = strtolower(trim((string) $input->getOption('tier')));
        $limit = max(0, (int) $input->getOption('limit'));
        $force = (bool) $input->getOption('force');

        $tiers = $tierOpt === 'all'
            ? ['beginner', 'mid', 'xhs']
            : [$tierOpt];

        $service = new PunLevelAiExplainService();
        $total = ['generated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($tiers as $tier) {
            if (!in_array($tier, ['beginner', 'mid', 'xhs'], true)) {
                $output->writeln("<error>无效 tier: {$tierOpt}，须为 beginner|mid|xhs|all</error>");
                return 1;
            }

            $used = $total['generated'] + $total['failed'];
            $batchLimit = $limit > 0 ? max(0, $limit - $used) : 0;
            if ($limit > 0 && $batchLimit === 0 && $used > 0) {
                break;
            }

            $output->writeln("<info>开始 {$tier} ...</info>");
            try {
                $stats = $service->generateMissingForTier($tier, $batchLimit, $force);
            } catch (\Throwable $e) {
                $output->writeln("<error>{$tier} 失败: {$e->getMessage()}</error>");
                return 1;
            }

            foreach ($stats as $k => $v) {
                $total[$k] += $v;
            }

            $output->writeln(sprintf(
                '  %s: generated=%d skipped=%d failed=%d',
                $tier,
                $stats['generated'],
                $stats['skipped'],
                $stats['failed']
            ));
        }

        $output->writeln(sprintf(
            '<info>完成: generated=%d skipped=%d failed=%d</info>',
            $total['generated'],
            $total['skipped'],
            $total['failed']
        ));

        return $total['failed'] > 0 ? 2 : 0;
    }
}
