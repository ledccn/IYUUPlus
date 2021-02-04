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
                return self::Reseed($param);
            default:
                return 'date';
        }
    }

    /**
     * 辅种任务
     * @param array $param
     * @return string
     */
    public static function Reseed(array $param):string
    {
        return PHP_BINARY . ' ' . BASE_PATH . DIRECTORY_SEPARATOR . 'bin/iyuu.php '.$param['uuid'];
    }

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
