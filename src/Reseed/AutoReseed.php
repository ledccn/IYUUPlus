<?php
namespace IYUU\Reseed;

use Curl\Curl;
use IYUU\Client\AbstractClient;
use IYUU\Library\IFile;
use IYUU\Library\Table;
use app\domain\Reseed as domainReseed;
use app\domain\Crontab as domainCrontab;

/**
 * IYUUAutoReseed辅种类
 */
class AutoReseed
{
    /**
     * 版本号
     */
    const VER = '2.0.0';
    /**
     * 配置
     * @var array
     */
    private static $conf = [];
    /**
     * RPC连接
     * @var array
     */
    private static $links = [];
    /**
     * 站点
     * @var array
     */
    private static $sites = [];
    /**
     * 客户端
     * @var array
     */
    private static $clients = [];
    /**
     * 用户选择辅种的站点
     * @var array
     */
    private static $_sites = [];
    /**
     * 推荐的合作站点
     * @var array
     */
    private static $recommend = [];
    /**
     * 不辅种的站点
     * @var array
     */
    private static $noReseed = [];
    /**
     * 运行缓存目录
     * @var string
     */
    public static $cacheDir  = TORRENT_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
    /**
     * 辅种缓存目录
     * @var string
     */
    public static $cacheHash = TORRENT_PATH . DIRECTORY_SEPARATOR . 'cachehash' . DIRECTORY_SEPARATOR;
    /**
     * 移动缓存目录
     * @var string
     */
    public static $cacheMove = TORRENT_PATH . DIRECTORY_SEPARATOR . 'cachemove' . DIRECTORY_SEPARATOR;
    /**
     * API接口配置
     * @var string
     */
    public static $apiUrl = 'http://api.iyuu.cn';
    /**
     * API接入点
     * @var array
     */
    public static $endpoints = array(
        'login'   => '/user/login',
        'sites'   => '/api/sites',
        'infohash'=> '/api/infohash',
        'hash'    => '/api/hash',
        'notify'  => '/api/notify',
        'recommendSites' => '/Api/GetRecommendSites',
        'getSign'   => '/Api/GetSign'
    );
    /**
     * @var null | Curl
     */
    private static $curl = null;
    protected static $pid_file = '';
    /**
     * 退出状态码
     * @var int
     */
    public static $ExitCode = 0;
    /**
     * 客户端转移做种 格式：['客户端key', '移动参数move']
     * @var null
     */
    private static $move = null;
    /**
     * 微信通知消息体
     * @var array
     */
    private static $wechatMsg = array(
        'hashCount'			=>	0,		// 提交给服务器的hash总数
        'sitesCount'		=>	0,		// 可辅种站点总数
        'reseedCount'		=>	0,		// 返回的总数据
        'reseedSuccess'		=>	0,		// 成功：辅种成功（会加入缓存，哪怕种子在校验中，下次也会过滤）
        'reseedError'		=>	0,		// 错误：辅种失败（可以重试）
        'reseedRepeat'		=>	0,		// 重复：客户端已做种
        'reseedSkip'		=>	0,		// 跳过：因未设置passkey，而跳过
        'reseedPass'		=>	0,		// 忽略：因上次成功添加、存在缓存，而跳过
        'MoveSuccess'       =>  0,      // 移动成功
        'MoveError'         =>  0,      // 移动失败
    );
    /**
     * 错误通知消息体
     * @var array
     */
    private static $errNotify = array(
        'sign'      => '',
        'site'      => '',
        'sid'       => 0,
        'torrent_id'=> 0,
        'error'     => '',
    );

    /**
     * 初始化
     */
    public static function init()
    {
        //sleep(mt_rand(1, 5));
        self::getCliInput();

        self::$curl = new Curl();
        self::$curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        self::$curl->setOpt(CURLOPT_SSL_VERIFYHOST, 2);

        self::Oauth();
        self::getSites();
        self::ShowTableSites();
        // 递归删除上次缓存
        IFile::rmdir(self::$cacheDir, true);
        IFile::mkdir(self::$cacheDir);
        IFile::mkdir(self::$cacheHash);
        IFile::mkdir(self::$cacheMove);
        // 连接全局客户端
        self::links();
    }

    /**
     * 解析命令行参数
     */
    protected static function getCliInput()
    {
        // 命令行参数
        global $argv;
        $cron_name = isset($argv[1]) ? $argv[1] : null;
        self::$conf = domainReseed::configParser($cron_name);
        if (empty(self::$conf['sites']) || empty(self::$conf['clients'])) {
            die('解析计划任务失败：站点或客户端为空！可能当前任务已被停止或删除！'.PHP_EOL);
        }
        self::savePid($cron_name);
        Oauth::init(self::$conf);
        // 用户选择辅种的站点
        self::$_sites = self::$conf['sites'];
        // 对url拼接串进行预处理
        array_walk(self::$_sites, function (&$v, $k){
            if (!empty($v['url_join'])) {
                $url_join = http_build_query($v['url_join']);
                $v['url_join'] = [$url_join];
            }
        });
        // 用户辅种的下载器
        self::$clients = self::$conf['clients'];
        echo microtime(true).' 命令行参数解析完成！'.PHP_EOL;
    }

