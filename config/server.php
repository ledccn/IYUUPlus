<?php
return [
    'listen' => defined('IYUU_LISTEN_IPV6') ? 'http://[::]:8787' : 'http://0.0.0.0:8787',
    'transport' => 'tcp',
    'context' => [],
    'name' => 'IYUUPlus',
    'count' => env('SERVER_PROCESS_COUNT', 2),
    'user' => env('SERVER_PROCESS_USER', ''),
    'group' => env('SERVER_PROCESS_GROUP', ''),
    'event_loop' => '',
    'stop_timeout' => 2,
    'pid_file' => runtime_path() . '/webman.pid',
    'log_file' => runtime_path() . '/workerman.log',
    'status_file' => runtime_path() . '/webman.status',
    'stdout_file' => runtime_path() . '/logs/stdout.log',
    'max_request' => 1000,
    'max_package_size' => 10 * 1024 * 1024
];
