<?php
require_once __DIR__ . '/app/common/ICheck.php';

use app\common\ICheck;

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

// 设置时区
date_default_timezone_set('Asia/Shanghai');
echo microtime(true) . ' 当前时间：' . date('Y-m-d H:i:s') . PHP_EOL;
echo microtime(true) . ' 当前操作系统：' . PHP_OS . PHP_EOL;
echo microtime(true) . ' 当前运行环境：' . PHP_OS_FAMILY . PHP_EOL;
echo microtime(true) . ' 当前接口类型：' . PHP_SAPI . PHP_EOL;
echo microtime(true) . ' PHP二进制文件：' . PHP_BINARY . PHP_EOL;
echo microtime(true) . ' PHP版本号：' . PHP_VERSION . PHP_EOL;

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

echo microtime(true) . ' 环境变量初始化完成！' . PHP_EOL;

//检查扩展与函数
if (class_exists('app\\common\\ICheck')) {
    $currentOs = \DIRECTORY_SEPARATOR === '\\' ? 1 : 2;
    ICheck::analysis($currentOs);
} else {
    exit('Class ICheck not found' . PHP_EOL);
}

//不存在env时，复制一份
is_file(__DIR__ . DIRECTORY_SEPARATOR . '.env') || copy(__DIR__ . DIRECTORY_SEPARATOR . '.env.example', __DIR__ . DIRECTORY_SEPARATOR . '.env');
//检查db_path目录
is_dir(db_path()) or mkdir(db_path(), 0777, true);
is_writable(db_path()) or exit('错误：' . db_path() . '目录无写入权限，程序终止！');

echo microtime(true) . '  检查配置，是否同时监听IPv6...' . PHP_EOL;
$default_config_file = db_path() . '/default.json';
if (is_file($default_config_file)) {
    $default_config = file_get_contents($default_config_file);
    $conf = json_decode($default_config, true);
    if (isset($conf['listen_ipv6'])) {
        define('IYUU_LISTEN_IPV6', true);
        echo microtime(true) . '  您设置同时监听IPv6，Windows系统本机访问URL为http://localhost:8787' . PHP_EOL;
    } else {
        echo microtime(true) . '  未监听IPv6，如果您有公网IPv6地址，可以打开监听[ IYUUPlus -> 系统设置 -> 常规设置 -> 监听IPv6 ]' . PHP_EOL;
    }
} else {
    echo microtime(true) . '  未检测到常规配置JSON文件。' . PHP_EOL;
}

// 命令行参数
global $argv;
