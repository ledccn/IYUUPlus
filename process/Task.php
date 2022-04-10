<?php
namespace process;

use Workerman\Lib\Timer;
use Workerman\Crontab\Crontab;
use app\common\Constant;
use app\common\Config as Conf;
use app\domain\Crontab as domainCron;

class Task
{
    /**
     * @var string
     */
    private static $cron_dir = '';

    /**
     * 定时任务命令缓存，当filemtime发生变化时将重新缓存，避免频繁读取磁盘
     * @var array
     */
    private static $cron_cache = array();

    /**
     * file scanner interval
     * 定时任务文件扫描间隔值介于0-60之间，保证在$interval内扫描次数大于等于2次，可根据CPU运转压力适当调整,建议值为0.9
     * @var float
     */
    private static $interval = 1;

    /**
     * 构造方法
     */
    public function __construct()
    {
        // 初始化cron
        domainCron::onWorkerStart();
        // 初始化计划任务的绝对路径
        self::$cron_dir = cron_path() . DIRECTORY_SEPARATOR . domainCron::cron_dir;
        //添加扫描器
        Timer::add(self::$interval, array($this, 'startScan'));
    }

    /**
     * 析构方法
     */
    public function __destruct()
    {
    }

    /**
     * 进程启动时执行
     */
    public function onWorkerStart()
    {
        // 每10秒执行
        new Crontab('*/10 * * * * *', function () {
            //echo date('Y-m-d H:i:s')."\n";
        });

        // 每天的10点10执行，注意这里省略了秒位
        new Crontab('10 10 * * *', function () {
            //echo date('Y-m-d H:i:s')."\n";
        });
    }

    /**
     * 启动扫描器并分析计划任务
     */
    public function startScan()
    {
        clearstatcache();
        $pattern = self::$cron_dir . '/*' .domainCron::cron_suffix;
        // 扫描任务目录
        foreach (glob($pattern) as $cron_file) {
            $filename = basename($cron_file);
            $fileMtime = filemtime($cron_file);
            // 初次读取任务文件并缓存到变量(self::$cron_cache)
            if (empty(self::$cron_cache[$filename])) {
                $this->addCache($cron_file, $filename, $fileMtime);
            }

            // 任务缓存判断
            if (self::$cron_cache[$filename]['fileMtime'] === $fileMtime) {
                // 命中缓存
            } else {
                // 未命中缓存
                echo $cron_file.'修改时间:'.date('Y-m-d H:i:s', $fileMtime).PHP_EOL;
                // 清理定时器
                $this->clearTimer($filename);
                $this->addCache($cron_file, $filename, $fileMtime);
            }
        }
        //清理计划任务缓存
        $this->clearCache();
        //cli(self::$cron_cache);
    }

    /**
     * 创建计划任务缓存
     * @param string $cron_file
     * @param string $filename
     * @param int $fileMtime
     * @return array
     */
    public function addCache(string $cron_file, string $filename, int $fileMtime):array
    {
        $cron = Conf::get($cron_file, Constant::config_format, [], true);
        self::$cron_cache[$filename] = $cron;
        self::$cron_cache[$filename]['fileMtime'] = $fileMtime;
        // 添加定时器
        $this->addTimer($cron, $filename);
        return $cron;
    }

    /**
     * 清理计划任务缓存
     */
    public function clearCache()
    {
        clearstatcache();
        foreach (self::$cron_cache as $filename => $cron) {
            $cron_file = self::$cron_dir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($cron_file)) {
                unset(self::$cron_cache[$filename]);
                // 清理定时器
                $this->clearTimer($filename);
            }
        }
    }

    /**
     * 添加定时器
     * @param array $cron
     * @param string $filename
     */
    public function addTimer(array $cron, string $filename)
    {
        new Crontab($cron['crontab'], function () use ($cron) {
            domainCron::execute($cron['command'], $cron['uuid']);
        }, $filename);
    }

    /**
     * 清理定时器
     * @param string $filename
     */
    public function clearTimer(string $filename = '')
    {
        $_instances = Crontab::getAll();    // Crontab对象数组
        /** @var Crontab $crontab */
        foreach ($_instances as $id => $crontab) {
            $name = $crontab->getName();
            // 关键条件
            if (is_string($name) && ($name === $filename)) {
                $crontab->remove($id);  //移除定时器
                return;
            }
        }
    }
}
