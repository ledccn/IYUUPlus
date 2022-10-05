<?php

namespace app\domain\ConfigParser;

use app\domain\ConfigParserInterface;
use app\domain\Config;
use IYUU\Library\IFile;

/**
 * 辅种相关
 * Class Reseed
 * @access private 常驻内存运行，禁止执行器调用
 * @package app\domain
 */
class Reseed implements ConfigParserInterface
{
    /**
     * 根据参数，解析辅种的站点和下载器
     * @param string $uuid
     * @return array
     */
    public static function parser(string $uuid = ''): array
    {
        $rs = [
            'sites' => [],
            'clients' => [],
        ];
        if (empty($uuid)) {
            return $rs;
        }
        $cron = Config::getCronByUUID($uuid);

        //IYUU密钥
        $iyuu = Config::getIyuu();
        $rs['iyuu.cn'] = $iyuu['iyuu.cn'];

        //默认
        $rs['default'] = Config::getDefault();

        //微信通知
        $rs['weixin'] = Config::getWeixin();

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

        //其他参数
        return array_merge($cron, $rs);
    }

    /**
     * 清理辅种缓存
     * @return bool
     */
    public static function clearReseedCache(): bool
    {
        $dir = self::getReseedCachePath();
        return !is_dir($dir) || IFile::rmdir($dir, true);
    }

    /**
     * 获取辅种缓存的存放路径
     * @return string
     */
    public static function getReseedCachePath(): string
    {
        return runtime_path() . DIRECTORY_SEPARATOR . 'torrent' . DIRECTORY_SEPARATOR . 'cachehash';
    }

    /**
     * 清理转移缓存
     * @return bool
     */
    public static function clearMoveCache(): bool
    {
        $dir = self::getMoveCachePath();
        return !is_dir($dir) || IFile::rmdir($dir, true);
    }

    /**
     * 获取转移缓存的存放路径
     * @return string
     */
    public static function getMoveCachePath(): string
    {
        return runtime_path() . DIRECTORY_SEPARATOR . 'torrent' . DIRECTORY_SEPARATOR . 'cachemove';
    }
}
