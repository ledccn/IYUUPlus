<?php

namespace app\domain\Command;

use app\domain\CommandInterface;

/**
 * 辅种任务命令解析
 * Class reseed
 * @package app\domain\Command
 */
class reseed implements CommandInterface
{
    public function parse(array $param): string
    {
        return PHP_BINARY . ' ' . BASE_PATH . DIRECTORY_SEPARATOR . 'bin/iyuu.php ' . $param['uuid'];
    }
}
