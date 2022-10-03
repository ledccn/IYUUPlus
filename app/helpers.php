<?php
/**
 * 自定义函数
 * 【载入时机】composer.json
 */
/**
 * 数据目录
 * @return string
 */
function db_path(): string
{
    return base_path() . DIRECTORY_SEPARATOR . 'db';
}

/**
 * 计划任务目录
 */
function cron_path(): string
{
    return runtime_path() . DIRECTORY_SEPARATOR . 'crontab';
}

if (!function_exists('env')) {
    /**
     * @param $key
     * @param null $default
     * @return array|bool|string|null
     */
    function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (($valueLength = strlen($value)) > 1 && $value[0] === '"' && $value[$valueLength - 1] === '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

/**
 * CLI打印调试
 * @param $data
 * @param bool $echo
 * @return string
 */
function cli($data, bool $echo = true): string
{
    $str = '----------------------------------------date:' . date("Y-m-d H:i:s") . PHP_EOL;
    if (is_bool($data)) {
        $show_data = $data ? 'true' : 'false';
    } elseif (is_null($data)) {
        $show_data = 'null';
    } else {
        $show_data = print_r($data, true);
    }
    $str .= $show_data;
    $str .= PHP_EOL . '----------------------------------------memory_get_usage:' . memory_get_usage(true) . PHP_EOL . PHP_EOL;
    if ($echo) {
        echo $str;
        return '';
    }
    return $str;
}

/**
 * 是否win平台
 * @return bool
 */
function isWin(): bool
{
    return DIRECTORY_SEPARATOR === '\\';
}

/**
 * 对布尔型进行格式化
 * @param mixed $value 变量值
 * @return boolean   string 格式化后的变量
 */
function booleanParse($value): bool
{
    $rs = $value;

    if (!is_bool($value)) {
        if (is_numeric($value)) {
            $rs = ($value + 0) > 0;
        } elseif (is_string($value)) {
            $rs = in_array(strtolower($value), ['ok', 'true', 'success', 'on', 'yes', '(ok)', '(true)', '(success)', '(on)', '(yes)']);
        } else {
            $rs = (bool)$value;
        }
    }

    return $rs;
}