    /**
     * 保存进程pid文件
     * @param string $cron_name
     */
    protected static function savePid($cron_name = '')
    {
        self::$conf['cron_name'] = $cron_name;  // 保存计划任务名字
        //pid文件
        self::$pid_file = domainCrontab::getPidFile($cron_name);
        $pid = 0;
        if (function_exists('posix_getpid')) {
            $pid = posix_getpid();
        }
        $data = time().','.$pid;
        file_put_contents(self::$pid_file, $data);

        //lock文件
        $lockFile = domainCrontab::getLockFile($cron_name);
        file_put_contents($lockFile, $data);

        //注册一个会在php中止时执行的函数
        register_shutdown_function(function () use (&$cron_name){
            self::deletePid();
            $lockFile = domainCrontab::getLockFile($cron_name);
            is_file($lockFile) and unlink($lockFile);
        });
    }

    /**
     * 删除pid文件
     */
    protected static function deletePid()
    {
        self::checkPid() and unlink(self::$pid_file);
    }

    /**
     * 检查pid文件
     * @return bool
     */
    protected static function checkPid()
    {
        clearstatcache();
        return is_file(self::$pid_file);
    }

    /**
     * 合作站点鉴权绑定
     */
    protected static function Oauth()
    {
        $recommend_sites = [];
        $ret = self::$curl->get(self::$apiUrl . self::$endpoints['recommendSites']);
        $ret = json_decode($ret->response, true);
        if (isset($ret['ret']) && $ret['ret'] === 200 && isset($ret['data']['recommend']) && is_array($ret['data']['recommend'])) {
            $recommend_sites = $ret['data']['recommend'];
            self::$recommend = array_column($recommend_sites, 'site');  // init
        }
        Oauth::login(self::$apiUrl . self::$endpoints['login'], $recommend_sites);
    }

    /**
     * 获取支持的辅种站点
     */
    protected static function getSites()
    {
        echo microtime(true).' 辅种版本号：' . self::VER . PHP_EOL;
        $list = [
            ' gitee源码仓库：https://gitee.com/ledc/IYUUAutoReseed',
            ' github源码仓库：https://github.com/ledccn/IYUUAutoReseed',
            ' 教程：https://gitee.com/ledc/IYUUAutoReseed/tree/master/wiki',
            ' 问答社区：http://wenda.iyuu.cn',
            ' 【IYUU自动辅种交流】QQ群：859882209、931954050、924099912'.PHP_EOL,
            ' 正在连接IYUUAutoReseed服务器，查询支持列表……'.PHP_EOL
        ];
        array_walk($list, function ($v, $k) {
            echo microtime(true). $v . PHP_EOL;
        });
        $url = sprintf('%s?sign=%s&version=%s',self::$apiUrl.self::$endpoints['sites'], Oauth::getSign(), self::VER);
        $res = self::$curl->get($url);
        $rs = json_decode($res->response, true);
        $sites = empty($rs['data']['sites']) ? [] : $rs['data']['sites'];
        if (empty($sites)) {
            if (!empty($rs['msg'])) {
                die($rs['msg'].PHP_EOL);
            }
            die('网络故障或远端服务器无响应，请稍后再试！！！');
        }
        self::$sites = array_column($sites, null, 'id');
    }

    /**
     * 显示支持站点列表
     */
    private static function ShowTableSites()
    {
        $data = [];
        $i = $j = $k = 0;   // i列、j序号、k行
        foreach (self::$sites as $v) {
            // 控制多少列
            if ($i > 4) {
                $k++;
                $i = 0;
            }
            $i++;
            $j++;
            $data[$k][] = $j.". ".$v['site'];
        }
        echo "IYUUAutoReseed自动辅种脚本，目前支持以下站点：".PHP_EOL;
        // 输出支持站点表格
        $table = new Table();
        $table->setRows($data);
        echo($table->render());
    }

    /**
     * 连接远端RPC下载器
     */
    protected static function links()
    {
        foreach (self::$clients as $k => $v) {
            // 跳过未配置的客户端
            if (empty($v['username']) || empty($v['password'])) {
                self::$links[$k] = array();
                echo "clients_".$v['name']." 用户名或密码未配置，已跳过！".PHP_EOL.PHP_EOL;
                continue;
            }
            try {
                // 传入配置，创建客户端实例
                $client = AbstractClient::create($v);
                self::$links[$k]['rpc'] = $client;
                self::$links[$k]['_config'] = $v;
                self::$links[$k]['type'] = $v['type'];
                self::$links[$k]['BT_backup'] = isset($v['BT_backup']) && $v['BT_backup'] ? $v['BT_backup'] : '';
                self::$links[$k]['root_folder'] = isset($v['root_folder']) ? $v['root_folder'] : 1;
                $result = $client->status();
                print $v['type'].'：'.$v['host']." Rpc连接 [{$result}]".PHP_EOL;
                // 检查转移做种 (self::$move为空，移动配置为真)
                if (is_null(self::$move) && !empty($v['move'])) {
                    self::$move = array($k, $v['move']);
                }
            } catch (\Exception $e) {
                die('[连接错误] '. $v['host'] . $e->getMessage() . PHP_EOL);
            }
        }
    }

