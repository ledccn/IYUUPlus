<?php

namespace app\domain;

/**
 * 命令解析接口
 * Interface CommandInterface
 * @package app\domain
 */
interface CommandInterface
{
    public function parse(array $param): string;
}
