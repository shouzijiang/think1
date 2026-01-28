<?php
use think\facade\Route;

Route::group('auth', function () {
    // 微信登录 (公开)
    Route::any('wechat/login', 'Auth/wechatLogin');
    
    // 需要认证的路由
    Route::group(function () {
        Route::post('user/update', 'Auth/updateUser');
    })->middleware(\app\middleware\Auth::class);
});

