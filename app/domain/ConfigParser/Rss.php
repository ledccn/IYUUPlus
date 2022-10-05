<?php

namespace app\domain\ConfigParser;

use app\domain\ConfigParserInterface;
use app\domain\Config;

class Rss implements ConfigParserInterface
{
    /**
     * 根据参数解析RSS下载的运行时配置
     * @param string $uuid
     * @return array
     */
    public static function parser(string $uuid = ''): array
    {
        $rs = [
            'site' => [],
            'sites' => [],
            'clients' => [],
            'filter' => [],
        ];
        if (empty($uuid)) {
            return $rs;
        }
        $cron = Config::getCronByUUID($uuid);

        //IYUU密钥
        $iyuu = Config::getIyuu();
        $rs['iyuu.cn'] = $iyuu['iyuu.cn'];

        //默认
        $default = Config::getDefault();
        $rs['default'] = $default;

        //解析用户的站点配置
        $site = 'site';
        $userSites = Config::getUserSites();
        if (!empty($cron[$site]) && !empty($userSites)) {
            $key = $cron[$site];
            $rs['site'] = array_key_exists($key, $userSites) ? $userSites[$key] : [];
        }

        //解析站点域名
        $sites = Config::getSites();
        if (!empty($cron[$site]) && !empty($sites)) {
            $key = $cron[$site];
            $rs['sites'] = array_key_exists($key, $sites) ? $sites[$key] : [];
        }

        //解析下载器
        $clients = Config::getClients();
        if (!empty($cron['clients']) && !empty($clients)) {
            $key = $cron['clients'];
            $rs['clients'] = array_key_exists($key, $clients) ? $clients[$key] : [];
        }

        //解析筛选规则的过滤器
        $filter = Config::getFilter();
        if (!empty($cron['filter']) && !empty($filter)) {
            $key = $cron['filter'];
            $rs['filter'] = array_key_exists($key, $filter) ? $filter[$key] : [];
        }

        //其他参数
        return array_merge($cron, $rs);
    }

    /**
     * 获取所有RSS支持的站点名称
     * @descr 步骤：1.获取Rss目录下的全部类文件名； 2.实例化类为对象； 3.获取对象的成员变量site
     * @return array
     */
    public static function getAllRssClass(): array
    {
        $data = [];
        //排除的类
        $filter = ['AbstractRss'];
        $pattern = base_path() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Rss' . DIRECTORY_SEPARATOR . '*.php';
        foreach (glob($pattern) as $key => $file) {
            $filename = basename($file, '.php');
            if (in_array($filename, $filter)) {
                continue;
            }
            $classname = "IYUU\\Rss\\" . $filename;
            if (class_exists($classname)) {
                $obj = new $classname(false);
                $data[] = $obj->site;
            }
        }
        sort($data);

        return $data;
    }

    /**
     * 格式化RSS站点完整信息
     * @param array $data
     * @return array
     */
    public static function formatSites(array $data = []): array
    {
        $sites = Config::getSites();
        $sites = array_filter($sites, function ($k) use ($data) {
            return in_array($k, $data);
        }, ARRAY_FILTER_USE_KEY);
        ksort($sites);

        return $sites;
    }
}
