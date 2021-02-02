<?php
return [
    'listen'               => 'http://0.0.0.0:8787',
    'transport'            => 'tcp',
    'context'              => [],
    'name'                 => 'IYUUAutoReseed',
    'count'                => env('SERVER_PROCESS_COUNT', 1),
    'user'                 => env('SERVER_PROCESS_USER', ''),
    'group'                => env('SERVER_PROCESS_GROUP', ''),
    'pid_file'             => runtime_path() . '/webman.pid',
    'log_file'             => runtime_path() . '/workerman.log',
    'max_request'          => 1000,
    'stdout_file'          => runtime_path() . '/logs/stdout.log',
    'max_package_size'     => 10*1024*1024
];
