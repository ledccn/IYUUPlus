<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/common/ICheck.php';
use Workerman\Worker;
use Workerman\Protocols\Http;
use Workerman\Connection\TcpConnection;
use Webman\App;
use Webman\Config;
use Webman\Route;
use Webman\Middleware;
use Dotenv\Dotenv;
use support\Request;
use support\bootstrap\Log;
use support\bootstrap\Container;
use app\common\ICheck;

//检查扩展与函数
if (class_exists('app\\common\\ICheck')) {
    $currentOs = \DIRECTORY_SEPARATOR === '\\' ? 1 : 2;
    ICheck::analysis($currentOs);
} else {
    exit('Class ICheck not found'.PHP_EOL);
}
//不存在env时，复制一份
is_file(__DIR__ . DIRECTORY_SEPARATOR . '.env') || copy(__DIR__ . DIRECTORY_SEPARATOR . '.env.example', __DIR__ . DIRECTORY_SEPARATOR . '.env');

if (method_exists('Dotenv\Dotenv', 'createUnsafeImmutable')) {
    Dotenv::createUnsafeImmutable(base_path())->load();
} else {
    Dotenv::createMutable(base_path())->load();
}

Config::load(config_path(), ['route', 'container']);
$config = config('server');

if ($timezone = config('app.default_timezone')) {
    date_default_timezone_set($timezone);
}

Worker::$onMasterReload = function (){
    if (function_exists('opcache_get_status')) {
        if ($status = opcache_get_status()) {
            if (isset($status['scripts']) && $scripts = $status['scripts']) {
                foreach (array_keys($scripts) as $file) {
                    opcache_invalidate($file, true);
                }
            }
        }
    }
};
Worker::$logFile                      = $config['log_file'] ?? '';
Worker::$pidFile                      = $config['pid_file'];
Worker::$stdoutFile                   = $config['stdout_file'];
TcpConnection::$defaultMaxPackageSize = $config['max_package_size'] ?? 10*1024*1024;

$process_name = 'IYUUTask';
$config = config('process.IYUUTask', []);
$worker = new Worker($config['listen'] ?? null, $config['context'] ?? []);
$property_map = [
    'count',
    'user',
    'group',
    'reloadable',
    'reusePort',
    'transport',
    'protocol',
];
$worker->name = $process_name;
foreach ($property_map as $property) {
    if (isset($config[$property])) {
        $worker->$property = $config[$property];
    }
}

$worker->onWorkerStart = function ($worker) use ($config) {
    foreach (config('autoload.files', []) as $file) {
        include_once $file;
    }
    Dotenv::createMutable(base_path())->load();
    Config::reload(config_path(), ['route']);

    $bootstrap = $config['bootstrap'] ?? config('bootstrap', []);
    if (!in_array(support\bootstrap\Log::class, $bootstrap)) {
        $bootstrap[] = support\bootstrap\Log::class;
    }
    foreach ($bootstrap as $class_name) {
        /** @var \Webman\Bootstrap $class_name */
        $class_name::start($worker);
    }

    foreach ($config['services'] ?? [] as $server) {
        if (!class_exists($server['handler'])) {
            echo "process error: class {$server['handler']} not exists\r\n";
            continue;
        }
        $listen = new Worker($server['listen'] ?? null, $server['context'] ?? []);
        if (isset($server['listen'])) {
            echo "listen: {$server['listen']}\n";
        }
        $class = Container::make($server['handler'], $server['constructor'] ?? []);
        worker_bind($listen, $class);
        $listen->listen();
    }

    if (isset($config['handler'])) {
        if (!class_exists($config['handler'])) {
            echo "process error: class {$config['handler']} not exists\r\n";
            return;
        }

        $class = Container::make($config['handler'], $config['constructor'] ?? []);
        worker_bind($worker, $class);
    }
};

Worker::runAll();
