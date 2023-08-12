<?php

use support\Request;

return [
    'debug' => env('APP_DEBUG', false),
    'default_timezone' => 'Asia/Shanghai',
    'error_reporting' => E_ALL,
    'request_class' => Request::class,
    'public_path' => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path' => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => '',
    //关闭控制器复用，每个请求都会触发对应控制器的__construct()构造函数
    'controller_reuse' => false,
];
