<?php

namespace app\domain\ConfigParser;

use app\domain\ConfigParserInterface;
use IYUU\Spiders\SitesBase;

class Spiders implements ConfigParserInterface
{
    /**
     * 根据参数解析免费种爬虫下载的运行时配置
     * @param string $uuid
     * @return array
     */
    public static function parser(string $uuid = ''): array
    {
        return Rss::parser($uuid);
    }

    /**
     * 获取所有RSS支持的站点名称
     * @descr 步骤：1.获取Rss目录下的全部类文件名； 2.实例化类为对象； 3.获取对象的成员变量site
     * @return array
     */
    public static function getAllSpidersClass(): array
    {
        $data = [];
        //排除的类
        $filter = ['SitesBase'];
        $pattern = base_path() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Spiders' . DIRECTORY_SEPARATOR . '*.php';
        foreach (glob($pattern) as $key => $file) {
            $filename = basename($file, '.php');
            if (in_array($filename, $filter)) {
                continue;
            }
            $classname = "IYUU\\Spiders\\" . $filename;
            if (class_exists($classname)) {
                $data[] = SitesBase::getSiteName($filename);
            }
        }
        sort($data);

        return $data;
    }

    /**
     * 格式化站点完整信息
     * @param array $data
     * @return array
     */
    public static function formatSites(array $data = []): array
    {
        return Rss::formatSites($data);
    }
}
