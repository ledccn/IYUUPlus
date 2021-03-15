<?php
namespace app\domain;

use app\common\Config as Conf;
use app\common\Constant;
use IYUU\Rss\AbstractRss;
class Rss
{
    /**
     * 根据参数解析RSS下载的运行时配置
     * @param string $uuid
     * @return array
     */
    public static function configParser($uuid = ''):array
    {
        return [];
    }

    /**
     * 获取所有RSS支持的站点名称
     * @return array
     */
    public static function getAllRssClass():array
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
            $classname = "IYUU\\Rss\\".$filename;
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
    public static function formatRssSites(array $data = []):array
    {
        $sites = Config::getSites();
        $sites = array_filter($sites, function ($k) use ($data) {
            return in_array($k, $data);
        },ARRAY_FILTER_USE_KEY);
        ksort($sites);

        return $sites;
    }
}
