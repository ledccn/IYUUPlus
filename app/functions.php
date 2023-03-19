<?php
/**
 * 自定义函数
 * 【载入时机】worker子进程
 */

use app\common\components\Curl as ICurl;
use app\domain\Config as domainConfig;

/**
 * 返回IYUU客户端版本号
 * @return string
 */
function IYUU_VERSION(): string
{
    return '2.1.19';
}

/**
 * 返回项目名称
 * @return string
 */
function IYUU_PROJECT_NAME(): string
{
    return 'IYUUPlus';
}

/**
 * 微信推送 爱语飞飞
 * @param string $text
 * @param string $desp
 * @return false|string
 */
function ff(string $text = '', string $desp = '')
{
    $config = domainConfig::getIyuu();
    if (empty($config) || empty($config['iyuu.cn'])) {
        return false;
    }

    $token = $config['iyuu.cn'];
    $desp = empty($desp) ? date("Y-m-d H:i:s") : $desp;
    $data = array(
        'text' => $text,
        'desp' => $desp
    );
    return ICurl::http_post('https://iyuu.cn/' . $token . '.send', $data);
}

/**
 * 获取全局唯一的UUID
 * @param int $pid
 * @return string
 */
function getUUID(int $pid = 0): string
{
    if (function_exists('posix_getpid')) {
        $pid = posix_getpid();
    }
    return sprintf('pid%d_%s', $pid, uniqid());
}

/**
 * 粗略验证字符串是否为IYUU的token
 * @param string $token
 * @return bool
 */
function check_token(string $token = ''): bool
{
    return (strlen($token) < 60) && (strpos($token, 'IYUU') === 0) && (strpos($token, 'T') < 15);
}

/**
 * 异步执行命令
 * @descr 原理为php的程序执行函数后台执行
 * @param string $cmd
 */
function run_exec(string $cmd = '')
{
    if (DIRECTORY_SEPARATOR === '\\') {
        pclose(popen('start /B ' . $cmd, 'r'));
    } else {
        pclose(popen($cmd, 'r'));
    }
}

/**
 * 工具函数,读取文件最后$n行
 * @param string $filename 文件的路径
 * @param int $n 文件的行数
 * @return string
 */
function fileLastLines(string $filename, int $n = 1): ?string
{
    // 文件存在并打开文件
    if (!is_file($filename) || !$fp = fopen($filename, 'r')) {
        return '';
    }

    $pos = -2;
    $eof = '';
    $lines = array();
    while ($n > 0) {
        while ($eof != "\n") {
            //在打开的文件中定位
            if (!fseek($fp, $pos, SEEK_END)) {
                //从文件指针中读取一个字符
                $eof = fgetc($fp);
                $pos--;
            } else {
                break;
            }
        }
        // 插入到数组的开头
        array_unshift($lines, fgets($fp));
        $eof = '';
        $n--;
    }

    return implode('', $lines);
}
