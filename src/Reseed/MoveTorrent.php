<?php
namespace IYUU\Reseed;

use Curl\Curl;
use IYUU\Client\AbstractClient;
use IYUU\Library\IFile;
use IYUU\Library\Table;
use app\domain\Reseed as domainReseed;
use app\domain\Crontab as domainCrontab;

class MoveTorrent extends AutoReseed
{
    /**
     * 客户端转移做种 格式：['客户端key', '移动参数move']
     * @var null
     */
    private static $move = null;
    /**
     * IYUUAutoReseed做种客户端转移
     */
    private static function move()
    {
        //遍历客户端
        foreach (self::$links as $k => $v) {
            if (self::$move[0] == $k) {
                echo "clients_".$k."是目标转移客户端，避免冲突，已跳过！".PHP_EOL.PHP_EOL;
                continue;
            }
            if (empty(self::$links[$k])) {
                echo "clients_".$k." 用户名或密码未配置，已跳过".PHP_EOL.PHP_EOL;
                continue;
            }
            echo "正在从下载器 clients_".$k." 获取种子哈希……".PHP_EOL;
            $move = [];     // 客户端做种列表 传址
            $hashArray = self::$links[$k]['rpc']->all($move);
            if (empty($hashArray)) {
                // 失败
                continue;
            } else {
                $infohash_Dir = $hashArray['hashString'];
                // 写日志
                wlog($hashArray, 'move'.$k);
            }
            // 前置过滤：移除转移成功的hash
            $rs = self::hashFilter($infohash_Dir);
            if ($rs) {
                echo "clients_".$k." 全部转移成功，本次无需转移！".PHP_EOL.PHP_EOL;
                continue;
            }
            //遍历当前客户端种子
            foreach ($infohash_Dir as $info_hash => $downloadDir) {
                // 调用路径过滤
                if (self::pathFilter($downloadDir)) {
                    continue;
                }
                // 做种实际路径与相对路径之间互转
                echo '转换前：'.$downloadDir.PHP_EOL;
                $downloadDir = self::pathReplace($downloadDir);
                echo '转换后：'.$downloadDir.PHP_EOL;
                if (is_null($downloadDir)) {
                    echo 'IYUU自动转移做种客户端--使用教程 https://www.iyuu.cn/archives/351/'.PHP_EOL;
                    die("全局配置的move数组内，路径转换参数配置错误，请重新配置！！！".PHP_EOL);
                }
                // 种子目录：脚本要能够读取到
                $path = self::$links[$k]['BT_backup'];
                $torrentPath = '';
                // 待删除种子
                $torrentDelete = '';
                // 获取种子原文件的实际路径
                switch ($v['type']) {
                    case 'transmission':
                        // 优先使用API提供的种子路径
                        $torrentPath = $move[$info_hash]['torrentFile'];
                        $torrentDelete = $move[$info_hash]['id'];
                        // API提供的种子路径不存在时，使用配置内指定的BT_backup路径
                        if (!is_file($torrentPath)) {
                            $torrentPath = str_replace("\\", "/", $torrentPath);
                            $torrentPath = $path . strrchr($torrentPath, '/');
                        }
                        break;
                    case 'qBittorrent':
                        if (empty($path)) {
                            echo 'IYUU自动转移做种客户端--使用教程 https://www.iyuu.cn/archives/351/'.PHP_EOL;
                            die("clients_".$k." 未设置种子的BT_backup目录，无法完成转移！");
                        }
                        $torrentPath = $path .DS. $info_hash . '.torrent';
                        $torrentDelete = $info_hash;
                        break;
                    default:
                        break;
                }
                if (!is_file($torrentPath)) {
                    echo 'IYUU自动转移做种客户端--使用教程 https://www.iyuu.cn/archives/351/'.PHP_EOL;
                    die("clients_".$k." 的种子文件{$torrentPath}不存在，无法完成转移！");
                }
                echo '存在种子：'.$torrentPath.PHP_EOL;
                $torrent = file_get_contents($torrentPath);
                // 正式开始转移
                echo "种子已推送给下载器，正在转移做种...".PHP_EOL;

                // 目标下载器类型
                $rpcKey = self::$move[0];
                $type = self::$links[$rpcKey]['type'];
                $extra_options = array();
                // 转移后，是否开始？
                $extra_options['paused'] = isset(self::$conf['default']['move']['paused']) && self::$conf['default']['move']['paused'] ? true : false;
                if ($type == 'qBittorrent') {
                    if (isset(self::$conf['default']['move']['skip_check']) && self::$conf['default']['move']['skip_check'] === 1) {
                        $extra_options['skip_checking'] = "true";    //转移成功，跳校验
                    }
                }

                // 添加转移任务：成功返回：true
                $ret = self::add(self::$move[0], $torrent, $downloadDir, $extra_options);
                /**
                 * 转移成功的种子写日志
                 */
                $log = $info_hash.PHP_EOL.$torrentPath.PHP_EOL.$downloadDir.PHP_EOL.PHP_EOL;
                if ($ret) {
                    //转移成功时，删除做种，不删资源
                    if (isset(self::$conf['default']['move']['delete_torrent']) && self::$conf['default']['move']['delete_torrent'] === 1) {
                        self::$links[$k]['rpc']->delete($torrentDelete);
                    }
                    // 转移成功的种子，以infohash为文件名，写入缓存
                    wlog($log, $info_hash, self::$cacheMove);
                    wlog($log, 'MoveSuccess'.$k);
                    self::$wechatMsg['MoveSuccess']++;
                } else {
                    // 失败的种子
                    wlog($log, 'MoveError'.$k);
                    self::$wechatMsg['MoveError']++;
                }
            }
        }
    }

