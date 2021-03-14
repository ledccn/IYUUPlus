<?php
require_once dirname(__DIR__) . '/init.php';
require_once ROOT_PATH . '/src/helper.php';
echo microtime(true).' 当前脚本路径：'.__FILE__.PHP_EOL;

use IYUU\Rss\AbstractRss;
use Workerman\Autoloader;

Autoloader::setRootPath(ROOT_PATH);

global $argv;
if (count($argv) < 2) {
    echo "执行RSS订阅命令时，缺少站点标识！！目前支持以下站点：".PHP_EOL;
    ShowTableSites('Rss');
    echo "命令格式：php rss.php 站点标识".PHP_EOL.PHP_EOL;
    echo "举例：RSS订阅备胎，只需要输入：php rss.php beitai".PHP_EOL.PHP_EOL;
    exit(1);
} else {
    echo "RSS订阅脚本，目前支持以下站点：".PHP_EOL;
    ShowTableSites('Rss');
    echo "命令格式：php rss.php 站点标识".PHP_EOL.PHP_EOL;
}
// php脚本文件
$start_file = $argv[0];
// 参数1：站点标志
$command  = strtolower(trim($argv[1]));
// 参数2：扩展参数
$command2 = isset($argv[2]) ? $argv[2] : '';
// RSS页面URL
$url = '';
$fileName = $command;
// 检查解码类
if (!is_file(APP_PATH.'Rss'.DS.$fileName.'.php')) {
    echo '解码文件：' .APP_PATH. 'Rss' .DS.$fileName. '.php' . " 不存在".PHP_EOL.PHP_EOL;
    echo "举例：RSS订阅备胎，只需要输入：php rss.php beitai".PHP_EOL.PHP_EOL;
    exit(1);
}

// 通用部分
$obj = AbstractRss::getInstance($fileName);
$obj->run($url);
