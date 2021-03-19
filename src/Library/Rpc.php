<?php
/**
 * Rpc操作类
 */
namespace IYUU\Library;

use IYUU\Client\AbstractClient;

class Rpc
{
    // 站点标识
    public static $site = '';
    // 下载种子的请求类型 GET POST
    public static $method = 'GET';
    /**
     * 运行时解析的配置
     * @var array
     */
    protected static $conf = [];
    // RPC连接池
    public static $links = array();
    /**
     * cookie
     */
    public static $cookies = '';
    /**
     * 浏览器 User-Agent
     */
    public static $userAgent = '';
    /**
     * passkey
     */
    public static $passkey = '';
    /**
     * 客户端配置
     */
    public static $clients = [];
    /**
     * 监控目录
     */
    public static $watch = '';
    /**
     * 种子存放路径
     */
    public static $torrentDir = '';
    /**
     * 工作模式
     */
    public static $workingMode = '';
    /**
     * 负载均衡 控制变量
     */
    public static $RPC_Key = 0;
    /**
     * 退出状态码
     */
    public static $ExitCode = 0;

    /**
     * 初始化
     * @param string $site
     * @param string $method
     * @param array  $conf
     */
    public static function init($site, $method, $conf)
    {
        self::$site = $site;
        self::$method = strtoupper($method);
        self::$conf = $conf;
        $config = static::$conf['site'];

        self::$cookies = $config['cookie'];
        self::$userAgent = isset($config['userAgent']) && $config['userAgent'] ? $config['userAgent'] : $configALL['default']['userAgent'];
        self::$clients = isset($config['clients']) && $config['clients'] ? $config['clients'] : $configALL['default']['clients'];
        self::$workingMode = isset($config['workingMode']) && $config['workingMode'] ? $config['workingMode'] : 0;
        $watch = isset($config['watch']) && $config['watch'] ? $config['watch'] : $configALL['default']['watch'];
        self::$watch = rtrim($watch, '/') . DS;
        self::$torrentDir = TORRENT_PATH . $site . DS;
        // 建立目录
        IFile::mkdir(self::$torrentDir);
        
        self::links();
    }

    /**
     * 连接远端RPC服务器
     *
     * @return bool
     */
    public static function links()
    {
        if (self::$workingMode === 1 && empty(self::$links)) {
            foreach (self::$clients as $k => $v) {
                // 跳过未配置的客户端
                if (empty($v['username']) || empty($v['password'])) {
                    unset(self::$clients[$k]);
                    echo "clients_".$k." 用户名或密码未配置，已跳过 \n\n";
                    continue;
                }
                try {
                    switch ($v['type']) {
                        case 'transmission':
                            $client = new TransmissionRPC($v['host'], $v['username'], $v['password']);
                            break;
                        case 'qBittorrent':
                            $client = new qBittorrent($v['host'], $v['username'], $v['password']);
                            break;
                        case 'uTorrent':
                            $client = new uTorrent($v['host'], $v['username'], $v['password']);
                            break;
                        default:
                            echo '[ERROR] '.$v['type'];
                            exit(1);
                            break;
                    }
                    self::$links[$k]['rpc'] = $client;
                    self::$links[$k]['type'] = $v['type'];
                    self::$links[$k]['downloadDir'] = isset($v['downloadDir']) && $v['downloadDir'] ? $v['downloadDir'] : '';
                    $result = $client->status();
                    print $v['type'].'：'.$v['host']." Rpc连接 [{$result}] \n";
                } catch (Exception $e) {
                    echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
                    exit(1);
                }
            }
        }
        return true;
    }