    /**
     * 过滤已转移的种子hash
     * @param array $infohash_Dir       infohash与路径对应的字典
     * @return bool     true 过滤 | false 不过滤
     */
    private static function hashFilter(&$infohash_Dir = array())
    {
        foreach ($infohash_Dir as $info_hash => $dir) {
            if (is_file(self::$cacheMove . $info_hash.'.txt')) {
                unset($infohash_Dir[$info_hash]);
                echo '-------当前种子上次已成功转移，前置过滤已跳过！ ' .PHP_EOL.PHP_EOL;
            }
        }
        return empty($infohash_Dir) ? true : false;
    }

    /**
     * 实际路径与相对路径之间互相转换
     * @param string $path
     * @return string | null        string转换成功
     */
    private static function pathReplace($path = '')
    {
        $type = intval(self::$conf['default']['move']['type']);
        $pathArray = self::$conf['default']['move']['path'];
        $path = rtrim($path, DIRECTORY_SEPARATOR);      // 提高Windows转移兼容性
        switch ($type) {
            case 1:         // 减
                foreach ($pathArray as $key => $val) {
                    if (strpos($path, $key)===0) {
                        return substr($path, strlen($key));
                    }
                }
                break;
            case 2:         // 加
                foreach ($pathArray as $key => $val) {
                    if (strpos($path, $key)===0) {      // 没用$path == $key判断，是为了提高兼容性
                        return $val . $path;
                    }
                }
                break;
            case 3:         // 替换
                foreach ($pathArray as $key => $val) {
                    if (strpos($path, $key)===0) {      // 没用$path == $key判断，是为了提高兼容性
                        return $val . substr($path, strlen($key));
                    }
                }
                break;
            default:        // 不变
                return $path;
                break;
        }
        return null;
    }

    /**
     * 处理转移种子时所设置的过滤器、选择器
     * @param string $path
     * @return bool   true 过滤 | false 不过滤
     */
    private static function pathFilter(&$path = '')
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);      // 提高Windows转移兼容性
        // 转移过滤器、选择器 David/2020年7月11日
        $path_filter = !empty(self::$conf['default']['move']['path_filter']) ? self::$conf['default']['move']['path_filter'] : null;
        $path_selector = !empty(self::$conf['default']['move']['path_selector']) ? self::$conf['default']['move']['path_selector'] : null;
        if (\is_null($path_filter) && \is_null($path_selector)) {
            return false;
        }

        if (\is_null($path_filter)) {
            //选择器
            if (\is_array($path_selector)) {
                foreach ($path_selector as $pathName) {
                    if (strpos($path, $pathName)===0) {      // 没用$path == $key判断，是为了提高兼容性
                        return false;
                    }
                }
                echo '已跳过！转移选择器未匹配到：'.$path.PHP_EOL;
                return true;
            }
        } elseif (\is_null($path_selector)) {
            //过滤器
            if (\is_array($path_filter)) {
                foreach ($path_filter as $pathName) {
                    if (strpos($path, $pathName)===0) {      // 没用$path == $key判断，是为了提高兼容性
                        echo '已跳过！转移过滤器匹配到：'.$path.PHP_EOL;
                        return true;
                    }
                }
                return false;
            }
        } else {
            //同时设置过滤器、选择器
            if (\is_array($path_filter) && \is_array($path_selector)) {
                //先过滤器
                foreach ($path_filter as $pathName) {
                    if (strpos($path, $pathName)===0) {
                        echo '已跳过！转移过滤器匹配到：'.$path.PHP_EOL;
                        return true;
                    }
                }
                //后选择器
                foreach ($path_selector as $pathName) {
                    if (strpos($path, $pathName)===0) {
                        return false;
                    }
                }
                echo '已跳过！转移选择器未匹配到：'.$path.PHP_EOL;
                return true;
            }
        }
        return false;
    }
}