<?php
/**
 * 从指定关卡开始，批量重新生成 AI 解读
 *
 * 用法：
 *   php scripts/regenerate_explain.php xhs 2700
 *   php scripts/regenerate_explain.php xhs 2700 --dry-run    （仅预览，不实际调用 AI）
 */

declare(strict_types=1);

// 加载 ThinkPHP
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../app/App.php';
$app->initialize();

use app\service\PunLevelAiExplainService;

$tier = $argv[1] ?? 'xhs';
$startLevel = (int) ($argv[2] ?? 1);
$dryRun = in_array('--dry-run', $argv, true);

// 获取 tier 对应的题库 key
$configKey = match ($tier) {
    'xhs'      => 'pun_levels_issue3',
    'mid'      => 'pun_levels_issue2',
    'beginner' => 'pun_levels',
    default    => null,
};
if ($configKey === null) {
    echo "用法: php scripts/regenerate_explain.php <xhs|mid|beginner> <startLevel> [--dry-run]\n";
    exit(1);
}

$data = \think\facade\Config::get($configKey, []);
$levels = array_filter(array_keys($data), fn($l) => (int) $l >= $startLevel);
sort($levels, SORT_NUMERIC);

echo "📋 tier={$tier}  start={$startLevel}  共 " . count($levels) . " 关";
if ($dryRun) {
    echo "  [DRY-RUN 不会实际调用 AI]\n";
} else {
    echo "\n";
}
echo str_repeat('-', 50) . "\n";

$service = new PunLevelAiExplainService();
$generated = 0;
$failed = 0;

foreach ($levels as $levelNo) {
    $levelNo = (int) $levelNo;

    if ($dryRun) {
        echo "  [DRY] {$tier}#{$levelNo} …\n";
        continue;
    }

    try {
        $meta = $service->getLevelMeta($tier, $levelNo);
    } catch (\InvalidArgumentException $e) {
        echo "  ❌ {$tier}#{$levelNo} 关卡不存在: {$e->getMessage()}\n";
        $failed++;
        continue;
    }

    $aiText = $service->generateExplainText((string) $meta['answer'], (string) $meta['hint'], [
        'tier'  => $tier,
        'level' => $levelNo,
    ]);

    if ($aiText === '') {
        echo "  ❌ {$tier}#{$levelNo} AI 返回空（答案: {$meta['answer']}）\n";
        $failed++;
    } else {
        try {
            $service->upsertExplain($tier, $levelNo, $aiText);
            echo "  ✅ {$tier}#{$levelNo} {$aiText}\n";
            $generated++;
        } catch (\Throwable $e) {
            echo "  ❌ {$tier}#{$levelNo} 写入失败: {$e->getMessage()}\n";
            $failed++;
        }
    }

    // 避免请求过快
    usleep(300000); // 0.3s
}

echo str_repeat('-', 50) . "\n";
echo "✅ 完成: generated={$generated}  failed={$failed}\n";
