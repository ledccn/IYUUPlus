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
            $iyuu = self::iyuuConfig();
            $rs['iyuu.cn'] = $iyuu['iyuu.cn'];

            //默认
            $default = self::defaultConfig();
            $rs['default'] = $default;

            //解析站点
            $sites = self::userSitesConfig();
            if (!empty($cron['sites']) && !empty($sites)) {
                $key = $cron['sites'];
                $rs['sites'] = array_filter($sites, function ($v, $k) use ($key) {
                    return array_key_exists($k, $key);
                }, ARRAY_FILTER_USE_BOTH);
            }

            //解析下载器
            $clients = self::clientsConfig();
            if (!empty($cron['clients']) && !empty($clients)) {
                $key = $cron['clients'];
                $rs['clients'] = array_filter($clients, function ($k) use ($key) {
                    return array_key_exists($k, $key);
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        return $rs;
    }

    /**
     * IYUU密钥
     * @return array
     */
    public static function iyuuConfig():array
    {
        return Conf::get(Config::filename['iyuu'], Constant::config_format, []);
    }

    /**
     * 默认配置
     * @return array
     */
    public static function defaultConfig():array
    {
        return Conf::get(Config::filename['default'], Constant::config_format, []);
    }

    /**
     * 客户端
     * @return array
     */
    public static function clientsConfig():array
    {
        return Conf::get(Config::filename['clients'], Constant::config_format, []);
    }

    /**
     * 用户拥有的站点
     * @return array
     */
    public static function userSitesConfig():array
    {
        return Conf::get(Config::filename['user_sites'], Constant::config_format, []);
    }
}
