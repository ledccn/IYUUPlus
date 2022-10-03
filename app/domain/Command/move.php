<?php

namespace app\domain\Command;

use app\domain\CommandInterface;

/**
 * 转移种子任务命令解析
 * Class move
 * @package app\domain\Command
 */
class move implements CommandInterface
{
    public function parse(array $param): string
    {
        return PHP_BINARY . ' ' . BASE_PATH . DIRECTORY_SEPARATOR . 'bin/move.php ' . $param['uuid'];
    }
}
