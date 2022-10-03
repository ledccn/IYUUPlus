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
    return '2.1.1';
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
 * 获取当前版本commit
 * @param string $branch
 * @param bool $short
 * @return string
 */
function get_current_git_commit(string $branch = 'master', bool $short = true): string
{
    if ($hash = file_get_contents(sprintf(base_path() . '/.git/refs/heads/%s', $branch))) {
        $hash = trim($hash);

        return $short ? substr($hash, 0, 7) : $hash;
    }
    return '';
}

/**
 * 获取当前版本时间
 * @param string $branch
 * @return string
 */
function get_current_git_filemtime(string $branch = 'master'): string
{
    if ($time = filemtime(sprintf(base_path() . '/.git/refs/heads/%s', $branch))) {
        return date("Y-m-d H:i:s", $time);
    }
    return '';
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
 * 转换成易读的容量格式(包含小数)
 * @param int|float $bytes 字节
 * @param string $delimiter 分隔符 [&nbsp; | <br />]
 * @param int $decimals 保留小数点
 * @return string
 */
function dataSize($bytes, string $delimiter = '', int $decimals = 2): string
{
    $type = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $i = 0;
    while ($bytes >= 1024) {
        $bytes /= 1024;
        $i++;
    }

    return number_format($bytes, $decimals) . $delimiter . $type[$i];
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
        return null;
    }

    $pos = -2;
    $eof = '';
    $lines = array();
    while ($n > 0) {
        $str = '';
        while ($eof != "\n") {
            //在打开的文件中定位
            if (!fseek($fp, $pos, SEEK_END)) {
                //从文件指针中读取一个字符
                $eof = fgetc($fp);
                $pos--;
                $str = $eof . $str;
            } else {
                break;
            }
        }
        // 插入到数组的开头
        array_unshift($lines, $str);
        $eof = '';
        $n--;
    }

    return implode('', $lines);
}
