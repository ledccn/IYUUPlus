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
    /**
     * cookie
     */
    public static $cookies = '';
    /**
     * 浏览器 User-Agent
     */
    public static $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36';
    /**
     * 客户端配置
     */
    public static $clients = [];
    /**
     * 种子存放路径
     */
    public static $torrentDir = '';
    /**
     * 工作模式
     */
    public static $workingMode = '';
    /**
     * 监控目录
     */
    public static $watch = '';
    /**
     * RPC连接池
     */
    public static $links = array();
    /**
     * 退出状态码
     */
    public static $ExitCode = 0;

    /**
     * 初始化
     * @param string $site
     * @param string $method
     * @param array  $config
     */
    public static function init($site, $method, $config)
    {
        self::$site = $site;
        self::$method = strtoupper($method);
        self::$conf = $config;    //所有配置

        $userSite = $config['site'];
        self::$cookies = isset($userSite['cookie']) && $userSite['cookie'] ? $userSite['cookie'] : '';

        $default = empty($config['default']) ? [] : $config['default'];
        self::$userAgent = isset($default['ua']) && $default['ua'] ? $default['ua'] : self::$userAgent;

        self::$clients = $config['clients'];
        self::$torrentDir = TORRENT_PATH  . DS . $site . DS;
        self::$workingMode = isset($config['workingMode']) ? 0 : 1;

        $watch = isset(self::$clients['watch']) && self::$clients['watch'] ? self::$clients['watch'] : self::$torrentDir;
        if (empty(self::$clients['watch'])) {
            self::$workingMode = 1;
        }
        self::$watch = rtrim($watch, '/') . DS;

        // 建立目录
        IFile::mkdir(self::$torrentDir);
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
                if (empty(self::$clients['watch'])) {
                    die("clients_".self::$clients['name']." 用户名或密码未配置，下载器的watch监控目录未配置！！".PHP_EOL.PHP_EOL);
                }
                echo "clients_".self::$clients['name']." 用户名或密码未配置，切换为watch模式！".PHP_EOL.PHP_EOL;
                self::$workingMode = 0;
                return false;
            }
            try {
                // 传入配置，创建客户端实例
                $client = AbstractClient::create(self::$clients);
                static::$links['rpc'] = $client;
                static::$links['_config'] = self::$clients;
                static::$links['type'] = self::$clients['type'];
                static::$links['root_folder'] = isset(self::$clients['root_folder']) ? self::$clients['root_folder'] : 1;
                $result = $client->status();
                print self::$clients['type'].'：'.self::$clients['host']." Rpc连接 [{$result}]".PHP_EOL;
            } catch (\Exception $e) {
                die('[连接错误] '. self::$clients['host'] . ' ' . $e->getMessage() . PHP_EOL);
            }
        }
        return true;
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
     * @return mixed
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
            echo '主标题：'.$value['h1'].PHP_EOL;
            echo '副标题：'.$value['title'].PHP_EOL;
            echo '详情页：'.$value['details'].PHP_EOL;
            if ($value['type'] != 0) {
                echo "-----非免费，已忽略！".PHP_EOL.PHP_EOL;
                continue;
            }
            if (isset($value['hr']) && ($value['hr'] == 1)) {
                echo "-----HR种子，已忽略！".PHP_EOL.PHP_EOL;
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
                    echo '-----存在旧种子：'.$filename.PHP_EOL.PHP_EOL;
                    continue;
                }
                // 删除下载错误的文件
                IFile::unlink($torrentFile);
            }
            
            // 调用过滤函数
            $filter = empty(self::$conf['filter']) ? [] : self::$conf['filter'];
            $isFilter = filter($filter, $value);
            if (is_string($isFilter)) {
                echo "-----" .$isFilter. PHP_EOL.PHP_EOL;
                continue;
            }
            //种子不存在
            echo '正在下载新种子... '.$value['download'].PHP_EOL;
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
                print "种子下载失败！！！".PHP_EOL.PHP_EOL;
                IFile::unlink($torrentFile);
                continue;
            } else {
                print "成功下载种子" . $filename . '，共计：' . $worldsnum . "字节".PHP_EOL;
                sleep(mt_rand(2, 10));
                $ret = false;
                switch ((int)self::$workingMode) {
                    case 0:		//watch模式
                        // 复制到watch目录
                        copy($torrentFile, $to);
                        if (is_file($to)) {
                            print "********watch模式，下载任务添加成功.".PHP_EOL.PHP_EOL;
                            $ret = true;
                        } else {
                            print "-----watch模式，下载任务添加失败!!!".PHP_EOL.PHP_EOL;
                        }
                        break;
                    case 1:		//Rpc模式
                        $type = self::$links['type'];
                        // 下载服务器类型
                        switch ($type) {
                            case 'transmission':
                                $ret = static::$links['rpc']->add_torrent($content, $to, $extra_options);
                                break;
                            case 'qBittorrent':
                                $extra_options['name'] = 'torrents';
                                $extra_options['filename'] = $filename;
                                $extra_options['autoTMM'] = 'false';	//关闭自动种子管理
                                $ret = static::$links['rpc']->add_torrent($content, $to, $extra_options);
                                break;
                            default:
                                break;
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        return true;
    }
}
