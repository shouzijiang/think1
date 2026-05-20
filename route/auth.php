<?php
use think\facade\Route;

Route::group('auth', function () {
    // 微信登录 (公开)
    Route::any('wechat/login', 'Auth/wechatLogin');
    // 抖音登录 (公开)
    Route::any('douyin/login', 'Auth/douyinLogin');
    
    // 需要认证的路由
    Route::group(function () {
        Route::post('user/update', 'Auth/updateUser');
        Route::post('touch-login', 'Auth/touchLogin');
    })->middleware(\app\middleware\Auth::class);
});

