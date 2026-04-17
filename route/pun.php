<?php

use think\facade\Route;

Route::group('pun', function () {
    // 排行榜（可选登录，不强制鉴权）
    Route::get('rank/list', 'Pun/rankList');
    // 论坛列表和详情(不强制鉴权)
    Route::get('forum/list', 'Pun/forumList');
    Route::get('forum/detail', 'Pun/forumDetail');
    // 首页「本期更新」弹窗（不强制鉴权）
    Route::get('changelog/latest', 'Pun/changelogLatest');
    // 首页「已有 X 位好友 · 累计 Y 次答题」（不强制鉴权）
    Route::get('stats/home', 'Pun/homeStats');
    // 1V1：全局对战排行（胜/负统计，不强制鉴权）
    Route::get('battle/rank', 'PunBattle/battleRank');

    // 以下需要登录
    Route::group(function () {
        Route::post('answer/submit', 'Pun/submitAnswer');
        Route::post('level/reveal-hint', 'Pun/revealHint');
        Route::post('level/share-reward', 'Pun/shareReward');
        Route::post('level/reward-video', 'Pun/rewardVideo');
        Route::get('level/progress', 'Pun/levelProgress');
        Route::post('feedback/submit', 'Pun/feedbackSubmit');
        
        // 信箱（站内信；新增邮件由库内 INSERT pun_game_mail，无 HTTP 发送接口）
        Route::get('mail/list', 'PunMail/mailList');
        Route::get('mail/detail', 'PunMail/mailDetail');
        
        // 论坛发布和回复
        Route::post('forum/topic/create', 'Pun/forumTopicCreate');
        Route::post('forum/reply/create', 'Pun/forumReplyCreate');

        // 1V1 对战房间
        Route::post('battle/create', 'PunBattle/createRoom');
        Route::get('battle/history', 'PunBattle/history');
    })->middleware(\app\middleware\Auth::class);
});
