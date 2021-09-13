<?php
namespace IYUU\Reseed;

use app\domain\ConfigParser\Move as domainMove;
use app\domain\Crontab as domainCrontab;

class MoveTorrent extends AutoReseed
{
    /**
     * 配置
     * @var array
     */
    protected static $conf = [];
    /**
     * RPC连接
     * @var array
     */
    protected static $links = [];
    /**
     * 客户端
     * @var array
     */
    protected static $clients = [];
    /**
     * 微信通知消息体
     * @var array
     */
    protected static $wechatMsg = array(
        'MoveSuccess'       =>  0,      // 移动成功
        'MoveError'         =>  0,      // 移动失败
    );
    /**
     * 初始化
     */
    public static function init()
    {
        self::getCliInput();
        // 连接全局客户端
        parent::links();
    }

    /**
     * 解析命令行参数
     */
    protected static function getCliInput()
    {
        // 命令行参数
        global $argv;
        $cron_name = isset($argv[1]) ? $argv[1] : null;
        is_null($cron_name) and die('缺少命令行参数。');
        self::$conf = domainMove::parser($cron_name);
        // 用户选择的下载器
        self::$clients = self::$conf['clients'];
        echo microtime(true).' 命令行参数解析完成！'.PHP_EOL;
        //cli(self::$conf);//exit;
    }

    /**
     * 转移，总入口
     */
    public static function call()
    {
        self::move();
        self::wechatMessage();
        exit(self::$ExitCode);
    }

    /**
     * IYUUAutoReseed做种客户端转移
     */
    private static function move()
    {
        //遍历客户端
        foreach (self::$links as $k => $v) {
            if ($k === self::$conf['to_clients']['uuid']) {
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
                static::wLog($hashArray, 'move'.$k);
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
                    die("路径转换参数配置错误，请重新配置！！！".PHP_EOL);
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
                $rpcKey = self::$conf['to_clients']['uuid'];
                $type = self::$links[$rpcKey]['type'];
                $extra_options = array();
                // 转移后，是否开始？
                $extra_options['paused'] = isset(self::$conf['paused']) && self::$conf['paused'] ? true : false;
                if ($type == 'qBittorrent') {
                    if (isset(self::$conf['skip_check']) && self::$conf['skip_check']) {
                        $extra_options['skip_checking'] = "true";    //转移成功，跳校验
                    }
                }

                // 添加转移任务：成功返回：true
                $ret = self::add($rpcKey, $torrent, $downloadDir, $extra_options);
                /**
                 * 转移成功的种子写日志
                 */
                $log = $info_hash.PHP_EOL.$torrentPath.PHP_EOL.$downloadDir.PHP_EOL.PHP_EOL;
                if ($ret) {
                    //转移成功时，删除做种，不删资源
                    if (isset(self::$conf['delete_torrent']) && self::$conf['delete_torrent']) {
                        self::$links[$k]['rpc']->delete($torrentDelete);
                    }
                    // 转移成功的种子，以infohash为文件名，写入缓存
                    static::wLog($log, $info_hash, self::$cacheMove);
                    static::wLog($log, 'MoveSuccess'.$k);
                    static::$wechatMsg['MoveSuccess']++;
                } else {
                    // 失败的种子
                    static::wLog($log, 'MoveError'.$k);
                    static::$wechatMsg['MoveError']++;
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
     * 处理转移种子时所设置的过滤器、选择器
     * @param string $path
     * @return bool   true 过滤 | false 不过滤
     */
    private static function pathFilter(&$path = '')
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);      // 提高Windows转移兼容性
        // 转移过滤器、选择器 David/2020年7月11日
        $path_filter = !empty(self::$conf['path_filter']) ? self::$conf['path_filter'] : null;
        $path_selector = !empty(self::$conf['path_selector']) ? self::$conf['path_selector'] : null;
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

    /**
     * 实际路径与相对路径之间互相转换
     * @param string $path
     * @return string | null        string转换成功
     */
    private static function pathReplace($path = '')
    {
        $type = intval(self::$conf['path_type']);
        $pathArray = self::$conf['path_rule'];
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
     * 微信模板消息拼接方法
     * @return string           发送情况，json
     */
    protected static function wechatMessage()
    {
        $br = PHP_EOL;
        $text = 'IYUU自动辅种-统计报表';
        $desp = '### 版本号：'. IYUU_VERSION() . $br;
        // 移动做种
        if (static::$wechatMsg['MoveSuccess'] || static::$wechatMsg['MoveError']) {
            $desp .= $br.'----------'.$br;
            $desp .= '**移动成功：'.static::$wechatMsg['MoveSuccess']. '**  [会把hash加入移动缓存]' .$br;
            $desp .= '**移动失败：'.static::$wechatMsg['MoveError']. '**  [解决错误提示，可以重试]' .$br;
            $desp .= '**如需重新移动，请删除 ./torrent/cachemove 移动缓存。**'.$br;
        }
        $desp .= $br.'*此消息将在3天后过期*。';
        return static::ff($text, $desp);
    }
}
