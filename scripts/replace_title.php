<?php
$files = [
    'pun-game/src/pages/mine/mine.vue',
    'pun-game/src/pages/feedback/feedback.vue',
    'pun-game/src/pages/battleRoom/battleRoom.vue',
    'pun-game/src/pages/rank/rank.vue',
    'pun-game/src/pages/levels/levels.vue',
    'pun-game/src/pages/battlePlay/battlePlay.vue',
    'pun-game/src/pages/forum/forum.vue',
    'pun-game/src/pages/battleHistory/battleHistory.vue',
    'pun-game/src/composables/useWechatPageShare.js',
];
$base = __DIR__ . '/..';
foreach ($files as $f) {
    $path = "$base/$f";
    $content = file_get_contents($path);
    $new = str_replace('谐音梗图', '谐音梗猜一猜', $content);
    if ($new !== $content) {
        file_put_contents($path, $new);
        echo "updated: $f\n";
    }
}
echo "done\n";
