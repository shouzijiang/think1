<?php
/**
 * 生成 issue3.json 脚本
 *
 * 用法: php scripts/gen_issue3_json.php
 *
 * 修改下方 $sections 数组即可控制不同关卡区间的 answerType 和 author。
 * 脚本会自动从 pun_levels_issue3.php 读取关卡数据，合并到现有 JSON 中。
 */

// ============================================================
// 📌 配置区：只需修改这里
// ============================================================
$sections = [
    [
        'start'      => 4702,         // 起始 level（含）
        'end'        => 4707,         // 结束 level（含）
        'answerType' => '无',
        'author'     => '谐音梗事务所',
    ],
    // 后续新增区间，复制上面的格式粘贴在下面即可：
    // [
    //     'start'      => 5001,
    //     'end'        => 5200,
    //     'answerType' => 'xxx',
    //     'author'     => 'yyy',
    // ],
];

// ============================================================
// 执行逻辑（一般不需要修改）
// ============================================================

$data = include __DIR__ . '/../config/pun_levels_issue3.php';
$jsonPath = __DIR__ . '/../public/static/punGame/issue3.json';
$existing = json_decode(file_get_contents($jsonPath), true);

// 建立已有 level → 索引 的映射，用于去重更新
$existingIndex = [];
foreach ($existing as $i => $entry) {
    $existingIndex[$entry['level']] = $i;
}

$added = 0;
$updated = 0;

foreach ($sections as $section) {
    $start = $section['start'];
    $end   = $section['end'];
    $answerType = $section['answerType'];
    $author     = $section['author'];

    foreach ($data as $level => $chars) {
        if ($level < $start || $level > $end) {
            continue;
        }

        $entry = [
            'level'        => $level,
            'answerLength' => count($chars),
            'answerType'   => $answerType,
            'author'       => $author,
        ];

        if (isset($existingIndex[$level])) {
            // 更新已有条目
            $existing[$existingIndex[$level]] = $entry;
            $updated++;
        } else {
            // 新增条目
            $existing[] = $entry;
            $added++;
        }
    }
}

// 按 level 排序
usort($existing, function ($a, $b) {
    return $a['level'] - $b['level'];
});

file_put_contents(
    $jsonPath,
    json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
);

echo "✅ 完成！\n";
echo "   新增: {$added} 条\n";
echo "   更新: {$updated} 条\n";
echo "   总计: " . count($existing) . " 条\n";
echo "   输出: {$jsonPath}\n";
