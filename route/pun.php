<?php

use think\facade\Route;

Route::group('pun', function () {
    // 排行榜（可选登录）
    Route::get('rank/list', 'Pun/rankList');
    // 共创列表、详情（可选登录）
    Route::get('cocreate/list', 'Cocreate/list');
    Route::get('cocreate/detail', 'Cocreate/detail');
    // 以下需要登录
    Route::group(function () {
        Route::post('answer/submit', 'Pun/submitAnswer');
        Route::get('level/progress', 'Pun/levelProgress');
        Route::post('feedback/submit', 'Pun/feedbackSubmit');
        // 共创
        Route::post('cocreate/words/generate', 'Cocreate/wordsGenerate');
        Route::post('cocreate/image/generate', 'Cocreate/imageGenerate');
        Route::post('cocreate/submit', 'Cocreate/submit');
        Route::post('cocreate/answer/submit', 'Cocreate/answerSubmit');
    })->middleware(\app\middleware\Auth::class);
});