    /**
     * @brief 添加下载任务
     * @param $rpcKey
     * @param string $torrent 种子元数据
     * @param string $save_path 保存路径
     * @param array $extra_options
     * @return bool
     */
    protected static function add($rpcKey, $torrent, $save_path = '', $extra_options = array())
    {
        try {
            $is_url = false;
            if ((strpos($torrent, 'http://')===0) || (strpos($torrent, 'https://')===0) || (strpos($torrent, 'magnet:?xt=urn:btih:')===0)) {
                $is_url = true;
            }
            // 下载服务器类型
            $type = self::$links[$rpcKey]['type'];
            // 判断
            switch ($type) {
                case 'transmission':
                    $extra_options['paused'] = isset($extra_options['paused']) ? $extra_options['paused'] : true;
                    if ($is_url) {
                        $result = self::$links[$rpcKey]['rpc']->add($torrent, $save_path, $extra_options);			// URL添加
                    } else {
                        $result = self::$links[$rpcKey]['rpc']->add_metainfo($torrent, $save_path, $extra_options);	// 元数据添加
                    }
                    if (isset($result['result']) && $result['result'] == 'success') {
                        $_key = isset($result['arguments']['torrent-added']) ? 'torrent-added' : 'torrent-duplicate';
                        $id = $result['arguments'][$_key]['id'];
                        $name = $result['arguments'][$_key]['name'];
                        print "名字：" .$name . PHP_EOL;
                        print "********RPC添加下载任务成功 [" .$result['result']. "] (id=" .$id. ")".PHP_EOL.PHP_EOL;
                        return true;
                    } else {
                        $errmsg = isset($result['result']) ? $result['result'] : '未知错误，请稍后重试！';
                        if (strpos($errmsg, 'http error 404: Not Found') !== false) {
                            self::sendNotify('404');
                        }
                        print "-----RPC添加种子任务，失败 [{$errmsg}]" . PHP_EOL.PHP_EOL;
                    }
                    break;
                case 'qBittorrent':
                    $extra_options['autoTMM'] = 'false';	//关闭自动种子管理
                    #$extra_options['skip_checking'] = 'true';    //跳校验
                    // 添加任务校验后是否暂停
                    if (isset($extra_options['paused'])) {
                        $extra_options['paused'] = $extra_options['paused'] ? 'true' : 'false';
                    } else {
                        $extra_options['paused'] = 'true';
                    }
                    // 是否创建根目录
                    $extra_options['root_folder'] = self::$links[$rpcKey]['root_folder'] ? 'true' : 'false';
                    if ($is_url) {
                        $result = self::$links[$rpcKey]['rpc']->add($torrent, $save_path, $extra_options);			// URL添加
                    } else {
                        $extra_options['name'] = 'torrents';
                        $extra_options['filename'] = time().'.torrent';
                        $result = self::$links[$rpcKey]['rpc']->add_metainfo($torrent, $save_path, $extra_options);	// 元数据添加
                    }
                    if ($result === 'Ok.') {
                        print "********RPC添加下载任务成功 [{$result}]".PHP_EOL.PHP_EOL;
                        return true;
                    } else {
                        print "-----RPC添加种子任务，失败 [{$result}]".PHP_EOL.PHP_EOL;
                    }
                    break;
                default:
                    echo '[下载器类型错误] '.$type. PHP_EOL. PHP_EOL;
                    break;
            }
        } catch (\Exception $e) {
            echo '[添加下载任务出错] ' . $e->getMessage() . PHP_EOL;
        }
        return false;
    }

    /**
     * 辅种或转移，总入口
     */
    public static function call()
    {
        // 命令行参数
        global $argv;
        $is_move = isset($argv[2]) ? $argv[2] : 'null';
        if (self::$move !== null) {
            self::move();
        }
        self::reseed();
        self::wechatMessage();
        exit(self::$ExitCode);
    }

