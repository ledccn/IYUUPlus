<?php
/**
 * Rpc操作类
 * 把RSS解码与添加下载任务解耦
 */

namespace IYUU\Library;

use IYUU\Client\AbstractClient;

class Rpc
{
    // 站点标识
    /**
     * 退出状态码
     */
    public static $ExitCode = 0;
    // 下载种子的请求类型 GET POST
    protected static $site = '';
    protected static $method = 'GET';
    /**
     * 运行时解析的配置
     * @var array
     */
    protected static $conf = [];
    /**
     * cookie
     */
    protected static $cookies = '';
    /**
     * 浏览器 User-Agent
     */
    protected static $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36';
    /**
     * 客户端配置
     */
    protected static $clients = [];
    /**
     * 工作模式
     */
    protected static $workingMode = 0;
    /**
     * 种子存放路径   (斜杠结尾)
     */
    protected static $torrentDir = '';
    /**
     * 监控目录      (斜杠结尾)
     */
    protected static $watch = '';
    /**
     * 数据目录
     */
    protected static $downloadsDir = '';
    /**
     * RPC连接
     */
    protected static $links = array();

    /**
     * 初始化
     * @param string $site
     * @param string $method
     * @param array $config
     */
    public static function init(string $site, string $method, array $config)
    {
        //初始化站点、下载种子请求类型、所有配置
        self::$site = $site;
        self::$method = strtoupper($method);
        self::$conf = $config;    //所有配置

        //初始化cookie
        $userSite = $config['site'];
        self::$cookies = isset($userSite['cookie']) && $userSite['cookie'] ? $userSite['cookie'] : '';

        //初始化UserAgent
        $default = empty($config['default']) ? [] : $config['default'];
        self::$userAgent = isset($default['ua']) && $default['ua'] ? $default['ua'] : self::$userAgent;

        //初始化客户端
        self::$clients = $config['clients'];

        //初始化工作模式
        self::$workingMode = isset($config['workingMode']) ? 0 : 1;

        //初始化下载种子的存放目录
        self::$torrentDir = TORRENT_PATH . DS . $site . DS;

        //初始化watch目录
        if (empty(self::$clients['watch'])) {
            //watch监控目录未设置，设置为RPC下载模式
            self::$workingMode = 1;
        }
        $watch = isset(self::$clients['watch']) && self::$clients['watch'] ? self::$clients['watch'] : self::$torrentDir;
        self::$watch = rtrim($watch, '/') . DS;

        //初始化数据目录
        if (self::$workingMode) {
            //优先级：计划任务数据目录 > 下载器数据目录
            self::$downloadsDir = !empty(self::$conf['downloadsDir']) ? self::$conf['downloadsDir'] : (!empty(self::$clients['downloadsDir']) ? self::$clients['downloadsDir'] : '');
        }

        //建立watch监控目录
        IFile::mkdir(self::$watch);
        //建立下载种子的存放目录
        IFile::mkdir(self::$torrentDir);

        //连接下载器
        self::links();
    }

    /**
     * 连接远端RPC服务器
     * @return bool
     */
    public static function links()
    {
        if (self::$workingMode === 1) {
            // 跳过未配置的客户端
            if (empty(self::$clients['username']) || empty(self::$clients['password'])) {
                static::$links = array();
                //watch监控目录未设置，用户名密码未设置，直接报错
                if (empty(self::$clients['watch'])) {
                    die("clients_" . self::$clients['name'] . " 用户名或密码未配置，下载器的watch监控目录未配置！！" . PHP_EOL . PHP_EOL);
                }
                echo "clients_" . self::$clients['name'] . " 用户名或密码未配置，切换为watch模式！" . PHP_EOL . PHP_EOL;
                self::$workingMode = 0;
                return false;
            }
            try {
                // 传入配置，创建客户端实例
                $client = AbstractClient::create(self::$clients);
                static::$links['rpc'] = $client;
                static::$links['_config'] = self::$clients;
                static::$links['type'] = self::$clients['type'];
                static::$links['root_folder'] = self::$clients['root_folder'] ?? 1;
                $result = $client->status();
                print self::$clients['type'] . '：' . self::$clients['host'] . " Rpc连接 [{$result}]" . PHP_EOL;
            } catch (\Exception $e) {
                die(__FILE__ . ' LINE:' . __LINE__ . '[连接错误] ' . self::$clients['host'] . ' ' . $e->getMessage() . PHP_EOL);
            }
        }
        return true;
    }

