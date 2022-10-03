<?php

namespace IYUU\Reseed;

use app\common\event\EventDispatcher;
use Curl\Curl;
use Exception;
use IYUU\Client\AbstractClient;
use IYUU\Client\ClientException;
use IYUU\Client\qBittorrent\qBittorrent;
use IYUU\Client\transmission\transmission;
use IYUU\Library\IFile;
use IYUU\Library\Table;
use app\common\components\Curl as ICurl;
use app\common\Constant;
use app\domain\ConfigParser\Reseed as domainReseed;
use app\domain\Crontab as domainCrontab;
use IYUU\Reseed\Events\ClientLinkSuccessEvent;
use IYUU\Reseed\Listener\ClientLinkSuccessListener;

/**
 * IYUUAutoReseed辅种类
 */
class AutoReseed
{
    /**
     * 事件调度器
     * @var EventDispatcher
     */
    protected static $EventDispatcher;

    /**
     * 运行缓存目录
     * @var string
     */
    public static $cacheDir = TORRENT_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
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
     * 错误通知缓存目录
     * @var string
     */
    public static $cacheNotify = TORRENT_PATH . DIRECTORY_SEPARATOR . 'cacheNotify' . DIRECTORY_SEPARATOR;
    /**
     * 退出状态码
     * @var int
     */
    public static $ExitCode = 0;
    /**
     * 解析的运行时配置
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
     * @var null | Curl
     */
    protected static $curl = null;
    /**
     * 进程pid文件
     * @var string
     */
    protected static $pid_file = '';
    /**
     * 微信通知消息体
     * @var array
     */
    protected static $wechatMsg = array(
        'hashCount' => 0,  // 提交给服务器的hash总数
        'sitesCount' => 0,  // 可辅种站点总数
        'reseedCount' => 0,  // 返回的总数据
        'reseedSuccess' => 0,  // 成功：辅种成功（会加入缓存，哪怕种子在校验中，下次也会过滤）
        'reseedError' => 0,  // 错误：辅种失败（可以重试）
        'reseedRepeat' => 0,  // 重复：客户端已做种
        'reseedSkip' => 0,  // 跳过：因未设置passkey，而跳过
        'reseedPass' => 0,  // 忽略：因上次成功添加、存在缓存，而跳过
        'MoveSuccess' => 0,  // 移动成功
        'MoveError' => 0,  // 移动失败
    );
    /**
     * 错误通知消息体
     * @var array
     */
    protected static $errNotify = array(
        'sign' => '',
        'site' => '',
        'sid' => 0,
        'torrent_id' => 0,
        'error' => '',
    );
    /**
     * 临时变量
     */
    protected static $temp = [];
    /**
     * 站点
     * @var array
     */
    private static $sites = [];
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
     * 初始化
     */
    public static function init()
    {
        //初始化事件调度器
        $listener = [
            new ClientLinkSuccessListener::class,
        ];
        static::$EventDispatcher = new EventDispatcher($listener);

        // 1. 初始化curl
        self::$curl = new Curl();
        self::$curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        // 2. 解析命令行参数
        self::getCliInput();
        // 3. 鉴权绑定
        self::Oauth();
        // 4. 获取站点列表
        self::getSites();
        // 5. 显示站点列表
        self::ShowTableSites();
        // 6. 递归删除上次缓存
        IFile::rmdir(self::$cacheDir, true);
        // 7. 初始化运行目录
        IFile::mkdir(self::$cacheDir);
        IFile::mkdir(self::$cacheHash);
        IFile::mkdir(self::$cacheMove);
        IFile::mkdir(self::$cacheNotify);
        // 8. 连接下载服务器
        self::links();
    }

    /**
     * 解析命令行参数
     */
    protected static function getCliInput()
    {
        global $argv;
        $cron_name = $argv[1] ?? null;
        empty($cron_name) and die('缺少命令行参数。');
        self::$conf = domainReseed::parser($cron_name);
        if (empty(self::$conf['sites']) || empty(self::$conf['clients'])) {
            die('解析计划任务失败：站点或客户端为空！可能当前任务已被停止或删除！' . PHP_EOL);
        }
        self::savePid($cron_name);
        // 初始化Oauth静态类
        Oauth::init(self::$conf);
        // 勾选的辅种站点
        self::$_sites = self::$conf['sites'];
        // 预处理url拼接串
        array_walk(self::$_sites, function (&$v, $k) {
            if (!empty($v['url_join'])) {
                $url_join = http_build_query($v['url_join']);
                $v['url_join'] = [$url_join];
            }
        });
        // 用户辅种的下载器
        self::$clients = self::$conf['clients'];
        // curl超时
        $default = self::$conf['default'];
        $_timeout = isset($default['CONNECTTIMEOUT']) && is_numeric($default['CONNECTTIMEOUT']) && (int)$default['CONNECTTIMEOUT'] > 20 ? $default['CONNECTTIMEOUT'] : 20;
        $timeout = isset($default['TIMEOUT']) && is_numeric($default['TIMEOUT']) && (int)$default['TIMEOUT'] > 100 ? $default['TIMEOUT'] : 100;
        self::$curl->setOpt(CURLOPT_CONNECTTIMEOUT, $_timeout);
        self::$curl->setOpt(CURLOPT_TIMEOUT, $timeout);
        echo microtime(true) . ' 命令行参数解析完成！' . PHP_EOL;
    }