    /**
     * IYUUAutoReseed辅种
     */
    private static function reseed()
    {
        // 支持站点数量
        self::$wechatMsg['sitesCount'] = count(self::$sites);
        // 遍历客户端 开始
        foreach (self::$links as $k => $v) {
            if (empty($v)) {
                echo "【".$v['_config']['name']."】 用户名或密码未配置，已跳过".PHP_EOL.PHP_EOL;
                continue;
            }
            // 过滤无需辅种的客户端
            if ((self::$move !== null) && (self::$move[0] != $k) && (self::$move[1] == 2)) {
                echo "【".$v['_config']['name']."】 根据设置无需辅种，已跳过！";
                continue;
            }
            echo "正在从下载器 【".$v['_config']['name']."】 获取种子哈希……".PHP_EOL;
            $hashArray = self::$links[$k]['rpc']->all();
            if (empty($hashArray)) {
                continue;
            }
            $infohash_Dir = $hashArray['hashString'];   // 哈希目录字典
            unset($hashArray['hashString']);
            // 签名
            $hashArray['sign'] = Oauth::getSign();
            $hashArray['timestamp'] = time();
            $hashArray['version'] = self::VER;
            // 写请求日志
            wlog($hashArray, 'Request_'.$k);
            self::$wechatMsg['hashCount'] += count($infohash_Dir);
            // 此处优化大于一万条做种时，设置超时
            if (count($infohash_Dir) > 5000) {
                $connecttimeout = isset(self::$conf['default']['CONNECTTIMEOUT']) && self::$conf['default']['CONNECTTIMEOUT'] > 60 ? self::$conf['default']['CONNECTTIMEOUT'] : 60;
                $timeout = isset(self::$conf['default']['TIMEOUT']) && self::$conf['default']['TIMEOUT'] > 600 ? self::$conf['default']['TIMEOUT'] : 600;
                self::$curl->setOpt(CURLOPT_CONNECTTIMEOUT, $connecttimeout);
                self::$curl->setOpt(CURLOPT_TIMEOUT, $timeout);
            }
            echo "正在向服务器提交 【".$v['_config']['name']."】 种子哈希……".PHP_EOL;
            $res = self::$curl->post(self::$apiUrl . self::$endpoints['infohash'], $hashArray);
            $res = json_decode($res->response, true);
            // 写响应日志
            wlog($res, 'Response_'.$k);
            $data = isset($res['data']) && $res['data'] ? $res['data'] : array();
            if (empty($data)) {
                echo "clients_".$k." 没有查询到可辅种数据".PHP_EOL.PHP_EOL;
                continue;
            }
            // 判断返回值
            if (isset($res['ret']) && $res['ret'] === 200) {
                echo "【".$v['_config']['name']."】 辅种数据下载成功！！！".PHP_EOL.PHP_EOL;
                echo '【提醒】未配置passkey的站点都会跳过！'.PHP_EOL.PHP_EOL;
            } else {
                $msg = isset($res['msg']) && $res['msg'] ? $res['msg'] : '远端服务器无响应，请稍后重试！';
                echo '-----辅种失败，原因：' .$msg.PHP_EOL.PHP_EOL;
                continue;
            }
            // 遍历当前客户端可辅种数据
            foreach ($data as $info_hash => $reseed) {
                $downloadDir = $infohash_Dir[$info_hash];   // 辅种目录
                foreach ($reseed['torrent'] as $id => $value) {
                    // 匹配的辅种数据累加
                    self::$wechatMsg['reseedCount']++;
                    // 站点id
                    $sid = $value['sid'];
                    // 种子id
                    $torrent_id = $value['torrent_id'];
                    // 检查禁用站点
                    if (empty(self::$sites[$sid])) {
                        echo '-----当前站点不受支持，已跳过。' .PHP_EOL.PHP_EOL;
                        self::$wechatMsg['reseedSkip']++;
                        continue;
                    }
                    // 站名
                    $siteName = self::$sites[$sid]['site'];
                    // 错误通知
                    self::setNotify($siteName, $sid, $torrent_id);
                    // 协议
                    $protocol = self::$sites[$sid]['is_https'] == 0 ? 'http://' : 'https://';
                    // 种子页规则
                    $download_page = str_replace('{}', $torrent_id, self::$sites[$sid]['download_page']);

                    // 辅种检查规则初始化   2020年12月12日新增
                    if (!is_array(self::$sites[$sid]['reseed_check'])) {
                        // 初始化
                        if (!empty(self::$sites[$sid]['reseed_check'])) {
                            $reseed_check = explode(',', self::$sites[$sid]['reseed_check']);
                            array_walk($reseed_check, function (&$v, $k) {
                                $v = trim($v);
                            });
                            self::$sites[$sid]['reseed_check'] = $reseed_check;
                        } else {
                            self::$sites[$sid]['reseed_check'] = [];
                        }
                    }
                    $reseed_check = self::$sites[$sid]['reseed_check']; // 赋值

                    // 临时种子连接（会写入辅种日志）
                    $_url = $protocol . self::$sites[$sid]['base_url']. '/' .$download_page;
                    /**
                     * 辅种前置检查
                     */
                    if (!self::reseedCheck($k, $value, $infohash_Dir, $downloadDir, $_url)) {
                        continue;
                    }
                    /**
                     * 种子推送方式区分
                     */
                    if (in_array('cookie', $reseed_check)) {
                        // 特殊站点：种子元数据推送给下载器
                        $reseedPass = false;    // 标志：跳过辅种
                        $cookie = trim(self::$_sites[$siteName]['cookie']);
                        $userAgent = self::$conf['default']['ua'];
                        switch ($siteName) {
                            case 'hdchina':
                                // 请求详情页
                                $details_html = self::getNexusPHPdetailsPage($protocol, $value, $cookie, $userAgent);
                                if (is_null($details_html)) {
                                    $reseedPass = true;
                                    break;
                                }
                                // 搜索种子地址
                                $remove = '{hash}';
                                $offset = strpos($details_html, str_replace($remove, '', self::$sites[$sid]['download_page']));
                                if ($offset === false) {
                                    $reseedPass = true;
                                    self::cookieExpired($siteName);     // cookie失效
                                    break;
                                }
                                // 提取种子地址
                                $regex = "/download.php\?hash\=(.*?)[\"|\']/i";   // 提取种子hash的正则表达式
                                if (preg_match($regex, $details_html, $matchs)) {
                                    // 拼接种子地址
                                    $_url = str_replace($remove, $matchs[1], $_url);
                                    echo "下载种子：".$_url.PHP_EOL;
                                    $url = download($_url, $cookie, $userAgent);
                                    if (strpos($url, '第一次下载提示') != false) {
                                        self::$noReseed[] = $siteName;
                                        $reseedPass = true;

                                        echo "当前站点触发第一次下载提示，已加入排除列表".PHP_EOL;
                                        sleepIYUU(30, '请进入瓷器详情页，点右上角蓝色框：下载种子，成功后更新cookie！');
                                        self::ff($siteName. '站点，辅种时触发第一次下载提示！');
                                        break;
                                    }
                                    if (strpos($url, '系统检测到过多的种子下载请求') != false) {
                                        self::$_sites[$siteName]['limit'] = 1;
                                        $reseedPass = true;

                                        echo "当前站点触发人机验证，已加入流控列表".PHP_EOL;
                                        self::ff($siteName. '站点，辅种时触发人机验证！');
                                        break;
                                    }
                                } else {
                                    $reseedPass = true;
                                    sleepIYUU(15, $siteName.'正则表达式未匹配到种子地址，可能站点已更新，请联系IYUU作者！');
                                }
                                break;
                            case 'hdcity':
                                $details_url = $protocol . self::$sites[$sid]['base_url'] . '/t-' .$torrent_id;
                                print "种子详情页：".$details_url.PHP_EOL;
                                if (empty(self::$_sites[$siteName]['cuhash'])) {
                                    // 请求包含cuhash的列表页
                                    $html = download($protocol .self::$sites[$sid]['base_url']. '/pt', $cookie, $userAgent);
                                    // 搜索cuhash
                                    $offset = strpos($html, 'cuhash=');
                                    if ($offset === false) {
                                        self::cookieExpired($siteName);     // cookie失效
                                        $reseedPass = true;
                                        break;
                                    }
                                    // 提取cuhash
                                    $regex = "/cuhash\=(.*?)[\"|\']/i";   // 提取种子cuhash的正则表达式
                                    if (preg_match($regex, $html, $matchs)) {
                                        self::$_sites[$siteName]['cuhash'] = $matchs[1];
                                    } else {
                                        $reseedPass = true;
                                        sleepIYUU(15, $siteName.'正则表达式未匹配到cuhash，可能站点已更新，请联系IYUU作者！');
                                        break;
                                    }
                                }
                                // 拼接种子地址
                                $remove = '{cuhash}';
                                $_url = str_replace($remove, self::$_sites[$siteName]['cuhash'], $_url);
                                // 城市下载种子会302转向
                                echo "下载种子：".$_url.PHP_EOL;
                                $url = download($_url, $cookie, $userAgent);
                                if (strpos($url, 'Non-exist torrent id!') != false) {
                                    echo '种子已被删除！'.PHP_EOL;
                                    self::sendNotify('404');
                                    // 标志：跳过辅种
                                    $reseedPass = true;
                                }
                                break;
                            case 'hdsky':
                                // 请求详情页
                                $details_html = self::getNexusPHPdetailsPage($protocol, $value, $cookie, $userAgent);
                                if (is_null($details_html)) {
                                    $reseedPass = true;
                                    break;
                                }
                                // 搜索种子地址
                                $remove = 'id={}&passkey={passkey}';
                                $offset = strpos($details_html, str_replace($remove, '', self::$sites[$sid]['download_page']));
                                if ($offset === false) {
                                    self::cookieExpired($siteName);     // cookie失效
                                    $reseedPass = true;
                                    break;
                                }
                                // 提取种子地址
                                $regex = '/download.php\?(.*?)["|\']/i';
                                if (preg_match($regex, $details_html, $matchs)) {
                                    // 拼接种子地址
                                    $download_page = str_replace($remove, '', self::$sites[$sid]['download_page']).str_replace('&amp;', '&', $matchs[1]);
                                    $_url = $protocol . self::$sites[$sid]['base_url']. '/' . $download_page;
                                    print "下载种子：".$_url.PHP_EOL;
                                    $url = download($_url, $cookie, $userAgent);
                                    if (strpos($url, '第一次下载提示') != false) {
                                        self::$noReseed[] = $siteName;
                                        $reseedPass = true;

                                        echo "当前站点触发第一次下载提示，已加入排除列表".PHP_EOL;
                                        echo "请进入种子详情页，下载种子，成功后更新cookie！".PHP_EOL;
                                        sleepIYUU(30, '请进入种子详情页，下载种子，成功后更新cookie！');
                                        self::ff($siteName. '站点，辅种时触发第一次下载提示！');
                                    }
                                } else {
                                    $reseedPass = true;
                                    sleepIYUU(15, $siteName.'正则表达式未匹配到种子地址，可能站点已更新，请联系IYUU作者！');
                                }
                                break;
                            default:
                                // 默认站点：推送给下载器种子URL链接
                                break;
                        }
                        // 检查switch内是否异常
                        if ($reseedPass) {
                            continue;
                        }
                        $downloadUrl = $_url;
                    } else {
                        $url = self::getTorrentUrl($siteName, $_url);
                        $downloadUrl = $url;
                    }

                    // 把种子URL，推送给下载器
                    echo '推送种子：' . $_url . PHP_EOL;
                    // 成功true | 失败false
                    $ret = self::add($k, $url, $downloadDir);

                    // 规范日志内容
                    $log = 'clients_'. $k . PHP_EOL . $downloadDir . PHP_EOL . $downloadUrl . PHP_EOL.PHP_EOL;
                    if ($ret) {
                        // 成功
                        // 操作流控参数
                        if (isset(self::$_sites[$siteName]['limitRule']) && self::$_sites[$siteName]['limitRule']) {
                            $limitRule = self::$_sites[$siteName]['limitRule'];
                            if ($limitRule['count']) {
                                self::$_sites[$siteName]['limitRule']['count']--;
                                self::$_sites[$siteName]['limitRule']['time'] = time();
                            }
                        }
                        // 添加成功，以infohash为文件名，写入缓存；所有客户端共用缓存，不可以重复辅种！如果需要重复辅种，请经常删除缓存！
                        wlog($log, $value['info_hash'], self::$cacheHash);
                        wlog($log, 'reseedSuccess');
                        // 成功累加
                        self::$wechatMsg['reseedSuccess']++;
                    } else {
                        // 失败
                        wlog($log, 'reseedError');
                        // 失败累加
                        self::$wechatMsg['reseedError']++;
                    }
                }
                // 当前种子辅种 结束
            }
            // 当前客户端辅种 结束
        }
        // 按客户端循环辅种 结束
    }

