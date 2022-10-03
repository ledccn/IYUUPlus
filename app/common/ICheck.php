<?php

namespace app\common;

/**
 * 检查扩展与函数
 */
class ICheck
{
    /**
     * 待检查扩展列表
     * @var array
     */
    private static $extends = [
        //Win
        '1' => [
            'json',
            'curl',
            'mbstring',
        ],
        //Linux
        '2' => [
            'json',
            'curl',
            'mbstring',
            'pcntl',
            'posix',
        ]
    ];

    /**
     * 待检查函数列表
     * @var array
     */
    private static $functions = [
        //Win
        '1' => [
            'usleep',
            'sleep',
            'ob_start',
            'ob_end_clean',
            'ob_get_contents',
            'proc_open',
            'proc_close',
            'popen',
            'pclose',
        ],
        //Linux
        '2' => [
            'chdir',
            'usleep',
            'sleep',
            'ob_start',
            'ob_end_clean',
            'ob_get_contents',
            'proc_open',
            'proc_close',
            'popen',
            'pclose',
            'pcntl_fork',
            'posix_setsid',
            'posix_getpid',
            'posix_getppid',
            'pcntl_wait',
            'posix_kill',
            'pcntl_signal',
            'pcntl_alarm',
            'pcntl_waitpid',
            'pcntl_signal_dispatch',
            'stream_socket_server',
            'stream_socket_client',
        ]
    ];

    /**
     * 解析检查运行环境
     * @param string $currentOs
     */
    public static function analysis(string $currentOs)
    {
        //检查版本
        if (version_compare(PHP_VERSION, "7.2.0", "<")) {
            exit('php version < 7.2.0');
        }
        //检查扩展
        $waitExtends = static::$extends[$currentOs];
        foreach ($waitExtends as $extend) {
            if (!extension_loaded($extend)) {
                exit("php_{$extend}.(dll/so) is not load,please check php.ini file");
            }
        }
        //检查函数
        $waitFunctions = static::$functions[$currentOs];
        foreach ($waitFunctions as $func) {
            if (!function_exists($func)) {
                exit("function $func may be disabled,please check disable_functions in php.ini");
            }
        }
    }
}