    /**
     * @brief 添加下载任务
     * @param string $torrent 种子元数据
     * @param string $save_path 保存路径
     * @return bool
     */
    public static function add($torrent, $save_path = '', $extra_options = array())
    {
        switch ((int)self::$workingMode) {
            case 0:		// watch默认工作模式
                // 复制到watch目录
                copy($torrent, $save_path);
                if (is_file($save_path)) {
                    print "********watch模式，下载任务添加成功 \n\n";
                    return true;
                } else {
                    print "-----watch模式，下载任务添加失败!!! \n\n";
                }
                break;
            case 1:		//负载均衡模式
                try {
                    $is_url = false;
                    if ((strpos($torrent, 'http://')===0) || (strpos($torrent, 'https://')===0) || (strpos($torrent, 'magnet:?xt=urn:btih:')===0)) {
                        $is_url = true;
                    }
                    // 负载均衡
                    $rpcKey = self::$RPC_Key;
                    echo '选中：负载均衡'.$rpcKey."\n";
                    self::rpcSelect();
                    // 调试
                    #p($result);
                    // 下载服务器类型 判断
                    $type = self::$links[$rpcKey]['type'];
                    switch ($type) {
                        case 'transmission':
                            if ($is_url) {
                                echo 'add';
                                $result = self::$links[$rpcKey]['rpc']->add($torrent, self::$links[$rpcKey]['downloadDir'], $extra_options);			// 种子URL添加下载任务
                            } else {
                                echo 'add_metainfo';
                                $result = self::$links[$rpcKey]['rpc']->add_metainfo($torrent, self::$links[$rpcKey]['downloadDir'], $extra_options);	// 种子文件添加下载任务
                            }
                            $id = $name = '';
                            if (isset($result->arguments->torrent_duplicate)) {
                                $id = $result->arguments->torrent_duplicate->id;
                                $name = $result->arguments->torrent_duplicate->name;
                            } elseif (isset($result->arguments->torrent_added)) {
                                $id = $result->arguments->torrent_added->id;
                                $name = $result->arguments->torrent_added->name;
                            }
                            if (!$id) {
                                print "-----RPC添加种子任务，失败 [{$result->result}] \n\n";
                            } else {
                                print "********RPC添加下载任务成功 [{$result->result}] (id=$id) \n\n";
                                // 新添加的任务，开始
                                self::$links[$rpcKey]['rpc']->start($id);
                                return true;
                            }
                            break;
                        case 'qBittorrent':
                            if ($is_url) {
                                echo 'add';
                                $result = self::$links[$rpcKey]['rpc']->add($torrent, self::$links[$rpcKey]['downloadDir'], $extra_options);			// 种子URL添加下载任务
                            } else {
                                echo 'add_metainfo';
                                $result = self::$links[$rpcKey]['rpc']->add_metainfo($torrent, self::$links[$rpcKey]['downloadDir'], $extra_options);	// 种子文件添加下载任务
                            }
                            if ($result === 'Ok.') {
                                print "********RPC添加下载任务成功 [{$result}] \n\n";
                                return true;
                            } else {
                                print "-----RPC添加种子任务，失败 [{$result}] \n\n";
                            }
                            break;
                        default:
                            echo '[ERROR] '.$type;
                            break;
                    }
                } catch (Exception $e) {
                    die('[ERROR] ' . $e->getMessage() . PHP_EOL);
                }
                break;
            case 2:
                echo "\n\n";
                # 暂未开放
                break;
            default:
                echo "\n\n";
                break;
        }
        return false;
    }