    /**
     * 请求NexusPHP详情页
     * @descr 天空、瓷器、城市 个别站用到
     * @param $protocol     string      协议
     * @param $torrent      array       种子
     * @param $cookie       string      Cookie
     * @param $userAgent    string      UA
     * @return mixed|null
     */
    private static function getNexusPHPdetailsPage($protocol, $torrent, $cookie, $userAgent)
    {
        $sid = $torrent['sid'];
        $torrent_id = $torrent['torrent_id'];

        // 拼接详情页URL
        $details = str_replace('{}', $torrent_id, 'details.php?id={}&hit=1');
        $details_url = $protocol . self::$sites[$sid]['base_url'] . '/' .$details;
        print "种子详情页：".$details_url.PHP_EOL;
        $details_html = download($details_url, $cookie, $userAgent);
        // 删种检查
        if (strpos($details_html, '没有该ID的种子') != false) {
            echo '种子已被删除！'.PHP_EOL;
            self::sendNotify('404');
            return null;
        }
        return $details_html;
    }

    /**
     * 微信通知cookie失效，延时15秒提示
     * @descr 天空、瓷器、城市 个别站用到
     * @param $siteName
     */
    private static function cookieExpired($siteName)
    {
        self::$_sites[$siteName]['cookie'] = '';

        self::ff($siteName. '站点，cookie已过期，请更新后重新辅种！');
        sleepIYUU(15, 'cookie已过期，请更新后重新辅种！已加入排除列表');
    }

