<?php

use think\facade\Route;

Route::group('pun', function () {
    // 排行榜（可选登录，不强制鉴权）
    Route::get('rank/list', 'Pun/rankList');
    // 论坛列表和详情(不强制鉴权)
    Route::get('forum/list', 'Pun/forumList');
    Route::get('forum/detail', 'Pun/forumDetail');

    // 以下需要登录
    Route::group(function () {
        Route::post('answer/submit', 'Pun/submitAnswer');
        Route::get('level/progress', 'Pun/levelProgress');
        Route::post('feedback/submit', 'Pun/feedbackSubmit');
        
        // 论坛发布和回复
        Route::post('forum/topic/create', 'Pun/forumTopicCreate');
        Route::post('forum/reply/create', 'Pun/forumReplyCreate');
    })->middleware(\app\middleware\Auth::class);
});