    /**
     * 负载均衡 选择算法
     *
     * @param
     * @return
     */
    public static function rpcSelect()
    {
        $clientsConut = count(self::$clients);
        if ($clientsConut > 1) {
            if ($clientsConut > (self::$RPC_Key+1)) {
                self::$RPC_Key++;
            } else {
                self::$RPC_Key = 0;
            }
        }
    }
    /**
     * @brief 种子处理函数
     * @param array $data 种子数组
     * Array
        (
            [id] => 118632
            [h1] => CCTV5+ 2019 ATP Men's Tennis Final 20191115B HDTV 1080i H264-HDxxx
            [title] => 央视体育赛事频道 2019年ATP男子网球年终总决赛 单打小组赛 纳达尔VS西西帕斯 20191115[优惠剩余时间：4时13分]
            [details] => https://XXX.me/details.php?id=118632
            [download] => https://XXX.me/download.php?id=118632
            [filename] => 118632.torrent
            [type] => 0
            [sticky] => 1
            [time] => Array
                (
                    [0] => "2019-11-16 20:41:53">4时13分
                    [1] => "2019-11-16 14:41:53">1时<br />46分
                )
            [comments] => 0
            [size] => 5232.64MB
            [seeders] => 69
            [leechers] => 10
            [completed] => 93
            [percentage] => 100%
            [owner] => 匿名
        )
     * @return
     */
    public static function call($data = array())
    {
        // 首次运行锁 开始
        $LOCK = self::$torrentDir . self::$site . '.lock';
        if (!is_file($LOCK)) {
            file_put_contents($LOCK, date('Y-m-d H:i:s'));
            foreach ($data as $k => $torrent) {
                if ($k < 2) {   // 首次运行仅处理前2个种子，其余全部忽略
                    continue;
                }
                $_torrentFile = self::$torrentDir . $torrent['id'] . '.torrent';
                file_put_contents($_torrentFile, \json_encode($torrent, JSON_UNESCAPED_UNICODE));
            }
        }
        // 首次运行锁 结束

        foreach ($data as $key => $value) {
            // 控制台打印
            echo '主标题：'.$value['h1']."\n";
            echo '副标题：'.$value['title']."\n";
            echo '详情页：'.$value['details']."\n";
            if ($value['type'] != 0) {
                echo "-----非免费，已忽略！ \n\n";
                continue;
            }
            if (isset($value['hr']) && ($value['hr'] == 1)) {
                echo "-----HR种子，已忽略！ \n\n";
                continue;
            }
            // 下载任务的可选参数
            $extra_options = array();
            // 保存的文件名
            $filename = $value['id'] . '.torrent';
            // 默认watch工作模式，复制到此目录
            $to = self::$watch . $filename;
            // 种子完整存放路径
            $torrentFile = self::$torrentDir . $filename;
            if (is_file($torrentFile)) {
                $fileSize = filesize($torrentFile);		//失败会返回false 或 0（0代表上次下载失败）
                if (!empty($fileSize)) {
                    //种子已经存在
                    echo '-----存在旧种子：'.$filename."\n\n";
                    continue;
                }
                // 删除下载错误的文件
                IFile::unlink($torrentFile);
            }
            
            // 调用过滤函数
            $isFilter = filter(self::$site, $value);
            if (is_string($isFilter)) {
                echo "-----" .$isFilter. "\n\n";
                continue;
            }
            //种子不存在
            echo '正在下载新种子... '.$value['download']." \n";
            // 创建文件、下载种子以二进制写入
            $content = '';
            $content = download($value['download'], self::$cookies, self::$userAgent, self::$method);
            if (strpos($content, '第一次下载提示') !== false) {
                die('当前站点触发第一次下载提示，请手动下载1个种子，然后更新cookie！'.PHP_EOL);
            }
            #p($content);
            // 文件句柄
            $resource = fopen($torrentFile, "wb");
            // 成功：返回写入字节数，失败返回false
            $worldsnum = fwrite($resource, $content);
            // 关闭
            fclose($resource);
            // 判断
            if (is_bool($worldsnum)) {
                print "种子下载失败！！！ \n\n";
                IFile::unlink($torrentFile);
                continue;
            } else {
                print "成功下载种子" . $filename . '，共计：' . $worldsnum . "字节 \n";
                sleep(mt_rand(2, 10));
                $ret = false;
                $rpcKey = self::$RPC_Key;
                switch ((int)self::$workingMode) {
                    case 0:		//默认工作模式
                        $ret = self::add($torrentFile, $to);
                        break;
                    case 1:		//负载均衡模式
                        $type = self::$links[$rpcKey]['type'];
                        // 下载服务器类型
                        switch ($type) {
                            case 'transmission':
                                # code...
                                break;
                            case 'qBittorrent':
                                $extra_options['name'] = 'torrents';
                                $extra_options['filename'] = $filename;
                                $extra_options['autoTMM'] = 'false';	//关闭自动种子管理
                                break;
                            default:
                                # code...
                                break;
                        }
                        // 种子文件添加下载任务
                        $ret = self::add($content, $to, $extra_options);
                        break;
                    default:
                        echo "\n\n";
                        break;
                }
            }
        }
        return true;
    }
}
