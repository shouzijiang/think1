<?php

use think\facade\Route;

// 微信相关路由
Route::any('auth/wechat/login', 'Auth/wechatLogin');

