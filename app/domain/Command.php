<?php

namespace app\domain;

/**
 * 解析计划任务命令
 * Class Command
 * @package app\domain
 */
class Command
{
    /**
     * @param array $param
     * @return string
     */
    public static function parse(array $param): string
    {
        $taskType = $param['type'] ?? 'default';
        $className = "app\\domain\\Command\\" . $taskType;
        if (\class_exists($className)) {
            $obj = new $className;
            if ($obj instanceof CommandInterface) {
                return $obj->parse($param);
            }
        }

        return '计划任务命令解析类 ' . $className . ' 不存在';
    }
}
