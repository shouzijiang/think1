<?php

use think\facade\Route;

Route::group('pun', function () {
    // 排行榜（可选登录，不强制鉴权）
    Route::get('rank/list', 'Pun/rankList');
    // 以下需要登录
    Route::group(function () {
        Route::any('answer/submit', 'Pun/submitAnswer');
        Route::get('level/progress', 'Pun/levelProgress');
    })->middleware(\app\middleware\Auth::class);
});