    /**
     * 辅种前置检查
     * @param $k                int         客户端key
     * @param $torrent          array       可辅的种子
     * @param $infohash_Dir     array       当前客户端hash目录对应字典
     * @param $downloadDir      string      辅种目录
     * @param $_url             string      种子临时连接
     * @return bool     true 可辅种 | false 不可辅种
     */
    private static function reseedCheck($k, $torrent, $infohash_Dir, $downloadDir, $_url)
    {
        self::checkPid() or die('检测到当前任务被外部主动停止，进程退出！'.PHP_EOL);
        $sid = $torrent['sid'];
        $torrent_id = $torrent['torrent_id'];
        $info_hash = $torrent['info_hash'];
        $siteName = self::$sites[$sid]['site'];
        $reseed_check = self::$sites[$sid]['reseed_check'];
        if ($reseed_check && is_array($reseed_check)) {
            // 循环检查所有项目
            foreach ($reseed_check as $item) {
                echo "clients_".$k."正在循环检查所有项目... {$siteName}".PHP_EOL;
                $item = ($item === 'uid' ? 'id' : $item);   // 兼容性处理
                if (empty(self::$_sites[$siteName]) || empty(self::$_sites[$siteName][$item])) {
                    $msg =  '-------因当前' .$siteName. "站点未设置".$item."，已跳过！！".PHP_EOL.PHP_EOL;
                    echo $msg;
                    self::$wechatMsg['reseedSkip']++;
                    return false;
                }
            }
        }
        // 重复做种检测
        if (isset($infohash_Dir[$info_hash])) {
            echo '-------与客户端现有种子重复：'.$_url.PHP_EOL.PHP_EOL;
            self::$wechatMsg['reseedRepeat']++;
            return false;
        }
        // 历史添加检测
        if (is_file(self::$cacheHash . $info_hash.'.txt')) {
            echo '-------当前种子上次辅种已成功添加【'.self::$cacheHash . $info_hash.'】，已跳过！ '.$_url.PHP_EOL.PHP_EOL;
            self::$wechatMsg['reseedPass']++;
            return false;
        }
        // 检查站点是否可以辅种
        if (in_array($siteName, self::$noReseed)) {
            echo '-------已跳过不辅种的站点：'.$_url.PHP_EOL.PHP_EOL;
            self::$wechatMsg['reseedPass']++;
            // 写入日志文件，供用户手动辅种
            wlog('clients_'.$k.PHP_EOL.$downloadDir.PHP_EOL.$_url.PHP_EOL.PHP_EOL, $siteName);
            return false;
        }
        // 流控检测
        if (isset(self::$_sites[$siteName]['limit'])) {
            echo "-------因当前" .$siteName. "站点触发流控，已跳过！！ {$_url}".PHP_EOL.PHP_EOL;
            // 流控日志
            if ($siteName == 'hdchina') {
                $details_page = str_replace('{}', $torrent_id, 'details.php?id={}&hit=1');
                $_url = 'https://' .self::$sites[$sid]['base_url']. '/' .$details_page;
            }
            wlog('clients_'.$k.PHP_EOL.$downloadDir.PHP_EOL."-------因当前" .$siteName. "站点触发流控，已跳过！！ {$_url}".PHP_EOL.PHP_EOL, 'reseedLimit');
            self::$wechatMsg['reseedSkip']++;
            return false;
        }
        // 操作站点流控的配置
        if (isset(self::$_sites[$siteName]['limitRule']) && self::$_sites[$siteName]['limitRule']) {
            $limitRule = self::$_sites[$siteName]['limitRule'];
            if (isset($limitRule['count']) && isset($limitRule['sleep'])) {
                if ($limitRule['count'] <= 0) {
                    echo '-------当前站点辅种数量已满足规则，保障账号安全已跳过：'.$_url.PHP_EOL.PHP_EOL;
                    self::$wechatMsg['reseedPass']++;
                    return false;
                } else {
                    // 异步间隔流控算法：各站独立、执行时间最优
                    $lastTime = isset($limitRule['time']) ? $limitRule['time'] : 0; // 最近一次辅种成功的时间
                    if ($lastTime) {
                        $interval = time() - $lastTime;   // 间隔时间
                        if ($interval < $limitRule['sleep']) {
                            $t = $limitRule['sleep'] - $interval +  mt_rand(1, 5);
                            do {
                                echo microtime(true)." 为账号安全，辅种进程休眠 {$t} 秒后继续...".PHP_EOL;
                                sleep(1);
                            } while (--$t > 0);
                        }
                    }
                }
            } else {
                echo '-------当前站点流控规则错误，缺少count或sleep参数！请重新配置！'.$_url.PHP_EOL.PHP_EOL;
                self::$wechatMsg['reseedPass']++;
                return false;
            }
        }
        return true;
    }