    /**
     * @brief 种子处理函数
     * @param array $data 种子数组
     * Array
     * (
     * [id] => 118632
     * [h1] => CCTV5+ 2019 ATP Men's Tennis Final 20191115B HDTV 1080i H264-HDxxx
     * [title] => 央视体育赛事频道 2019年ATP男子网球年终总决赛 单打小组赛 纳达尔VS西西帕斯 20191115[优惠剩余时间：4时13分]
     * [details] => https://XXX.me/details.php?id=118632
     * [download] => https://XXX.me/download.php?id=118632
     * [filename] => 118632.torrent
     * [type] => 0
     * [sticky] => 1
     * [time] => Array
     * (
     * [0] => "2019-11-16 20:41:53">4时13分
     * [1] => "2019-11-16 14:41:53">1时<br />46分
     * )
     * [comments] => 0
     * [size] => 5232.64MB
     * [seeders] => 69
     * [leechers] => 10
     * [completed] => 93
     * [percentage] => 100%
     * [owner] => 匿名
     * )
     * @return mixed
     */
    public static function call(array $data = array())
    {
        // 首次运行锁 开始
        $LOCK = self::$torrentDir . self::$site . '.lock';
        if (!is_file($LOCK)) {
            echo "系统未检测到首次运行锁({$LOCK})，将会启动账号保护逻辑。" . PHP_EOL;
            $max_downloads = 2;
            echo "为保护账号安全，首次运行仅处理前{$max_downloads}个种子，其余全部忽略；下次运行，会继续下载新种。" . PHP_EOL;
            file_put_contents($LOCK, date('Y-m-d H:i:s'));
            foreach ($data as $k => $torrent) {
                if ($k < $max_downloads) {
                    continue;
                }
                $_torrentFile = self::$torrentDir . $torrent['id'] . '.torrent';
                file_put_contents($_torrentFile, \json_encode($torrent, JSON_UNESCAPED_UNICODE));
            }
        }
        // 首次运行锁 结束

        foreach ($data as $key => $value) {
            // 控制台打印
            echo '主标题：' . $value['h1'] . PHP_EOL;
            echo '副标题：' . $value['title'] . PHP_EOL;
            echo '详情页：' . $value['details'] . PHP_EOL;
            if ($value['type'] != 0) {
                echo "-----非免费，已忽略！" . PHP_EOL . PHP_EOL;
                continue;
            }
            if (isset($value['hr']) && ($value['hr'] == 1)) {
                echo "-----HR种子，已忽略！" . PHP_EOL . PHP_EOL;
                continue;
            }
            // 保存的文件名
            $filename = $value['id'] . '.torrent';
            // 默认watch工作模式，复制到此目录
            $torrentFileTo = self::$watch . $filename;
            // 种子完整存放路径
            $torrentFile = self::$torrentDir . $filename;
            if (is_file($torrentFile)) {
                $fileSize = filesize($torrentFile);        //失败会返回false 或 0（0代表上次下载失败）
                if (!empty($fileSize)) {
                    //种子已经存在
                    echo '-----存在旧种子：' . $filename . PHP_EOL . PHP_EOL;
                    continue;
                }
                // 删除下载错误的文件
                IFile::unlink($torrentFile);
            }

            // 调用过滤函数
            $filter = empty(self::$conf['filter']) ? [] : self::$conf['filter'];
            $isFilter = filter($filter, $value);
            if (is_string($isFilter)) {
                echo "-----" . $isFilter . PHP_EOL . PHP_EOL;
                continue;
            }
            //优先级最高：过滤器数据目录
            $downloadsDir = !empty($filter['downloadsDir']) ? $filter['downloadsDir'] : self::$downloadsDir;
            //种子不存在
            echo '正在下载新种子... ' . $value['download'] . PHP_EOL;
            // 创建文件、下载种子以二进制写入
            $content = download($value['download'], self::$cookies, self::$userAgent, self::$method);
            #cli($content);
            if (strpos($content, '第一次下载提示') !== false) {
                die('当前站点触发第一次下载提示，请手动下载1个种子，然后更新cookie！' . PHP_EOL);
            }
            // 成功：返回写入字节数，失败返回false
            $worldsnum = file_put_contents($torrentFile, $content);
            if (is_bool($worldsnum)) {
                print "种子下载失败！！！" . PHP_EOL . PHP_EOL;
                IFile::unlink($torrentFile);
                continue;
            } else {
                print "成功下载种子" . $filename . '，共计：' . $worldsnum . "字节" . PHP_EOL;
                sleep(mt_rand(2, 10));
                $ret = null;
                switch ((int)self::$workingMode) {
                    case 0:        //watch下载模式
                        // 复制到watch目录
                        if (dirname($torrentFile) !== dirname($torrentFileTo)) {
                            copy($torrentFile, $torrentFileTo);
                        }
                        if (is_file($torrentFileTo)) {
                            print "********watch模式，下载任务添加成功." . PHP_EOL . PHP_EOL;
                            $ret = true;
                        } else {
                            print "-----watch模式，下载任务添加失败!!!" . PHP_EOL . PHP_EOL;
                        }
                        break;
                    case 1:        //Rpc下载模式
                        // 下载任务的可选参数
                        $extra_options = array();
                        $type = self::$links['type'];
                        // 下载服务器类型
                        switch ($type) {
                            case 'transmission':
                                $ret = static::$links['rpc']->add_torrent($content, $downloadsDir, $extra_options);
                                break;
                            case 'qBittorrent':
                                $extra_options['name'] = 'torrents';
                                $extra_options['filename'] = $filename;
                                $extra_options['autoTMM'] = 'false';    //关闭自动种子管理
                                $ret = static::$links['rpc']->add_torrent($content, $downloadsDir, $extra_options);
                                break;
                            default:
                                break;
                        }
                        break;
                    default:
                        break;
                }
                // 微信通知
                if ($ret) {
                    send(static::$site, $value);
                }
            }
        }
        return true;
    }
}
