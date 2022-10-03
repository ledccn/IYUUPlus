<?php

namespace app\domain\Command;

use app\domain\CommandInterface;

/**
 * RSS下载 命令解析
 * Class rss
 * @package app\domain\Command
 */
class rss implements CommandInterface
{
    public function parse(array $param): string
    {
        return PHP_BINARY . ' ' . BASE_PATH . DIRECTORY_SEPARATOR . 'bin/rss.php ' . $param['uuid'];
    }
}
