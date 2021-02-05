<?php
namespace app\domain;

use app\common\Config as Conf;
use app\common\Constant;
/**
 * 辅种相关
 * Class Reseed
 * @access private 常驻内存运行，禁止执行器调用
 * @package app\domain
 */
class Reseed
{
    /**
     * 根据参数，解析辅种的站点和下载器
     * @param string $uuid
     * @return array
     */
    public static function configParser($uuid = ''):array
    {
        $rs = [
            'sites'   => [],
            'clients' => [],
        ];

        $cronFilename = Config::filename['crontab'];
        $cron = Conf::get($cronFilename, Constant::config_format, []);
        $cron = array_key_exists($uuid, $cron) ? $cron[$uuid] : [];
        //检查使能
        if (isset($cron['switch']) && $cron['switch'] === 'on') {
            //IYUU密钥
            $iyuu = Config::getIyuu();
            $rs['iyuu.cn'] = $iyuu['iyuu.cn'];

            //默认
            $default = Config::getDefault();
            $rs['default'] = $default;

            //解析站点
            $sites = Config::getUserSites();
            if (!empty($cron['sites']) && !empty($sites)) {
                $key = $cron['sites'];
                $rs['sites'] = array_filter($sites, function ($v, $k) use ($key) {
                    return array_key_exists($k, $key);
                }, ARRAY_FILTER_USE_BOTH);
            }

            //解析下载器
            $clients = Config::getClients();
            if (!empty($cron['clients']) && !empty($clients)) {
                $key = $cron['clients'];
                $rs['clients'] = array_filter($clients, function ($k) use ($key) {
                    return array_key_exists($k, $key);
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        return $rs;
    }
}