    /**
     * 获取站点种子的URL
     * @param string $site
     * @param string $url
     * @return string           带host的完整种子下载连接
     */
    private static function getTorrentUrl($site = '', $url = '')
    {
        // 注入合作站种子的URL规则
        $url = self::getRecommendTorrentUrl($site, $url);
        // 进行补全
        if (!empty(self::$_sites[$site]['passkey']) && empty(self::$_sites[$site]['url_replace'])) {
            self::$_sites[$site]['url_replace'] = array('{passkey}' => trim(self::$_sites[$site]['passkey']));
        }
        // 通用操作：替换
        if (!empty(self::$_sites[$site]['url_replace'])) {
            $url = strtr($url, self::$_sites[$site]['url_replace']);
        }
        // 通用操作：拼接
        if (!empty(self::$_sites[$site]['url_join'])) {
            $url = $url . (strpos($url, '?') === false ? '?' : '&') . implode('&', self::$_sites[$site]['url_join']);
        }
        return $url;
    }

    /**
     * 注入合作站种子的URL规则
     * @param string $site
     * @param string $url
     * @return string
     */
    private static function getRecommendTorrentUrl($site = '', $url = '')
    {
        if (in_array($site, self::$recommend)) {
            $now = time();
            $uid = isset(self::$_sites[$site]['id']) ? self::$_sites[$site]['id'] : 0;
            $pk = isset(self::$_sites[$site]['passkey']) ? trim(self::$_sites[$site]['passkey']) : $now;
            $hash = md5($pk);

            $signString = self::getDownloadTorrentSign($site);  // 检查签名有效期，如果过期获取新的签名
            switch ($site) {
                case 'pthome':
                case 'hdhome':
                case 'hddolby':
                    if (isset(self::$_sites[$site]['downHash']) && self::$_sites[$site]['downHash']) {
                        $hash = self::$_sites[$site]['downHash'];    // 直接提交专用下载hash
                    }
                    break;
                case 'ourbits':
                    // 兼容旧版本的IYUU
                    if ($uid) {
                        $url = str_replace('passkey={passkey}', 'uid={uid}&hash={hash}', $url);
                    }
                    break;
                default:
                    break;
            }

            // 注入替换规则
            $replace = [
                '{uid}' => $uid,
                '{hash}'=> $hash,
                '{passkey}' => $pk,
            ];
            self::$_sites[$site]['url_replace'] = $replace;

            // 注入拼接规则
            if (empty(self::$_sites[$site]['url_join'])) {
                self::$_sites[$site]['runtime_url_join'] = [];      //保存用户配置规则
                self::$_sites[$site]['url_join'] = array($signString);
            } else {
                // 用户已配置过url_join 1.先保存用户原来的规则；2.恢复规则；3.注入签名规则
                if (!isset(self::$_sites[$site]['runtime_url_join'])) {
                    self::$_sites[$site]['runtime_url_join'] = self::$_sites[$site]['url_join'];      //保存用户配置规则
                } else {
                    self::$_sites[$site]['url_join'] = self::$_sites[$site]['runtime_url_join'];      //恢复用户配置规则
                }
                self::$_sites[$site]['url_join'][] = $signString;
            }
        }

        return $url;
    }

