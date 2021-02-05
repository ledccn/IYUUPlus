<?php
/**
 * 返回IYUU当前版本号
 * @return string
 */
function IYUU_VERSION():string
{
    return '2.0.0';
}

/**
 * 返回项目名称
 * @return string
 */
function iyuu_name():string
{
    return 'IYUUPlus';
}

/**
 * 获取当前版本commit
 * @param string $branch
 * @param bool $short
 * @return string
 */
function get_current_git_commit($branch = 'master', $short = true):string
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
function get_current_git_filemtime($branch = 'master'):string
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
function ff($text = '', $desp = '')
{
    $token = env('IYUU', '');
    $desp = ($desp=='')?date("Y-m-d H:i:s") :$desp;
    $postdata = http_build_query(array(
        'text' => $text,
        'desp' => $desp
    ));
    $opts = array('http' =>	array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata
    ));
    $context  = stream_context_create($opts);
    $result = file_get_contents('http://iyuu.cn/'.$token.'.send', false, $context);
    return $result;
}

/**
 * 获取全局唯一的UUID
 * @param int $pid
 * @return string
 */
function getUUID(int $pid = 0):string
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
function check_token($token = ''):bool
{
    return (strlen($token) < 60) && (strpos($token, 'IYUU') === 0) && (strpos($token, 'T') < 15);
}

/**
 * 异步执行命令
 * @descr 原理为php的程序执行函数后台执行
 * @param string $cmd
 */
function run_exec($cmd = '')
{
    if(DIRECTORY_SEPARATOR === '\\')
    {
        pclose(popen('start /B '.$cmd, 'r'));
    } else {
        pclose(popen($cmd, 'r'));
    }
}
