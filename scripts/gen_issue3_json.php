<?php
$data = include __DIR__ . '/../config/pun_levels_issue3.php';
$existing = json_decode(file_get_contents(__DIR__ . '/../public/static/punGame/issue3.json'), true);

foreach ($data as $level => $chars) {
    if ($level >= 2000) {
        $existing[] = [
            'level' => $level,
            'answerLength' => count($chars),
            'answerType' => '',
            'author' => '乌吴捂勿'
        ];
    }
}

file_put_contents(
    __DIR__ . '/../public/static/punGame/issue3.json',
    json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

echo "Done. Total: " . count($existing) . " entries\n";
