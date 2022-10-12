<?php

namespace app\domain;

use app\common\Config as Conf;
use app\common\Constant;

/**
 * 计划任务相关
 * @access private 常驻内存运行，禁止执行器调用
 */
class Crontab
{
    // 定时任务目录
    const cron_dir = 'cron_dir';

    // 任务锁目录
    const lock_dir = 'lock_dir';

    // 任务日志记录目录
    const log_dir = 'log_dir';

    // 任务执行进程目录
    const pid_dir = 'pid_dir';

    // 任务运行状态目录
    const run_dir = 'run_dir';

    // 定时任务文件名后缀
    const cron_suffix = '.crontab';

    // 任务锁后缀
    const lock_suffix = '.lock';

    // 任务进程后缀
    const pid_suffix = '.pid';

    /**
     * linux系统的crontab任务永远在第1秒执行,且添加定时任务后的1分钟之内是不会执行该任务(即使语法上完全满足)
     * @var string
     */
    const cron_minute = '%s %s %s %s %s';

    /**
     * @var string
     */
    const cron_second = '%s %s %s %s %s %s';
    /**
     * where可能的值
     */
    const WHERE = [
        'day', 'day_n', 'hour', 'hour_n', 'minute', 'minute_n', 'second', 'second_n', 'week', 'month'
    ];
    // 管理员用户名,用户名密码都为空字符串时说明不用验证
    public static $adminName = '';

    // 管理员密码,用户名密码都为空字符串时说明不用验证
    public static $adminPassword = '';

    /**
     * 构造方法
     */
    public function __construct()
    {
    }

    /**
     * 进程启动时执行
     */
    public static function onWorkerStart()
    {
        // 初始化目录
        $sys_dir = [self::cron_dir, self::run_dir, self::pid_dir, self::lock_dir, self::log_dir];
        array_walk($sys_dir, function ($v, $k) {
            $dir = cron_path() . DIRECTORY_SEPARATOR . $v;
            is_dir($dir) or mkdir($dir, 0777, true);
        });

        // 初始化计划任务文件[不同平台的配置，会造成command错误，需要重新解析命令]
        $cron = Config::getCrontab();
        array_walk($cron, function ($v, $k) {
            self::createHock($v);
        });
    }

    /**
     * 创建计划任务 钩子
     * @param array $param
     */
    public static function createHock(array &$param)
    {
        $param['startTime'] = !empty($param['startTime']) ? $param['startTime'] : time();
        $param['crontab'] = !empty($param['crontab']) ? $param['crontab'] : self::parseCron($param);
        $param['command'] = self::parseCommand($param);
        if (isset($param['switch']) && booleanParse($param['switch'])) {
            $filename = self::getFilePath($param['uuid'], self::cron_dir, self::cron_suffix);
            self::writeCronFile($filename, $param);
        } else {
            self::deleteHock($param['uuid']);
        }
    }

    /**
     * 转换为Linux的Crontab语法
     * @param array $param 数据
     * array(
     *      'where' => ''
     *      'weeks' => ''
     *      'day' => ''
     *      'hour' => ''
     *      'minute' => ''
     * )
     * @return string
     *   0    1    2    3    4    5
     *   *    *    *    *    *    *
     *   -    -    -    -    -    -
     *   |    |    |    |    |    |
     *   |    |    |    |    |    +----- day of week (0 - 6) (Sunday=0)
     *   |    |    |    |    +----- month (1 - 12)
     *   |    |    |    +------- day of month (1 - 31)
     *   |    |    +--------- hour (0 - 23)
     *   |    +----------- min (0 - 59)
     *   +------------- sec (0-59)
     */
    public static function parseCron(array $param): string
    {
        $cron = '';
        $where = $param['where'] ?? null;       //条件
        $weeks = $param['weeks'] ?? null;       //星期
        $day = $param['day'] ?? null;         //天
        $hour = $param['hour'] ?? null;        //时
        $minute = $param['minute'] ?? null;      //分
        $second = $param['second'] ?? '*';       //秒
        if ($where === null || !in_array($where, self::WHERE)) {
            throw new \InvalidArgumentException('Invalid cron param where');
        }

        //TODO：参数验证

        switch ($where) {
            case 'day':         //每天
                $cron = sprintf(self::cron_minute, $minute, $hour, '*', '*', '*');
                break;
            case 'day_n':       //N天
                $cron = sprintf(self::cron_minute, $minute, $hour, '*/' . $day, '*', '*');
                break;
            case 'hour':        //每小时
                $cron = sprintf(self::cron_minute, $minute, '*', '*', '*', '*');
                break;
            case 'hour_n':      //N小时
                $cron = sprintf(self::cron_minute, $minute, '*/' . $hour, '*', '*', '*');
                break;
            case 'minute':      //每分钟
                $cron = sprintf(self::cron_minute, '*', '*', '*', '*', '*');
                break;
            case 'minute_n':    //N分钟
                $cron = sprintf(self::cron_minute, '*/' . $minute, '*', '*', '*', '*');
                break;
            case 'second':      //每秒
                $cron = sprintf(self::cron_second, '*', '*', '*', '*', '*', '*');
                break;
            case 'second_n':    //N秒
                $cron = sprintf(self::cron_second, '*/' . $second, '*', '*', '*', '*', '*');
                break;
            case 'week':        //每周
                $cron = sprintf(self::cron_minute, $minute, $hour, '*', '*', $weeks);
                break;
            case 'month':       //每月
                $cron = sprintf(self::cron_minute, $minute, $hour, '*', $day, '*');
                break;
        }

        return $cron;
    }

