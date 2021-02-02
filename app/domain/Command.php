<?php
namespace app\domain;

/**
 * 解析计划任务命令
 * Class Command
 * @package app\domain
 */
class Command
{
    public static function parse(array $param):string
    {
        $taskType = $param['type'] ?? null;
        switch ($taskType) {
            case 'reseed':
                return PHP_BINARY . ' ' . BASE_PATH . DIRECTORY_SEPARATOR . 'bin/iyuu.php '.$param['uuid'];
            default:
                return 'date';
        }
    }

    public static function Reseed()
    {}

    public static function Download()
    {}

    public static function Url()
    {}

    public static function Shell()
    {}

    public static function Patch()
    {}

    public static function Ddns()
    {}

    public static function ClearLog()
    {}
}
