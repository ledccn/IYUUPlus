<?php
echo <<<EOF
IIIIIIIIIIYYYYYYY       YYYYYYYUUUUUUUU     UUUUUUUUUUUUUUUU     UUUUUUUU
I::::::::IY:::::Y       Y:::::YU::::::U     U::::::UU::::::U     U::::::U
I::::::::IY:::::Y       Y:::::YU::::::U     U::::::UU::::::U     U::::::U
II::::::IIY::::::Y     Y::::::YUU:::::U     U:::::UUUU:::::U     U:::::UU
  I::::I  YYY:::::Y   Y:::::YYY U:::::U     U:::::U  U:::::U     U:::::U
  I::::I     Y:::::Y Y:::::Y    U:::::D     D:::::U  U:::::D     D:::::U
  I::::I      Y:::::Y:::::Y     U:::::D     D:::::U  U:::::D     D:::::U
  I::::I       Y:::::::::Y      U:::::D     D:::::U  U:::::D     D:::::U
  I::::I        Y:::::::Y       U:::::D     D:::::U  U:::::D     D:::::U
  I::::I         Y:::::Y        U:::::D     D:::::U  U:::::D     D:::::U
  I::::I         Y:::::Y        U:::::D     D:::::U  U:::::D     D:::::U
  I::::I         Y:::::Y        U::::::U   U::::::U  U::::::U   U::::::U
II::::::II       Y:::::Y        U:::::::UUU:::::::U  U:::::::UUU:::::::U
I::::::::I    YYYY:::::YYYY      UU:::::::::::::UU    UU:::::::::::::UU
I::::::::I    Y:::::::::::Y        UU:::::::::UU        UU:::::::::UU
IIIIIIIIII    YYYYYYYYYYYYY          UUUUUUUUU            UUUUUUUUU

EOF;
echo microtime(true).' 当前时间：'.date('Y-m-d H:i:s').PHP_EOL;
echo microtime(true).' 当前操作系统：'.PHP_OS.PHP_EOL;
echo microtime(true).' 当前运行环境：'.PHP_OS_FAMILY.PHP_EOL;
echo microtime(true).' 当前接口类型：'.PHP_SAPI.PHP_EOL;
echo microtime(true).' PHP二进制文件：'.PHP_BINARY.PHP_EOL;
echo microtime(true).' PHP版本号：'.PHP_VERSION.PHP_EOL;
echo microtime(true).' 正在加载composer包管理器...'.PHP_EOL;
require_once __DIR__ . '/vendor/autoload.php';
echo microtime(true).' composer依赖载入完成！'.PHP_EOL;

// 定义目录
defined('ROOT_PATH') or define('ROOT_PATH', __DIR__);
defined('RUNTIME_PATH') or define('RUNTIME_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'runtime');
defined('TORRENT_PATH') or define('TORRENT_PATH', RUNTIME_PATH . DIRECTORY_SEPARATOR . 'torrent');
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

// 严格开发模式
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 永不超时
ini_set('max_execution_time', 0);
set_time_limit(0);

// 内存限制，如果外面设置的内存比 /etc/php/php-cli.ini 大，就不要设置了
if (intval(ini_get("memory_limit")) < 1024) {
    ini_set('memory_limit', '1024M');
}

// 限定CLI
if (PHP_SAPI != 'cli') {
    exit("You must run the CLI environment\n");
}

// 设置时区
date_default_timezone_set('Asia/Shanghai');
echo microtime(true).' 环境变量初始化完成！'.PHP_EOL.PHP_EOL;

// 命令行参数
global $argv;
