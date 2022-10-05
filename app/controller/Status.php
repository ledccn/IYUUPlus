<?php

namespace app\controller;

use support\Request;
use support\Response;
use app\common\Config as Conf;
use app\domain\Config;
use IYUU\Reseed\AutoReseed;

/**
 * Class Status
 * @package app\controller
 */
class Status extends BaseController
{
    /**
     * 系统日志
     * @param Request $request
     * @return Response
     */
    public function syslog(Request $request): Response
    {
        $rs = self::RS;
        $config = config('server');
        $log_file = Conf::get($config['log_file'], 'raw', '', true);
        $stdout_file = Conf::get($config['stdout_file'], 'raw', '', true);
        $rs['data'] = [
            'log_file' => fileLastLines($config['log_file'], 100),   //$log_file,
            'stdout_file' => $stdout_file,
        ];
        return json($rs);
    }

    /**
     * 辅种成功日志、辅种失败日志
     * @param Request $request
     * @return Response
     */
    public function reseedLog(Request $request): Response
    {
        $rs = self::RS;
        $key = $request->get('id', 'success');
        if ($key === 'success') {
            $log = Conf::get(AutoReseed::$cacheDir . 'reseedSuccess.txt', 'raw', '', true);
        } else {
            $log = Conf::get(AutoReseed::$cacheDir . 'reseedError.txt', 'raw', '', true);
        }
        $rs['data'] = [
            $key => $log
        ];

        return json($rs);
    }

    /**
     * 欢迎页
     * @param Request $request
     * @return Response
     */
    public function welcome(Request $request): Response
    {
        $rs = self::RS;
        $cron = Config::getCrontab();
        $user_sites = Config::getUserSites();
        $sites = Config::getSites();
        $clients = Config::getClients();
        $version = 'v' . IYUU_VERSION();
        $system_info = sprintf('%s / %s', PHP_OS, PHP_OS_FAMILY);
        //读取git信息
        $updated_at = get_current_git_filemtime() . (get_current_git_commit() ? ' (' . get_current_git_commit() . ')' : '');
        $updated_at = strlen($updated_at) > 10 ? $updated_at : '点此查看';
        // 磁盘容量、可用容量
        $disk_total_space = disk_total_space(db_path());
        $disk_free_space = disk_free_space(db_path());
        $disk_total = \sprintf('可用：%s（总容量：%s）', \dataSize($disk_free_space), \dataSize($disk_total_space));
        $rs['data'] = [
            'cron_total' => count($cron),
            'sites_total' => count($user_sites),
            'sites_count' => count($sites),
            'clients_total' => count($clients),
            'project' => IYUU_PROJECT_NAME(),
            'version' => $version,
            'updated_at' => $updated_at,
            'system_info' => $system_info,
            'PHP_VERSION' => PHP_VERSION,
            'PHP_BINARY' => PHP_BINARY,
            'RUNTIME_PATH' => runtime_path(),
            'disk_total' => $disk_total,
        ];
        return json($rs);
    }
}
