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
            ->addOption('force', null, Option::VALUE_NONE, '已有记录也重新生成')
            ->addOption('level', null, Option::VALUE_REQUIRED, '指定关卡编号（单个生成），需配合 --tier 使用');
    }

    protected function execute(Input $input, Output $output): int
    {
        $tierOpt = strtolower(trim((string) $input->getOption('tier')));
        $limit = max(0, (int) $input->getOption('limit'));
        $force = (bool) $input->getOption('force');
        $level = $input->getOption('level');

        // 单关卡模式
        if ($level !== null) {
            return $this->executeSingle($output, $tierOpt, (int) $level, $force);
        }

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

    private function executeSingle(Output $output, string $tier, int $levelNo, bool $force): int
    {
        if (!in_array($tier, ['beginner', 'mid', 'xhs'], true)) {
            $output->writeln("<error>无效 tier: {$tier}，须为 beginner|mid|xhs</error>");
            return 1;
        }
        if ($tier === 'beginner' && $levelNo <= 0) {
            $output->writeln('<error>beginner 关卡编号须 > 0</error>');
            return 1;
        }
        if ($levelNo < 0) {
            $output->writeln('<error>关卡编号须 >= 0</error>');
            return 1;
        }

        $service = new PunLevelAiExplainService();

        // 已有记录且非 force 模式则跳过
        if (!$force && $service->getExplainText($tier, $levelNo) !== '') {
            $output->writeln("<info>关卡 {$tier}#{$levelNo} 已有解读，跳过（使用 --force 强制重新生成）</info>");
            return 0;
        }

        try {
            $meta = $service->getLevelMeta($tier, $levelNo);
        } catch (\InvalidArgumentException $e) {
            $output->writeln("<error>关卡 {$tier}#{$levelNo} 不存在: {$e->getMessage()}</error>");
            return 1;
        }

        $output->writeln("<info>生成 {$tier}#{$levelNo}（答案: {$meta['answer']}, 提示: {$meta['hint']}）...</info>");

        $aiText = $service->generateExplainText((string) $meta['answer'], (string) $meta['hint'], [
            'tier'  => $tier,
            'level' => $levelNo,
        ]);

        if ($aiText === '') {
            $output->writeln('<error>AI 生成失败（查看日志了解详情）</error>');
            return 2;
        }

        try {
            $service->upsertExplain($tier, $levelNo, $aiText);
            $output->writeln("<info>已写入: {$aiText}</info>");
            return 0;
        } catch (\Throwable $e) {
            $output->writeln("<error>写入失败: {$e->getMessage()}</error>");
            return 2;
        }
    }
}
