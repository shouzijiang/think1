<?php

namespace app\controller;

use app\BaseController;
use think\facade\Log;

class Index extends BaseController
{
    public function index()
    {
        Log::info('首页进入成功:' . 1);
        return 'aaaa';
    }

    public function hello($name = 'ThinkPHP8')
    {
        Log::info('hello进入成功:' . 'ThinkPHP8');
        return '11hello,' . $name;
    }
}
