<?php
/**
 * Generate category-filtered JSON files from issue3.json
 * Reads category definitions from pun_album_category table
 *
 * Usage:
 *   php scripts/gen_category_json.php                  # 全部
 *   php scripts/gen_category_json.php --slug=dragonboat  # 单个
 */

$targetSlug = null;
$args = getopt('', ['slug:']);
if (!empty($args['slug'])) {
    $targetSlug = trim($args['slug']);
}

$issue3Path = __DIR__ . '/../public/static/punGame/issue3.json';
$outputDir = __DIR__ . '/../public/static/punGame/';

$data = json_decode(file_get_contents($issue3Path), true);
if (!$data) {
    die("Error: Cannot read issue3.json\n");
}

// Read category definitions from DB
require __DIR__ . '/../vendor/autoload.php';

$app = new \think\App();
$app->initialize();
$db = \think\facade\Db::class;

$query = \think\facade\Db::name('pun_album_category')
    ->where('is_active', 1)
    ->order('sort_order', 'asc');
if ($targetSlug !== null) {
    $query->where('slug', $targetSlug);
}
$rows = $query->select()->toArray();

if (empty($rows)) {
    $hint = $targetSlug ? "slug={$targetSlug}" : 'No active categories';
    die("Error: {$hint}\n");
}

$categories = [];
foreach ($rows as $r) {
    $answerTypes = json_decode($r['answer_types'] ?? '[]', true) ?: [];
    $categories[$r['slug']] = [
        'label'       => $r['label'],
        'icon'        => $r['icon'] ?? '',
        'answerTypes' => $answerTypes,
    ];
}

// Group entries by category
$categoryEntries = [];
$subTypeCounts = [];  // track counts per answerType per category
foreach ($categories as $slug => $cat) {
    $categoryEntries[$slug] = [];
    foreach ($cat['answerTypes'] as $type) {
        $subTypeCounts[$slug][$type] = 0;
    }
}

$unmatched = 0;
foreach ($data as $entry) {
    $answerType = isset($entry['answerType']) ? $entry['answerType'] : '';
    if (empty($answerType) || $answerType === '无') {
        $unmatched++;
        continue;
    }

    $matched = false;
    foreach ($categories as $slug => $cat) {
        if (in_array($answerType, $cat['answerTypes'])) {
            $categoryEntries[$slug][] = [
                'level'        => $entry['level'],
                'answerLength' => $entry['answerLength'],
                'answerType'   => $answerType,
                'author'       => isset($entry['author']) ? $entry['author'] : '',
            ];
            $subTypeCounts[$slug][$answerType]++;
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $unmatched++;
    }
}

// Write category JSON files
$manifest = ['categories' => []];
foreach ($categories as $slug => $cat) {
    $entries = $categoryEntries[$slug];
    $filePath = $outputDir . 'issue3_' . $slug . '.json';
    file_put_contents(
        $filePath,
        json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    $totalCount = count($entries);
    echo sprintf("  %s (%s): %d entries → %s\n", $cat['label'], $slug, $totalCount, basename($filePath));

    // 回写 total_count 到 DB
    \think\facade\Db::name('pun_album_category')
        ->where('slug', $slug)
        ->update(['total_count' => $totalCount]);

    // Build manifest
    $manifest['categories'][$slug] = [
        'label'      => $cat['label'],
        'icon'       => $cat['icon'] ?? '',
        'fileName'   => 'issue3_' . $slug . '.json',
        'answerTypes'=> $cat['answerTypes'],
        'totalCount' => $totalCount,
    ];
}

// Write manifest JSON（全量时才写，单 slug 跳过避免覆盖其他分类数据）
if ($targetSlug === null) {
    $manifestPath = $outputDir . 'issue3_categories.json';
    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    echo "  Manifest → " . basename($manifestPath) . "\n";
}

echo "\nTotal entries in issue3.json: " . count($data) . "\n";
echo "Categorized: " . (count($data) - $unmatched) . "\n";
echo "Unmatched (无 or no category): " . $unmatched . "\n";
echo "Done.\n";
