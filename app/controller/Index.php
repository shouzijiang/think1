<?php

namespace app\controller;

use app\BaseController;

class Index extends BaseController
{
    public function index()
    {
        return 'aaaa';
    }

    public function hello($name = 'ThinkPHP8')
    {
        return '11hello,' . $name;
    }
}