    /**
     * 解析计划任务命令
     * @param array $param
     * @return string
     */
    public static function parseCommand(array $param): string
    {
        return Command::parse($param);
    }

    /**
     * 获取文件路径
     * @param string $filename 文件名
     * @param string $dir 子目录
     * @param string $suffix 扩展名
     * @return string           文件的完整路径
     */
    public static function getFilePath(string $filename = '', string $dir = 'cron_dir', string $suffix = '.crontab'): string
    {
        clearstatcache();
        $_dir = cron_path() . DIRECTORY_SEPARATOR . $dir;
        is_dir($_dir) or mkdir($_dir, 0777, true);

        return $_dir . DIRECTORY_SEPARATOR . $filename . $suffix;
    }

    /**
     * 创建计划任务文件
     * @param string $filename 文件的完整路径
     * @param mixed $param 数据
     * @return bool|int             结果
     */
    public static function writeCronFile(string $filename, $param)
    {
        return Conf::set($filename, $param, 'json', true);
    }

    /**
     * 删除计划任务 钩子
     * @param string $uuid uuid或文件名
     * @return bool
     */
    public static function deleteHock(string $uuid): bool
    {
        if (empty($uuid)) {
            return false;
        }
        $file_name = self::getFilePath($uuid, self::cron_dir, self::cron_suffix);
        clearstatcache();
        if (is_file($file_name)) {
            return @unlink($file_name);
        }
        return true;
    }

    /**
     * 根据任务名字，拼接lock文件路径
     * @param string $filename
     * @return string
     */
    public static function getLockFile(string $filename = ''): string
    {
        return self::getFilePath($filename, self::lock_dir, self::lock_suffix);
    }

    /**
     * 根据任务名字，拼接pid文件路径
     * @param string $filename
     * @return string
     */
    public static function getPidFile(string $filename = ''): string
    {
        return self::getFilePath($filename, self::pid_dir, self::pid_suffix);
    }

    /**
     * 根据uuid运行任务
     * @param string $uuid
     * @return bool
     */
    public static function runCron(string $uuid = ''): bool
    {
        $cronFilename = Config::filename['crontab'];
        $cronAll = Conf::get($cronFilename, Constant::config_format, []);
        $cron = array_key_exists($uuid, $cronAll) ? $cronAll[$uuid] : [];
        self::execute($cron['command'], $cron['uuid']);
        return true;
    }

    /**
     * 异步执行命令
     * @descr 原理为php的程序执行函数后台执行
     * @param string $cmd 任务执行的命令
     * @param string $uuid 任务的UUID，通常作为唯一的日志文件名
     */
    public static function execute(string $cmd = '', string $uuid = '')
    {
        $logFile = self::getLogFile($uuid);
        // 清理上次的日志
        self::clearLogs($uuid);
        // 运行命令
        if (DIRECTORY_SEPARATOR === '\\') {
            pclose(popen('start /B ' . $cmd . ' >> ' . $logFile, 'r'));
        } else {
            pclose(popen($cmd . ' >> ' . $logFile . ' 2>&1 &', 'r'));
            //exec($cmd.' >> '.$logFile.' 2>&1 &');
        }
    }

    /**
     * 拼接任务log文件的完整路径
     * @param string $filename
     * @return string
     */
    public static function getLogFile(string $filename = ''): string
    {
        return self::getFilePath($filename, self::log_dir, '.log');
    }

    /**
     * 清空计划任务日志
     * @param string $uuid
     * @return bool
     */
    public static function clearLogs(string $uuid = ''): bool
    {
        $logFile = self::getLogFile($uuid);
        $ret = Conf::set($logFile, date('Y-m-d H:i:s') . ' 清理日志' . PHP_EOL, 'raw', true);
        return is_bool($ret) ? $ret : ($ret >= 10);
    }

    /**
     * 读取计划任务的日志
     * @param string $uuid
     * @param int $last_line_number
     * @return string
     */
    public static function readLogs(string $uuid = '', int $last_line_number = 0): string
    {
        $logFile = self::getLogFile($uuid);
        if ($last_line_number) {
            $log = fileLastLines($logFile, $last_line_number);
        } else {
            $log = Conf::get($logFile, 'raw', '', true);
        }
        return $log;
    }

    /**
     * 清理所有计划任务的日志
     * @return int
     */
    public static function clearAllLogs(): int
    {
        $cron = Config::getCrontab();
        $count = 0;
        array_walk($cron, function ($v, $uuid) use (&$count) {
            if (static::clearLogs($uuid)) {
                $count++;
            }
        });

        return $count;
    }
}
