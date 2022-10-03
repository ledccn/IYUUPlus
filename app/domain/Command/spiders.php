<?php

namespace app\domain\Command;

use app\domain\CommandInterface;

/**
 * 免费种爬虫下载 命令解析
 * Class spiders
 * @package app\domain\Command
 */
class spiders implements CommandInterface
{
    public function parse(array $param): string
    {
        return PHP_BINARY . ' ' . BASE_PATH . DIRECTORY_SEPARATOR . 'bin/spiders.php ' . $param['uuid'];
    }
}
