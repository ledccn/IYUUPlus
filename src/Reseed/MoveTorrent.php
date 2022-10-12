<?php

namespace IYUU\Reseed;

use app\domain\ConfigParser\Move as domainMove;
use IYUU\Client\ClientException;
use IYUU\Notify\NotifyFactory;
use Rhilip\Bencode\Bencode;
use Rhilip\Bencode\ParseException;

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
    protected static $notifyMsg = array(
        'MoveSuccess' => 0,      // 移动成功
        'MoveError' => 0,      // 移动失败
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
        global $argv;
        $cron_name = $argv[1] ?? null;
        is_null($cron_name) and die('缺少命令行参数。');
        self::$conf = domainMove::parser($cron_name);
        // 用户选择的下载器
        self::$clients = self::$conf['clients'];
        // 获取通知渠道
        self::$notify = NotifyFactory::get(self::$conf['notify']['channel'] ?? '');
        echo microtime(true) . ' 命令行参数解析完成！' . PHP_EOL;
    }

    /**
     * 转移，总入口
     * @return void
     * @throws ClientException
     */
    public static function call()
    {
        self::move();
        self::job_done_notify();
        exit(self::$ExitCode);
    }

    /**
     * IYUUAutoReseed做种客户端转移
     * @throws ClientException
     */
    private static function move()
    {
        //遍历客户端
        foreach (self::$links as $k => $v) {
            if ($k === self::$conf['to_clients']['uuid']) {
                echo "clients_" . $k . "是目标转移客户端，避免冲突，已跳过！" . PHP_EOL . PHP_EOL;
                continue;
            }
            if (empty(self::$links[$k])) {
                echo "clients_" . $k . " 用户名或密码未配置，已跳过" . PHP_EOL . PHP_EOL;
                continue;
            }
            echo "正在从下载器 clients_" . $k . " 获取种子哈希……" . PHP_EOL;
            $move = [];     // 客户端做种列表 传址
            $hashArray = static::getRpc($k)->all($move);
            if (empty($hashArray)) {
                // 失败
                continue;
            } else {
                $infohash_Dir = $hashArray['hashString'];
                // 写日志
                static::wLog($hashArray, 'move' . $k);
            }
            // 前置过滤：移除转移成功的hash
            $rs = self::hashFilter($infohash_Dir);
            if ($rs) {
                echo "clients_" . $k . " 全部转移成功，本次无需转移！" . PHP_EOL . PHP_EOL;
                continue;
            }
            $qBittorrent_version_lg_4_4 = false;
            if ($v['type'] === 'qBittorrent') {
                $version = $v['version'];
                $arr = explode('.', ltrim($version, "v"), 3);
                if (count($arr) > 2 && ($arr[0] == '4' && $arr[1] >= '4' || $arr[0] > '4')) {
                    global $qBittorrent_version_lg_4_4;
                    $qBittorrent_version_lg_4_4 = true;
                }
            }
            //遍历当前客户端种子
            foreach ($infohash_Dir as $info_hash => $downloadDir) {
                // 调用路径过滤器、选择器
                if (self::pathFilter($downloadDir)) {
                    continue;
                }
                // 做种实际路径与相对路径之间互转
                echo '转换前：' . $downloadDir . PHP_EOL;
                $downloadDir = self::pathReplace($downloadDir);
                echo '转换后：' . $downloadDir . PHP_EOL;
                $help_msg = 'IYUU自动转移做种客户端--使用教程' . PHP_EOL . 'https://www.iyuu.cn/archives/451/' . PHP_EOL . 'https://www.iyuu.cn/archives/465/' . PHP_EOL;
                if (is_null($downloadDir)) {
                    echo $help_msg;
                    die("路径转换参数配置错误，请重新配置！！！" . PHP_EOL);
                }
                // 种子目录：脚本要能够读取到
                $path = self::$links[$k]['BT_backup'];
                $torrentPath = '';
                $fast_resumePath = '';
                $needPatchTorrent = $qBittorrent_version_lg_4_4;
                // 待删除种子
                $torrentDelete = '';
                // 获取种子文件的实际路径
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
                        // 再次检查
                        if (!is_file($torrentPath)) {
                            echo $help_msg;
                            die("clients_" . $k . " 的`{$move[$info_hash]['name']}`，种子文件`{$torrentPath}`不存在，无法完成转移！");
                        }
                        break;
                    case 'qBittorrent':
                        if (empty($path)) {
                            echo $help_msg;
                            die("clients_" . $k . " IYUUPlus内下载器未设置种子目录，无法完成转移！");
                        }
                        $torrentPath = $path . DS . $info_hash . '.torrent';
                        $fast_resumePath = $path . DS . $info_hash . '.fastresume';
                        $torrentDelete = $info_hash;

                        // 再次检查
                        if (!is_file($torrentPath)) {
                            //先检查是否为空
                            $infohash_v1 = $move[$info_hash]['infohash_v1'] ?? '';
                            if (empty($infohash_v1)) {
                                echo $help_msg;
                                die("clients_" . $k . " 的`{$move[$info_hash]['name']}`，种子文件{$torrentPath}不存在，infohash_v1为空，无法完成转移！");
                            }

                            //高版本qb下载器，infohash_v1
                            $v1_path = $path . DS . $infohash_v1 . '.torrent';
                            if (is_file($v1_path)) {
                                $torrentPath = $v1_path;
                                $fast_resumePath = $path . DS . $infohash_v1 . '.torrent';
                            } else {
                                echo $help_msg;
                                die("clients_" . $k . " 的`{$move[$info_hash]['name']}`，种子文件`{$torrentPath}`不存在，无法完成转移！");
                            }
                        }
                        break;
                    default:
                        break;
                }

                //读取种子源文件
                echo '存在种子：' . $torrentPath . PHP_EOL;
                $torrent = file_get_contents($torrentPath);
                $parsed_torrent = [];
                try {
                    global $parsed_torrent;
                    $parsed_torrent = Bencode::decode($torrent);
                    if (empty($parsed_torrent['announce'])) {
                        $needPatchTorrent = true;
                    }
                } catch (ParseException $e) {
                }
                if ($needPatchTorrent) {
                    echo '未发现tracker信息，尝试补充tracker信息...' . PHP_EOL;
                    if (empty($parsed_torrent)) {
                        die("clients_" . $k . " 的`{$move[$info_hash]['name']}`，种子文件`{$torrentPath}`解析失败，无法完成转移！");
                    }
                    if (empty($parsed_torrent['announce'])) {
                        if (!empty($move[$info_hash]['tracker'])) {
                            $parsed_torrent['announce'] = $move[$info_hash]['tracker'];
                        } else {
                            if (!is_file($fast_resumePath)) {
                                echo $help_msg;
                                die("clients_" . $k . " 的`{$move[$info_hash]['name']}`，resume文件`{$fast_resumePath}`不存在，无法完成转移！");
                            }
                            $parsed_fast_resume = null;
                            try {
                                global $parsed_fast_resume;
                                $parsed_fast_resume = Bencode::load($fast_resumePath);
                            } catch (ParseException $e) {
                                die("clients_" . $k . " 的`{$move[$info_hash]['name']}`，resume文件`{$fast_resumePath}`解析失败`{$e->getMessage()}`，无法完成转移！");
                            }
                            $trackers = $parsed_fast_resume['trackers'];
                            if (count($trackers) > 0 && !empty($trackers[0])) {
                                if (is_array($trackers[0]) && count($trackers[0]) > 0 && !empty($trackers[0][0])) {
                                    $parsed_torrent['announce'] = $trackers[0][0];
                                }
                            } else {
                                die("clients_" . $k . " 的`{$move[$info_hash]['name']}`，resume文件`{$fast_resumePath}`不包含tracker地址，无法完成转移！");
                            }
                        }
                    }
                    $torrent = Bencode::encode($parsed_torrent);
                }
                // 正式开始转移
                echo "种子已推送给下载器，正在转移做种..." . PHP_EOL;

                // 目标下载器类型
                $clientKey = self::$conf['to_clients']['uuid'];
                $type = self::$links[$clientKey]['type'];
                $extra_options = array();
                // 转移后，是否开始？
                $extra_options['paused'] = isset(self::$conf['paused']) && self::$conf['paused'];
                if ($type == 'qBittorrent') {
                    if (isset(self::$conf['skip_check']) && self::$conf['skip_check']) {
                        $extra_options['skip_checking'] = "true";    //转移成功，跳校验
                    }
                }

                // 添加转移任务：成功返回：true
                $ret = self::add($clientKey, $torrent, $downloadDir, $extra_options);
                /**
                 * 转移成功的种子写日志
                 */
                $log = $info_hash . PHP_EOL . $torrentPath . PHP_EOL . $downloadDir . PHP_EOL . PHP_EOL;
                if ($ret) {
                    //转移成功时，删除做种，不删资源
                    if (isset(self::$conf['delete_torrent']) && self::$conf['delete_torrent']) {
                        static::getRpc($k)->delete($torrentDelete);
                    }
                    // 转移成功的种子，以infohash为文件名，写入缓存
                    static::wLog($log, $info_hash, self::$cacheMove);
                    static::wLog($log, 'MoveSuccess' . $k);
                    static::$notifyMsg['MoveSuccess']++;
                } else {
                    // 失败的种子
                    static::wLog($log, 'MoveError' . $k);
                    static::$notifyMsg['MoveError']++;
                }
            }
        }
    }

    /**
     * 过滤已转移的种子hash
     * @param array $infohash_Dir infohash与路径对应的字典
     * @return bool     true 过滤 | false 不过滤
     */
    private static function hashFilter(array &$infohash_Dir = array()): bool
    {
        foreach ($infohash_Dir as $info_hash => $dir) {
            if (is_file(self::$cacheMove . $info_hash . '.txt')) {
                unset($infohash_Dir[$info_hash]);
                echo '-------当前种子上次已成功转移，前置过滤已跳过！ 如需再次转移，可以清理转移缓存。' . PHP_EOL . PHP_EOL;
            }
        }
        return empty($infohash_Dir);
    }

    /**
     * 处理转移种子时所设置的过滤器、选择器
     * @param string $path
     * @return bool   true 过滤 | false 不过滤
     */
    private static function pathFilter(string &$path = ''): bool
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
                    if (strpos($path, $pathName) === 0) {      // 没用$path == $key判断，是为了提高兼容性
                        return false;
                    }
                }
                echo '已跳过！转移选择器未匹配到：' . $path . PHP_EOL;
                return true;
            }
        } elseif (\is_null($path_selector)) {
            //过滤器
            if (\is_array($path_filter)) {
                foreach ($path_filter as $pathName) {
                    if (strpos($path, $pathName) === 0) {      // 没用$path == $key判断，是为了提高兼容性
                        echo '已跳过！转移过滤器匹配到：' . $path . PHP_EOL;
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
                    if (strpos($path, $pathName) === 0) {
                        echo '已跳过！转移过滤器匹配到：' . $path . PHP_EOL;
                        return true;
                    }
                }
                //后选择器
                foreach ($path_selector as $pathName) {
                    if (strpos($path, $pathName) === 0) {
                        return false;
                    }
                }
                echo '已跳过！转移选择器未匹配到：' . $path . PHP_EOL;
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
    private static function pathReplace(string $path = ''): ?string
    {
        $type = intval(self::$conf['path_type']);
        $pathArray = self::$conf['path_rule'];
        $path = rtrim($path, DIRECTORY_SEPARATOR);      // 提高Windows转移兼容性
        switch ($type) {
            case 1:         // 减
                foreach ($pathArray as $key => $val) {
                    if (strpos($path, $key) === 0) {
                        return substr($path, strlen($key));
                    }
                }
                break;
            case 2:         // 加
                foreach ($pathArray as $key => $val) {
                    if (strpos($path, $key) === 0) {      // 没用$path == $key判断，是为了提高兼容性
                        return $val . $path;
                    }
                }
                break;
            case 3:         // 替换
                foreach ($pathArray as $key => $val) {
                    if (strpos($path, $key) === 0) {      // 没用$path == $key判断，是为了提高兼容性
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
     * @return string|bool          发送情况，json
     */
    protected static function job_done_notify()
    {
        $notify = self::$conf['notify'];
        if (false === static::isNotifyEnable($notify)) {
            return '';
        }
        $br = PHP_EOL;
        $text = 'IYUU转移任务-统计报表';
        $desp = '### 版本号：' . IYUU_VERSION() . $br;
        // 移动做种
        $desp .= $br . '----------' . $br;
        if (static::$notifyMsg['MoveSuccess'] || static::$notifyMsg['MoveError']) {
            $desp .= '**移动成功：' . static::$notifyMsg['MoveSuccess'] . '**  [会把hash加入移动缓存]' . $br;
            $desp .= '**移动失败：' . static::$notifyMsg['MoveError'] . '**  [解决错误提示，可以重试]' . $br;
            $desp .= '**如需重新移动，请删除 ./torrent/cachemove 移动缓存。**' . $br;
        } else {
            $desp .= $br . '转移任务完成，未发现种子需要转移' . $br;
            $desp .= $br . '----------' . $br;
        }
        $desp .= $br . '*此消息将在3天后过期*。';
        return static::send_notify($text, $desp);
    }
}