    /**
     * 获取下载合作站种子的签名
     * @descr 检查签名有效期，如果过期将获取新的签名
     * @param string $site
     * @return string
     */
    private static function getDownloadTorrentSign($site = '')
    {
        $signKEY = 'signString';
        $expireKEY = 'signExpire';
        if (isset(self::$_sites[$site][$signKEY]) && isset(self::$_sites[$site][$expireKEY]) && (self::$_sites[$site][$expireKEY] > time())) {
            return self::$_sites[$site][$signKEY];     // 缓存在有效期内，直接返回
        }

        // 请求IYUU获取签名
        $data = [
            'sign' => self::$conf['iyuu.cn'],
            'timestamp' => time(),
            'version'   => self::VER,
            'site'      => $site,
            'uid'       => isset(self::$_sites[$site]['id']) ? self::$_sites[$site]['id'] : 0,
        ];
        $res = self::$curl->get(self::$apiUrl . self::$endpoints['getSign'], $data);
        $ret = json_decode($res->response, true);
        $signString = '';
        if (isset($ret['ret']) && $ret['ret'] === 200) {
            if (isset($ret['data'][$signKEY]) && isset($ret['data']['expire'])) {
                $signString = $ret['data'][$signKEY];
                $expire     = $ret['data']['expire'];
                self::$_sites[$site][$signKEY]     = $signString;
                self::$_sites[$site][$expireKEY]   = time() + $expire - 60;     // 提前60秒过期
            }
        } else {
            echo $site.' 很抱歉，请求IYUU辅种签名时失败啦，请稍后重新尝试辅种！详情：'.$ret['msg'].PHP_EOL;
        }

        return $signString;
    }

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

    /**
     * 微信模板消息拼接方法
     * @return string           发送情况，json
     */
    private static function wechatMessage()
    {
        if (isset(self::$conf['notify_on_change']) && self::$conf['notify_on_change'] && self::$wechatMsg['reseedSuccess'] == 0 && self::$wechatMsg['reseedError'] == 0) {
            return '';
        }
        $br = PHP_EOL;
        $text = 'IYUU自动辅种-统计报表';
        $desp = '### 版本号：'. self::VER . $br;
        $desp .= '**支持站点：'.self::$wechatMsg['sitesCount']. '**  [当前支持自动辅种的站点数量]' .$br;
        $desp .= '**总做种：'.self::$wechatMsg['hashCount'] . '**  [客户端做种的hash总数]' .$br;
        $desp .= '**返回数据：'.self::$wechatMsg['reseedCount']. '**  [服务器返回的可辅种数据]' .$br;
        $desp .= '**成功：'.self::$wechatMsg['reseedSuccess']. '**  [会把hash加入辅种缓存]' .$br;
        $desp .= '**失败：'.self::$wechatMsg['reseedError']. '**  [种子下载失败或网络超时引起]' .$br;
        $desp .= '**重复：'.self::$wechatMsg['reseedRepeat']. '**  [客户端已做种]' .$br;
        $desp .= '**跳过：'.self::$wechatMsg['reseedSkip']. '**  [未设置passkey]' .$br;
        $desp .= '**忽略：'.self::$wechatMsg['reseedPass']. '**  [成功添加存在缓存]' .$br;
        // 失败详情
        if (self::$wechatMsg['reseedError']) {
            $desp .= '**失败详情，见 ./torrent/cache/reseedError.txt**'.$br;
        }
        // 重新辅种
        $desp .= '**如需重新辅种，请删除 ./torrent/cachehash 辅种缓存。**'.$br;
        // 移动做种
        if (self::$wechatMsg['MoveSuccess'] || self::$wechatMsg['MoveError']) {
            $desp .= $br.'----------'.$br;
            $desp .= '**移动成功：'.self::$wechatMsg['MoveSuccess']. '**  [会把hash加入移动缓存]' .$br;
            $desp .= '**移动失败：'.self::$wechatMsg['MoveError']. '**  [解决错误提示，可以重试]' .$br;
            $desp .= '**如需重新移动，请删除 ./torrent/cachemove 移动缓存。**'.$br;
        }
        $desp .= $br.'*此消息将在3天后过期*。';
        return self::ff($text, $desp);
    }

    /**
     * 错误的种子通知服务器
     * @param string $error
     * @return bool
     */
    private static function sendNotify($error = '')
    {
        self::$errNotify['error'] = $error;
        $notify = http_build_query(self::$errNotify);
        self::$errNotify = array(
            'sign' => '',
            'site' => '',
            'sid'   => 0,
            'torrent_id'=> 0,
            'error'   => '',
        );
        $res = self::$curl->get(self::$apiUrl.self::$endpoints['notify'].'?'.$notify);
        $res = json_decode($res->response, true);
        if (isset($res['data']['success']) && $res['data']['success']) {
            echo '感谢您的参与，失效种子上报成功！！'.PHP_EOL;
        }
        return true;
    }

    /**
     * 设置通知主体
     * @param string $siteName
     * @param int $sid
     * @param int $torrent_id
     */
    private static function setNotify($siteName = '', $sid = 0, $torrent_id = 0)
    {
        self::$errNotify = array(
            'sign' => Oauth::getSign(),
            'site' => $siteName,
            'sid'   => $sid,
            'torrent_id'=> $torrent_id,
        );
    }

    /**
     * 微信推送 爱语飞飞
     * @param string $text
     * @param string $desp
     * @return false|string
     */
    private static function ff($text='', $desp='')
    {
        $token = self::$conf['iyuu.cn'];
        $desp = empty($desp) ? date("Y-m-d H:i:s") : $desp;
        $postdata = http_build_query(array(
            'text' => $text,
            'desp' => $desp
        ));
        $opts = array('http' =>	array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postdata
        ));
        $context  = stream_context_create($opts);
        $result = file_get_contents('http://iyuu.cn/'.$token.'.send', false, $context);
        return  $result;
    }
}
