<?php
/**
 * Generate category-filtered JSON files from issue3.json
 * Groups entries by answerType into 4 categories for the album picker
 *
 * Usage: php scripts/gen_category_json.php
 */

$issue3Path = __DIR__ . '/../public/static/punGame/issue3.json';
$outputDir = __DIR__ . '/../public/static/punGame/';

$data = json_decode(file_get_contents($issue3Path), true);
if (!$data) {
    die("Error: Cannot read issue3.json\n");
}

// Category definitions with answerType mappings
// Each category has subTypes: { displayName => [answerType1, answerType2, ...] }
// Merged subTypes combine entries from multiple answerTypes under one display name
$categories = [
    'character' => [
        'label'      => '人物篇',
        'icon'       => '🧑‍🎨',
        'color'      => '#6C5CE7',
        'answerTypes'=> ['历史人物', '漫威影视角色', '世界著名音乐家', '明星', '科学家', '动漫角色'],
    ],
    'city' => [
        'label'      => '城市篇',
        'icon'       => '🏙️',
        'color'      => '#48DBFB',
        'answerTypes'=> ['城市'],
    ],
    'landscape' => [
        'label'      => '风景名胜篇',
        'icon'       => '🏔️',
        'color'      => '#0ABDE3',
        'answerTypes'=> ['风景名胜'],
    ],
    'food' => [
        'label'      => '食物篇',
        'icon'       => '🍜',
        'color'      => '#FF6B6B',
        'answerTypes'=> ['美食', '食物', '小吃', '家常下饭菜', '调料', '茶', '菜名', '饮品', '菜品'],
    ],
    'fruit' => [
        'label'      => '水果篇',
        'icon'       => '🍎',
        'color'      => '#F8A5C2',
        'answerTypes'=> ['水果'],
    ],
    'dessert' => [
        'label'      => '甜品篇',
        'icon'       => '🍰',
        'color'      => '#FD79A8',
        'answerTypes'=> ['甜品', '点心'],
    ],
    'idiom' => [
        'label'      => '成语篇',
        'icon'       => '📚',
        'color'      => '#FECA57',
        'answerTypes'=> ['成语'],
    ],
    'plant' => [
        'label'      => '植物篇',
        'icon'       => '🌿',
        'color'      => '#26DE81',
        'answerTypes'=> ['植物', '中药材'],
    ],
    'christmas' => [
        'label'      => '圣诞节篇',
        'icon'       => '🎄',
        'color'      => '#E74C3C',
        'answerTypes'=> ['圣诞节'],
    ],
    'newyear' => [
        'label'      => '新年篇',
        'icon'       => '🧧',
        'color'      => '#E17055',
        'answerTypes'=> ['新年快乐'],
    ],
    'zodiac' => [
        'label'      => '生肖篇',
        'icon'       => '🐲',
        'color'      => '#F39C12',
        'answerTypes'=> ['鼠', '牛', '虎', '兔', '马', '羊'],
    ],
];

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

    // Build manifest
    $manifest['categories'][$slug] = [
        'label'      => $cat['label'],
        'icon'       => $cat['icon'],
        'color'      => $cat['color'],
        'fileName'   => 'issue3_' . $slug . '.json',
        'answerTypes'=> $cat['answerTypes'],
        'totalCount' => $totalCount,
    ];
}

// Write manifest JSON
$manifestPath = $outputDir . 'issue3_categories.json';
file_put_contents(
    $manifestPath,
    json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);
echo "  Manifest → " . basename($manifestPath) . "\n";

echo "\nTotal entries in issue3.json: " . count($data) . "\n";
echo "Categorized: " . (count($data) - $unmatched) . "\n";
echo "Unmatched (无 or no category): " . $unmatched . "\n";
echo "Done.\n";