    /**
     * 保存进程pid文件
     * @param string $cron_name
     */
    protected static function savePid(string $cron_name)
    {
        self::$conf['cron_name'] = $cron_name;  // 保存计划任务名字
        //pid文件
        self::$pid_file = domainCrontab::getPidFile($cron_name);
        $pid = 0;
        if (function_exists('posix_getpid')) {
            $pid = posix_getpid();
        }
        $data = time() . ',' . $pid;
        file_put_contents(self::$pid_file, $data);

        //lock文件
        $lockFile = domainCrontab::getLockFile($cron_name);
        //TODO.. 本次执行检查锁，避免系统资源耗尽
        file_put_contents($lockFile, $data);

        //注册一个会在php中止时执行的函数，删除pid、删除锁文件
        register_shutdown_function(function () use (&$cron_name) {
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
    protected static function checkPid(): bool
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
        $ret = self::$curl->get(Constant::API_BASE . Constant::API['recommend']);
        $ret = json_decode($ret->response, true);
        if (isset($ret['ret']) && $ret['ret'] === 200 && isset($ret['data']['recommend']) && is_array($ret['data']['recommend'])) {
            $recommend_sites = $ret['data']['recommend'];
            self::$recommend = array_column($recommend_sites, 'site');  // init
        }
        Oauth::login(Constant::API_BASE . Constant::API['login'], $recommend_sites);
    }

    /**
     * 获取支持的辅种站点
     */
    protected static function getSites()
    {
        static::showInfo();
        $url = sprintf('%s?sign=%s&version=%s', Constant::API_BASE . Constant::API['sites'], Oauth::getSign(), IYUU_VERSION());
        $res = self::$curl->get($url);
        $rs = json_decode($res->response, true);
        $sites = empty($rs['data']['sites']) ? [] : $rs['data']['sites'];
        if (empty($sites)) {
            if (!empty($rs['msg'])) {
                die($rs['msg'] . PHP_EOL);
            }
            die('网络故障或远端服务器无响应，请稍后再试！！！' . PHP_EOL . '如果多次出现此提示，请修改您设置的执行周期（请勿整点、半点执行），错峰辅种。');
        }
        self::$sites = array_column($sites, null, 'id');
        // 初始化辅种检查规则    2020年12月12日新增
        array_walk(self::$sites, function (&$v, $k) {
            if (empty($v['reseed_check'])) {
                $v['reseed_check'] = [];
            } else {
                $rule = explode(',', $v['reseed_check']);
                array_walk($rule, function (&$vv, $kk) {
                    $vv = trim($vv);
                });
                $v['reseed_check'] = empty($rule) ? [] : $rule;
            }
        });
    }

    /**
     * 显示基本信息
     */
    protected static function showInfo()
    {
        echo microtime(true) . ' 辅种版本号：' . IYUU_VERSION() . PHP_EOL;
        $list = [
            ' gitee源码仓库：https://gitee.com/ledc/iyuuplus',
            ' github源码仓库：https://github.com/ledccn/IYUUPlus',
            ' 教程：https://www.iyuu.cn',
            ' 【IYUU自动辅种交流】QQ群：859882209, 931954050, 924099912, 586608623, 41477250' . PHP_EOL,
            ' 正在连接IYUUAutoReseed服务器，查询支持列表……' . PHP_EOL
        ];
        array_walk($list, function ($v, $k) {
            echo microtime(true) . $v . PHP_EOL;
        });
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
            $data[$k][] = $j . ". " . $v['site'];
        }
        echo "IYUUPlus自动辅种，目前支持以下站点：" . PHP_EOL;
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
        foreach (static::$clients as $k => $v) {
            // 跳过未配置的客户端
            if (empty($v['username']) || empty($v['password'])) {
                static::$links[$k] = array();
                echo "clients_" . $v['name'] . " 用户名或密码未配置，已跳过！" . PHP_EOL . PHP_EOL;
                continue;
            }
            try {
                $client = AbstractClient::create($v);
                static::$links[$k]['rpc'] = $client;    // 客户端实例
                static::$links[$k]['_config'] = $v;     // 完整配置
                static::$links[$k]['type'] = $v['type'];// 类型
                static::$links[$k]['BT_backup'] = !empty($v['BT_backup']) ? $v['BT_backup'] : '';
                static::$links[$k]['root_folder'] = isset($v['root_folder']) && booleanParse($v['root_folder']);
                $result = $client->status();
                static::$links[$k]['version'] = $result;    // QB：v4.3.8, TR：success
                static::$links[$k]['reseed_infohash'] = []; // 初始化本次运行时辅种infohash变量
                print $v['type'] . '：' . $v['host'] . " Rpc连接 [{$result}]" . PHP_EOL;
            } catch (Exception $e) {
                die('[连接错误] ' . $v['host'] . ' ' . $e->getMessage() . PHP_EOL);
            }
        }
        //触发事件
        $event = new ClientLinkSuccessEvent(static::$clients, static::$links);
        static::$EventDispatcher->dispatch($event);
    }

    /**
     * 辅种或转移，总入口
     * @throws ClientException
     */
    public static function call()
    {
        self::reseed();
        self::wechatMessage();
        exit(self::$ExitCode);
    }

    /**
     * IYUUAutoReseed辅种
     * @throws ClientException
     */
    private static function reseed()
    {
        // 支持站点数量
        self::$wechatMsg['sitesCount'] = count(self::$sites);
        // 按客户端循环辅种 开始
        foreach (self::$links as $clientKey => $clientValue) {
            if (empty($clientValue)) {
                echo "【当前下载器】 用户名或密码未配置，已跳过" . PHP_EOL . PHP_EOL;
                continue;
            }
            echo "正在从下载器 【" . $clientValue['_config']['name'] . "】 获取种子哈希……" . PHP_EOL;
            $hashArray = static::getRpc($clientKey)->all();
            if (empty($hashArray)) {
                continue;
            }
            if (isset($clientValue['_config']['debug'])) {
                cli($hashArray);
            }
            $hashString = $hashArray['hashString'];   // 哈希目录字典
            unset($hashArray['hashString']);
            // 签名
            $sign = [];
            $sign['sign'] = Oauth::getSign();
            $sign['timestamp'] = time();
            $sign['version'] = IYUU_VERSION();
            // 写请求日志
            static::wLog($hashArray, 'Request_' . $clientKey);
            self::$wechatMsg['hashCount'] += count($hashString);
            // 分组200个hash，分批辅种
            $group_num = 200;
            if (count($hashString) > $group_num) {
                $hashJson = $hashArray['hash'];
                $infoHash = json_decode($hashJson, true);
                $hash = array_chunk($infoHash, $group_num);
                foreach ($hash as $info_hash) {
                    $hashArray = [];
                    sort($info_hash);
                    $json = json_encode($info_hash, JSON_UNESCAPED_UNICODE);
                    $hashArray['hash'] = $json;
                    $hashArray['sha1'] = sha1($json);
                    self::requestApi($hashString, array_merge($hashArray, $sign), $clientKey, $clientValue);
                }
            } else {
                self::requestApi($hashString, array_merge($hashArray, $sign), $clientKey, $clientValue);
            }
            /**
             * qBittorrent下载器的特殊操作
             */
            if ($clientValue['type'] === 'qBittorrent') {
                echo '检查当前客户端自动校验开关和已添加辅种任务的种子infohash，是否需要自动校验' . PHP_EOL;
                //cli(static::$links[$clientKey]['reseed_infohash'] ?? []);
                if (isset(static::$conf['auto_check']) && !empty(static::$links[$clientKey]['reseed_infohash'])) {
                    $msg = ' qBittorrent下载服务器添加辅种任务:' . count(static::$links[$clientKey]['reseed_infohash']) . '个，稍后将发送自动校验命令。';
                    $hashes = join('|', static::$links[$clientKey]['reseed_infohash']);
                    sleepIYUU(30, $msg);
                    static::getRpc($clientKey)->recheck($hashes);
                } else {
                    echo '当前qBittorrent下载器未开启自动校验或本次未添加辅种任务。' . PHP_EOL;
                }
            }
        }
        echo PHP_EOL . '辅种已完成';
    }

    /**
     * 追加式写入日志
     * @param string|int|array|object $data
     * @param string $name
     * @param string $path
     * @return bool|int
     */
    protected static function wLog($data, string $name = '', string $path = '')
    {
        if (is_bool($data)) {
            $show_data = $data ? 'true' : 'false';
        } elseif (is_null($data)) {
            $show_data = 'null';
        } else {
            $show_data = print_r($data, true);
        }
        // 写入日志
        $dir = empty($path) ? static::$cacheDir : $path;
        IFile::mkdir($dir);
        $file = $dir . $name . '.txt';
        $pointer = @fopen($file, "a");
        $result = @fwrite($pointer, $show_data);
        @fclose($pointer);
        return $result;
    }

    /**
     * 请求API接口获取当前客户端辅种数据
     * @param array $hashString 当前客户端infohash与目录对应的字典
     * @param array $hashArray 当前客户端infohash
     * @param int $clientKey 当前客户端key
     * @param array $clientValue 当前客户端配置
     */
    private static function requestApi(array $hashString, array $hashArray, int $clientKey, array $clientValue)
    {
        echo "正在向服务器提交 【" . $clientValue['_config']['name'] . "】 种子哈希……" . PHP_EOL;
        $res = self::$curl->post(Constant::API_BASE . Constant::API['infohash'], $hashArray);
        if (isset($clientValue['_config']['debug'])) {
            cli($res->response);
        }
        $res = json_decode($res->response, true);
        // 写响应日志
        static::wLog($res, 'Response_' . $clientKey);
        $data = $res['data'] ?? array();
        if (empty($data)) {
            echo "clients_" . $clientKey . "【" . $clientValue['_config']['name'] . "】 没有查询到可辅种数据" . PHP_EOL . PHP_EOL;
            return;
        }
        // 判断返回值
        if (isset($res['ret']) && $res['ret'] === 200) {
            echo "【" . $clientValue['_config']['name'] . "】 辅种数据下载成功！！！" . PHP_EOL . PHP_EOL;
            echo '【提醒】未配置passkey的站点都会跳过！' . PHP_EOL . PHP_EOL;
        } else {
            $msg = isset($res['msg']) && $res['msg'] ? $res['msg'] : '远端服务器无响应，请稍后重试！';
            echo '-----辅种失败，原因：' . $msg . PHP_EOL . PHP_EOL;
            return;
        }
        // 遍历当前客户端可辅种数据
        self::selfClientReseed($data, $hashString, $clientKey);
    }

    /**
     * 遍历当前客户端可辅种数据
     * @param array $data 接口返回的可辅种数据
     * @param array $hashString 当前客户端infohash与目录对应的字典
     * @param int $clientKey 当前客户端key
     */
    private static function selfClientReseed(array $data = [], array $hashString = [], int $clientKey = 0)
    {
        foreach ($data as $info_hash => $reseed) {
            $downloadDir = $hashString[$info_hash];   // 辅种目录
            foreach ($reseed['torrent'] as $id => $value) {
                // 匹配的辅种数据累加
                self::$wechatMsg['reseedCount']++;
                $sid = $value['sid'];                // 站点id
                $torrent_id = $value['torrent_id'];  // 种子id
                $reseed_infohash = $value['info_hash'];  // 种子infohash
                // 检查禁用站点
                if (empty(self::$sites[$sid])) {
                    echo '-----当前站点不受支持，已跳过。sid:' . $sid . PHP_EOL . PHP_EOL;
                    self::$wechatMsg['reseedSkip']++;
                    continue;
                }
                // 站名
                $siteName = self::$sites[$sid]['site'];
                // 设置错误通知数据结构
                self::setNotify($siteName, $sid, $torrent_id);
                // 协议
                $protocol = self::$sites[$sid]['is_https'] == 0 ? 'http://' : 'https://';
                // 种子页规则
                $download_page = str_replace('{}', $torrent_id, self::$sites[$sid]['download_page']);
                // 辅种检查规则
                $reseed_check = self::$sites[$sid]['reseed_check'];
                // 代理或镜像域名
                if (!empty(self::$_sites[$siteName]['mirror'])) {
                    echo '您已配置当前站点的镜像域名：' . self::$_sites[$siteName]['mirror'] . PHP_EOL;
                    self::$sites[$sid]['base_url'] = self::$_sites[$siteName]['mirror'];
                }

                // 临时种子连接（会写入辅种日志）
                $_url = $protocol . self::$sites[$sid]['base_url'] . '/' . $download_page;
                /**
                 * 辅种前置检查
                 */
                if (!self::reseedCheck($clientKey, $value, $hashString, $downloadDir, $_url)) {
                    continue;
                }
                /**
                 * 种子推送方式区分
                 */
                if (in_array('cookie', $reseed_check)) {
                    // 特殊站点：种子元数据推送给下载器
                    $url = '';
                    $reseedPass = false;    // 标志：跳过辅种

                    $cookie = trim(self::$_sites[$siteName]['cookie']);
                    $userAgent = empty(self::$conf['default']['ua']) ? Constant::UserAgent : self::$conf['default']['ua'];
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
                                echo "下载种子：" . $_url . PHP_EOL;
                                $url = download($_url, $cookie, $userAgent);
                                if (strpos($url, '第一次下载提示') !== false) {
                                    self::$noReseed[] = $siteName;
                                    $reseedPass = true;

                                    echo "当前站点触发第一次下载提示，已加入排除列表" . PHP_EOL;
                                    sleepIYUU(30, '请进入种子详情页，点右上角蓝色框：下载种子，成功后更新cookie！');
                                    self::ff($siteName . '站点，辅种时触发第一次下载提示！');
                                    break;
                                }
                                if (strpos($url, '系统检测到过多的种子下载请求') !== false) {
                                    self::$_sites[$siteName]['limit'] = 1;
                                    $reseedPass = true;

                                    echo "当前站点触发人机验证，已加入流控列表" . PHP_EOL;
                                    self::ff($siteName . '站点，辅种时触发流量控制！');
                                    break;
                                }
                            } else {
                                $reseedPass = true;
                                sleepIYUU(15, $siteName . '正则表达式未匹配到种子地址，可能站点已更新，请联系IYUU作者！');
                            }
                            break;
                        case 'hdcity':
                            $details_url = $protocol . self::$sites[$sid]['base_url'] . '/t-' . $torrent_id;
                            print "种子详情页：" . $details_url . PHP_EOL;
                            if (empty(self::$_sites[$siteName]['cuhash'])) {
                                // 请求包含cuhash的列表页
                                $html = download($protocol . self::$sites[$sid]['base_url'] . '/pt', $cookie, $userAgent);
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
                                    sleepIYUU(15, $siteName . '正则表达式未匹配到cuhash，可能站点已更新，请联系IYUU作者！');
                                    break;
                                }
                            }
                            // 拼接种子地址
                            $remove = '{cuhash}';
                            $_url = str_replace($remove, self::$_sites[$siteName]['cuhash'], $_url);
                            // 城市下载种子会302转向
                            echo "下载种子：" . $_url . PHP_EOL;
                            $url = download($_url, $cookie, $userAgent);
                            if (strpos($url, 'Non-exist torrent id!') !== false) {
                                echo '种子已被删除！' . PHP_EOL;
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
                                $download_page = str_replace($remove, '', self::$sites[$sid]['download_page']) . str_replace('&amp;', '&', $matchs[1]);
                                $_url = $protocol . self::$sites[$sid]['base_url'] . '/' . $download_page;
                                print "下载种子：" . $_url . PHP_EOL;
                                $url = download($_url, $cookie, $userAgent, 'POST');
                                if (strpos($url, '第一次下载提示')) {
                                    self::$noReseed[] = $siteName;
                                    $reseedPass = true;

                                    echo "当前站点触发第一次下载提示，已加入排除列表" . PHP_EOL;
                                    echo "请进入种子详情页，下载种子，成功后更新cookie！" . PHP_EOL;
                                    sleepIYUU(30, '请进入种子详情页，下载种子，成功后更新cookie！');
                                    self::ff($siteName . '站点，辅种时触发第一次下载提示！');
                                }
                            } else {
                                $reseedPass = true;
                                sleepIYUU(15, $siteName . '正则表达式未匹配到种子地址，可能站点已更新，请联系IYUU作者！');
                            }
                            break;
                        default:
                            // 未特殊定义，凭借cookie下载种子的站点
                            print "下载种子：" . $_url . PHP_EOL;
                            $url = download($_url, $cookie, $userAgent);
                            if (strpos($url, '第一次下载提示')) {
                                self::$noReseed[] = $siteName;
                                $reseedPass = true;

                                echo "当前站点触发第一次下载提示，已加入排除列表" . PHP_EOL;
                                echo "请进入种子详情页，下载种子，成功后更新cookie！" . PHP_EOL;
                                sleepIYUU(30, '请进入种子详情页，下载种子，成功后更新cookie！');
                                self::ff($siteName . '站点，辅种时触发第一次下载提示！');
                            }
                            break;
                    }
                    // 检查switch内是否异常
                    if ($reseedPass) {
                        continue;
                    }
                    $downloadUrl = $_url;
                } else {
                    $url = self::getTorrentUrl($siteName, $sid, $_url);
                    $downloadUrl = $url;
                }

                // 把种子URL或元数据，推送给下载器
                echo '推送种子：' . $_url . PHP_EOL;
                // 成功true | 失败false
                $ret = self::add($clientKey, $url, $downloadDir);

                // 规范日志内容
                $log = 'clients_' . $clientKey . "【" . self::$links[$clientKey]['_config']['name'] . "】" . PHP_EOL . $downloadDir . PHP_EOL . $downloadUrl . PHP_EOL . PHP_EOL;
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
                    static::wLog($log, $value['info_hash'], self::$cacheHash);
                    static::wLog($log, 'reseedSuccess');
                    // 成功累加
                    self::$wechatMsg['reseedSuccess']++;
                    // 保存当前客户端辅种的INFOHASH
                    self::$links[$clientKey]['reseed_infohash'][] = $reseed_infohash;
                } else {
                    // 失败
                    static::wLog($log, 'reseedError');
                    // 失败累加
                    self::$wechatMsg['reseedError']++;
                }
            }
            // 当前种子辅种 结束
        }
    }

    /**
     * 设置通知主体
     * @param string $siteName
     * @param int $sid
     * @param int $torrent_id
     */
    private static function setNotify(string $siteName = '', int $sid = 0, int $torrent_id = 0)
    {
        self::$errNotify = array(
            'sign' => Oauth::getSign(),
            'site' => $siteName,
            'sid' => $sid,
            'torrent_id' => $torrent_id,
        );
    }

    /**
     * 辅种前置检查
     * @param int $k                         客户端key
     * @param array $torrent                 可辅的种子
     * @param array $infohash_Dir            当前客户端hash目录对应字典
     * @param string $downloadDir            辅种目录
     * @param string $_url                   种子临时连接
     * @return bool     true 可辅种 | false 不可辅种
     */
    private static function reseedCheck(int $k, array $torrent, array $infohash_Dir, string $downloadDir, string $_url): bool
    {
        self::checkPid() or die('检测到当前任务被外部主动停止，进程退出！' . PHP_EOL);
        $sid = $torrent['sid'];
        $torrent_id = $torrent['torrent_id'];
        $info_hash = $torrent['info_hash'];
        $siteName = self::$sites[$sid]['site'];
        $reseed_check = self::$sites[$sid]['reseed_check'];
        if ($reseed_check && is_array($reseed_check)) {
            // 循环检查所有项目
            foreach ($reseed_check as $item) {
                echo "clients_" . $k . "【" . self::$links[$k]['_config']['name'] . "】正在循环检查所有项目... {$siteName}" . PHP_EOL;
                $item = ($item === 'uid' ? 'id' : $item);   // 兼容性处理【用户的user_id在配置项内是id】
                if (empty(self::$_sites[$siteName]) || empty(self::$_sites[$siteName][$item])) {
                    $msg = '-------因当前' . $siteName . "站点未设置" . $item . "，已跳过！！【如果确实已设置，请检查辅种任务，是否勾选{$siteName}站点】" . PHP_EOL . PHP_EOL;
                    echo $msg;
                    self::$wechatMsg['reseedSkip']++;
                    return false;
                }
            }
        }
        // 重复做种检测
        if (isset($infohash_Dir[$info_hash])) {
            echo '-------与客户端现有种子重复：' . $_url . PHP_EOL . PHP_EOL;
            self::$wechatMsg['reseedRepeat']++;
            return false;
        }
        // 历史添加检测
        if (is_file(self::$cacheHash . $info_hash . '.txt')) {
            echo '-------当前种子上次辅种已成功添加【' . self::$cacheHash . $info_hash . '】，已跳过！ ' . $_url . PHP_EOL . PHP_EOL;
            self::$wechatMsg['reseedPass']++;
            return false;
        }
        // 检查站点是否可以辅种
        if (in_array($siteName, self::$noReseed)) {
            echo '-------已跳过不辅种的站点：' . $_url . PHP_EOL . PHP_EOL;
            self::$wechatMsg['reseedPass']++;
            // 写入日志文件，供用户手动辅种
            static::wLog('clients_' . $k . "【" . self::$links[$k]['_config']['name'] . "】" . PHP_EOL . $downloadDir . PHP_EOL . $_url . PHP_EOL . PHP_EOL, $siteName);
            return false;
        }
        // 流控检测
        if (isset(self::$_sites[$siteName]['limit'])) {
            echo "-------因当前" . $siteName . "站点触发流控，已跳过！！ {$_url}" . PHP_EOL . PHP_EOL;
            // 流控日志
            if ($siteName == 'hdchina') {
                $details_page = str_replace('{}', $torrent_id, 'details.php?id={}&hit=1');
                $_url = 'https://' . self::$sites[$sid]['base_url'] . '/' . $details_page;
            }
            static::wLog('clients_' . $k . "【" . self::$links[$k]['_config']['name'] . "】" . PHP_EOL . $downloadDir . PHP_EOL . "-------因当前" . $siteName . "站点触发流控，已跳过！！ {$_url}" . PHP_EOL . PHP_EOL, 'reseedLimit');
            self::$wechatMsg['reseedSkip']++;
            return false;
        }
        // 操作站点流控的配置
        if (isset(self::$_sites[$siteName]['limitRule']) && self::$_sites[$siteName]['limitRule']) {
            $limitRule = self::$_sites[$siteName]['limitRule'];
            if (isset($limitRule['count']) && isset($limitRule['sleep'])) {
                if ($limitRule['count'] <= 0) {
                    echo '-------每次运行辅种，下载种子超过流控限制，会在下次运行辅种的时候，继续添加辅种。当前站点辅种数量已满足规则，保障账号安全已跳过：' . $_url . PHP_EOL . PHP_EOL;
                    self::$wechatMsg['reseedPass']++;
                    return false;
                } else {
                    // 异步间隔流控算法：各站独立、执行时间最优
                    $lastTime = $limitRule['time'] ?? 0; // 最近一次辅种成功的时间
                    if ($lastTime) {
                        $interval = time() - $lastTime;   // 间隔时间
                        if ($interval < $limitRule['sleep']) {
                            $t = $limitRule['sleep'] - $interval + mt_rand(1, 5);
                            do {
                                echo microtime(true) . " 为账号安全，辅种进程休眠 {$t} 秒后继续..." . PHP_EOL;
                                sleep(1);
                            } while (--$t > 0);
                        }
                    }
                }
            } else {
                echo '-------当前站点流控规则错误，缺少count或sleep参数！请重新配置！' . $_url . PHP_EOL . PHP_EOL;
                self::$wechatMsg['reseedPass']++;
                return false;
            }
        }
        return true;
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
        $details_url = $protocol . self::$sites[$sid]['base_url'] . '/' . $details;
        print "种子详情页：" . $details_url . PHP_EOL;
        $details_html = download($details_url, $cookie, $userAgent);
        // 删种检查
        if (strpos($details_html, '没有该ID的种子')) {
            echo '种子已被删除！' . PHP_EOL;
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
        $msg = $siteName . '站点，cookie已过期，请更新后重新辅种！';
        $msg_md5 = md5($msg);
        if (empty(static::$temp[$msg_md5])) {
            self::ff($msg);
            static::$temp[$msg_md5] = $msg;
        }

        sleepIYUU(15, 'cookie已过期，请更新后重新辅种！出现此提示切莫惊慌，请根据提示信息，手动访问种子详情页，检查种子是否被删除。');
    }

    /**
     * 微信推送 爱语飞飞
     * @param string $text
     * @param string $desp
     * @return false|string
     */
    protected static function ff(string $text = '', string $desp = '')
    {
        $token = static::$conf['iyuu.cn'];
        $desp = empty($desp) ? date("Y-m-d H:i:s") : $desp;
        $data = array(
            'text' => $text,
            'desp' => $desp
        );
        return ICurl::http_post('https://iyuu.cn/' . $token . '.send', $data);
    }

    /**
     * 获取站点种子的URL
     * @param string $site 站点名称
     * @param int $sid 站点ID
     * @param string $url 未替换前的url
     * @return string           带host的完整种子下载连接
     */
    private static function getTorrentUrl(string $site = '', int $sid = 0, string $url = ''): string
    {
        // 注入替换规则
        if (in_array($site, self::$recommend)) {
            $url = self::getRecommendTorrentUrl($site, $url);
        } else {
            $reseed_check = self::$sites[$sid]['reseed_check'];
            if ($reseed_check && is_array($reseed_check)) {
                $replace = [];
                foreach ($reseed_check as $value) {
                    $value = ($value === 'uid' ? 'id' : $value);   // 兼容性处理【用户的user_id在配置项内是id】
                    $key = '{' . $value . '}';
                    $replace[$key] = empty(self::$_sites[$site][$value]) ? '' : self::$_sites[$site][$value];
                }
                self::$_sites[$site]['url_replace'] = $replace;
            }
        }

        // 通用操作：替换
        if (!empty(self::$_sites[$site]['url_replace'])) {
            $url = strtr($url, self::$_sites[$site]['url_replace']);
        }
        // 通用操作：拼接
        if (!empty(self::$_sites[$site]['url_join'])) {
            $delimiter = strpos($url, '?') === false ? '?' : '&';
            $url = $url . $delimiter . implode('&', self::$_sites[$site]['url_join']);
        }
        return $url;
    }

    /**
     * 注入合作站种子的URL规则
     * @param string $site
     * @param string $url
     * @return string
     */
    private static function getRecommendTorrentUrl(string $site = '', string $url = ''): string
    {
        if (in_array($site, self::$recommend)) {
            $now = time();
            $uid = !empty(self::$_sites[$site]['id']) ? self::$_sites[$site]['id'] : 0;
            $passkey = !empty(self::$_sites[$site]['passkey']) ? trim(self::$_sites[$site]['passkey']) : $now;
            $hash = md5($passkey);

            $signString = self::getDownloadTorrentSign($site);  // 检查签名有效期，如果过期获取新的签名

            switch ($site) {
                case 'pthome':
                case 'hdhome':
                case 'hddolby':
                    if (!empty(self::$_sites[$site]['downHash'])) {
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

            // 注入推荐站点的替换规则
            $replace = [
                '{uid}' => $uid,
                '{hash}' => $hash,
                '{passkey}' => $passkey,
            ];
            self::$_sites[$site]['url_replace'] = $replace;

            // 注入推荐站点的拼接规则
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
    private static function getDownloadTorrentSign(string $site = ''): string
    {
        $signKEY = 'signString';
        $expireKEY = 'signExpire';
        if (isset(self::$_sites[$site][$signKEY]) && isset(self::$_sites[$site][$expireKEY]) && (self::$_sites[$site][$expireKEY] > time())) {
            return self::$_sites[$site][$signKEY];     // 缓存在有效期内，直接返回
        }

        // 请求IYUU获取签名
        $data = [
            'sign' => self::$conf['iyuu.cn'],
            'version' => IYUU_VERSION(),
            'site' => $site,
            'uid' => self::$_sites[$site]['id'] ?? 0,
        ];
        $res = self::$curl->get(Constant::API_BASE . Constant::API['getSign'], $data);
        $ret = json_decode($res->response, true);
        $signString = '';
        if (isset($ret['ret']) && $ret['ret'] === 200) {
            if (isset($ret['data'][$signKEY]) && isset($ret['data']['expire'])) {
                $signString = $ret['data'][$signKEY];
                $expire = $ret['data']['expire'];
                self::$_sites[$site][$signKEY] = $signString;
                self::$_sites[$site][$expireKEY] = time() + $expire - 60;     // 提前60秒过期
            }
        } else {
            echo $site . ' 很抱歉，请求IYUU辅种签名时失败啦，请稍后重新尝试辅种！详情：' . ($ret['msg'] ?? 'null') . PHP_EOL;
        }

        return $signString;
    }

    /**
     * @brief 添加下载任务
     * @param int $clientKey
     * @param string $torrent 种子元数据
     * @param string $save_path 保存路径
     * @param array $extra_options
     * @return bool
     */
    protected static function add(int $clientKey, string $torrent, string $save_path = '', array $extra_options = array()): bool
    {
        try {
            $is_url = static::isTorrentUrl($torrent);
            // 下载服务器类型
            $type = static::$links[$clientKey]['type'];
            // 判断
            switch ($type) {
                case 'transmission':
                    $extra_options['paused'] = $extra_options['paused'] ?? true;
                    if ($is_url) {
                        $result = static::getRpc($clientKey)->add($torrent, $save_path, $extra_options);            // URL添加
                    } else {
                        $result = static::getRpc($clientKey)->add_metainfo($torrent, $save_path, $extra_options);    // 元数据添加
                    }
                    if (isset($result['result']) && $result['result'] == 'success') {
                        $_key = isset($result['arguments']['torrent-added']) ? 'torrent-added' : 'torrent-duplicate';
                        $id = $result['arguments'][$_key]['id'];
                        $name = $result['arguments'][$_key]['name'];
                        print "名字：" . $name . PHP_EOL;
                        print "********RPC添加下载任务成功 [" . $result['result'] . "] (id=" . $id . ")" . PHP_EOL . PHP_EOL;
                        return true;
                    } else {
                        $err = $result['result'] ?? '未知错误，请稍后重试！';
                        if (strpos($err, 'http error 404: Not Found') !== false) {
                            static::sendNotify('404');
                        }
                        print "-----RPC添加种子任务，失败 [{$err}]" . PHP_EOL . PHP_EOL;
                    }
                    break;
                case 'qBittorrent':
                    //如果用户的下载器设置自动种子管理，需要传入这个参数
                    if (isset(static::$links[$clientKey]['_config']['autoTMM'])) {
                        $extra_options['autoTMM'] = 'false';  //关闭自动种子管理
                    }
                    // 添加任务校验后是否暂停
                    if (isset($extra_options['paused'])) {
                        $extra_options['paused'] = $extra_options['paused'] ? 'true' : 'false';
                    } else {
                        $extra_options['paused'] = 'true';
                    }
                    // 是否创建根目录
                    $extra_options['root_folder'] = static::$links[$clientKey]['root_folder'] ? 'true' : 'false';
                    if ($is_url) {
                        $result = static::getRpc($clientKey)->add($torrent, $save_path, $extra_options);            // URL添加
                    } else {
                        $extra_options['name'] = 'torrents';
                        $extra_options['filename'] = time() . '.torrent';
                        $result = static::getRpc($clientKey)->add_metainfo($torrent, $save_path, $extra_options);    // 元数据添加
                    }
                    if ($result === 'Ok.') {
                        print "********RPC添加下载任务成功 [{$result}]" . PHP_EOL . PHP_EOL;
                        return true;
                    } else {
                        print "-----RPC添加种子任务，失败 [{$result}]" . PHP_EOL . PHP_EOL;
                    }
                    break;
                default:
                    echo '[下载器类型错误] ' . $type . PHP_EOL . PHP_EOL;
                    break;
            }
        } catch (Exception $e) {
            echo '[添加下载任务出错] ' . $e->getMessage() . PHP_EOL;
        }
        return false;
    }

    /**
     * 判断内容是否为种子的URL链接
     * @param string $torrent
     * @return bool
     */
    protected static function isTorrentUrl(string $torrent): bool
    {
        return (stripos($torrent, 'http://') === 0) || (stripos($torrent, 'https://') === 0) || (strpos($torrent, 'magnet:?xt=urn:btih:') === 0);
    }

    /**
     * 优化IDE跟踪
     * @param string $clientKey
     * @return qBittorrent|transmission
     */
    public static function getRpc(string $clientKey)
    {
        return static::$links[$clientKey]['rpc'];
    }

    /**
     * 错误的种子通知服务器
     * @param string $error
     * @return void
     */
    private static function sendNotify(string $error = ''): void
    {
        self::$errNotify['error'] = $error;

        // 存在错误通知缓存，直接返回（减少请求次数）
        $errNotifyCacheFile = self::errNotifyCacheFile(self::$errNotify['sid'], self::$errNotify['torrent_id']);
        if (is_file($errNotifyCacheFile)) {
            echo '感谢您的参与，失效种子已经成功汇报过！！' . PHP_EOL;
            return;
        }

        // 创建错误通知缓存
        file_put_contents($errNotifyCacheFile, json_encode(self::$errNotify, JSON_UNESCAPED_UNICODE));

        $notify = http_build_query(self::$errNotify);
        self::$errNotify = array(
            'sign' => '',
            'site' => '',
            'sid' => 0,
            'torrent_id' => 0,
            'error' => '',
        );
        $res = self::$curl->get(Constant::API_BASE . Constant::API['notify'] . '?' . $notify);
        $res = json_decode($res->response, true);
        if (isset($res['data']['success']) && $res['data']['success']) {
            echo '感谢您的参与，失效种子上报成功！！' . PHP_EOL;
        }
    }

    /**
     * 拼接错误通知缓存的文件路径
     * @param int $site_id
     * @param int $torrent_id
     * @return string
     */
    private static function errNotifyCacheFile(int $site_id = 0, int $torrent_id = 0): string
    {
        $filename = $site_id . '_' . $torrent_id . '.txt';
        return self::$cacheNotify . $filename;
    }

    /**
     * 微信模板消息拼接方法
     * @return string           发送情况，json
     */
    protected static function wechatMessage()
    {
        $weixin = self::$conf['weixin'];
        // 1. 检查微信通知开关
        if (empty($weixin['switch'])) {
            return '';
        }
        // 2. 检查变化通知开关
        if (!empty($weixin['notify_on_change'])) {
            switch ($weixin['notify_on_change']) {
                case 'on':
                    if (self::$wechatMsg['reseedSuccess'] === 0 && self::$wechatMsg['reseedError'] === 0) {
                        return '';
                    }
                    break;
                case 'only_success':
                    if (self::$wechatMsg['reseedSuccess'] === 0) {
                        return '';
                    }
                    break;
                case 'only_fails':
                    if (self::$wechatMsg['reseedError'] === 0) {
                        return '';
                    }
                    break;
                case 'off':
                default:
                    break;
            }
        }
        $br = PHP_EOL;
        $text = 'IYUU自动辅种-统计报表';
        $desp = '### 版本号：' . IYUU_VERSION() . $br;
        $desp .= '**支持站点：' . self::$wechatMsg['sitesCount'] . '**  [当前支持自动辅种的站点数量]' . $br;
        $desp .= '**总做种：' . self::$wechatMsg['hashCount'] . '**  [客户端做种的hash总数]' . $br;
        $desp .= '**返回数据：' . self::$wechatMsg['reseedCount'] . '**  [服务器返回的可辅种数据]' . $br;
        $desp .= '**成功：' . self::$wechatMsg['reseedSuccess'] . '**  [会把hash加入辅种缓存]' . $br;
        $desp .= '**失败：' . self::$wechatMsg['reseedError'] . '**  [种子下载失败或网络超时引起]' . $br;
        $desp .= '**重复：' . self::$wechatMsg['reseedRepeat'] . '**  [客户端已做种]' . $br;
        $desp .= '**跳过：' . self::$wechatMsg['reseedSkip'] . '**  [未设置passkey]' . $br;
        $desp .= '**忽略：' . self::$wechatMsg['reseedPass'] . '**  [成功添加存在缓存]' . $br;
        // 失败详情
        if (self::$wechatMsg['reseedError']) {
            $desp .= '**失败详情，见 ./torrent/cache/reseedError.txt**' . $br;
        }
        // 重新辅种
        $desp .= '**如需重新辅种，请删除 ./torrent/cachehash 辅种缓存。**' . $br;
        $desp .= $br . '*此消息将在3天后过期*。';
        return self::ff($text, $desp);
    }
}
