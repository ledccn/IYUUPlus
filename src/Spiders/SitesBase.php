<?php

namespace IYUU\Spiders;

use app\common\Constant;
use app\domain\ConfigParser\Spiders as domainSpiders;
use IYUU\Library\Rpc;
use IYUU\Library\Selector;

/**
 * PT资源站基类
 */
class SitesBase
{
    /**
     * 站点名转换为文件名，所使用的映射表
     */
    const SITENAME_TO_FILENAME_MAP = Constant::SITENAME_TO_FILENAME_MAP;
    /**
     * 当前站点名
     * @var string
     */
    protected static $site_name = '';
    /**
     * 种子列表页URL
     * @var string
     */
    protected static $torrent_list_url = '';
    /**
     * 运行时解析的配置
     * @var array
     */
    protected static $conf = [];

    /**
     * 获取站点爬虫类完整名称，含命名空间
     * @param string $uuid 任务标识
     * @return string|SitesBase 返回站点解码类完整名称（含命名空间）
     */
    public static function getSpidersClass($uuid)
    {
        $filename = self::getCliInput($uuid);
        // 转小写
        $filename = strtolower($filename);
        $file = __DIR__ . DIRECTORY_SEPARATOR . $filename . '.php';
        if (!is_file($file)) {
            die($file . ' 文件不存在');
        }
        $className = 'IYUU\\Spiders\\' . $filename;
        if (class_exists($className)) {
            echo $filename . " 站点解码类存在" . PHP_EOL;
            return $className;
        } else {
            die($filename . ' 站点解码类不存在');
        }
    }

    /**
     * 解析命令行参数
     * @param string $uuid 任务标识
     * @return string 类文件名
     */
    public static function getCliInput($uuid)
    {
        self::$conf = domainSpiders::parser($uuid);
        if (empty(self::$conf)) {
            die('当前任务不存在或者未开启。' . PHP_EOL);
        }
        if (empty(self::$conf['site'])) {
            die('解析计划任务失败：用户未配置的站点。' . PHP_EOL);
        }
        if (empty(self::$conf['sites'])) {
            die('解析计划任务失败：用户配置的站点，当前不受支持。' . PHP_EOL);
        }
        if (empty(self::$conf['clients'])) {
            die('解析计划任务失败：当前下载器可能已经删除，请编辑站点爬虫下载任务，重选下载器。' . PHP_EOL);
        }
        echo microtime(true) . ' 命令行参数解析完成！' . PHP_EOL;
        //cli(self::$conf, true);
        /**
         * 初始化最关键的2个参数
         */
        // 站点标识
        $siteName = self::$conf['site']['name'];
        self::$site_name = $siteName;
        // 种子列表页URL
        if (!empty(self::$conf['urladdress'])) {
            self::$torrent_list_url = self::$conf['urladdress'];
        }
        return static::getFileName($siteName);
    }

    /**
     * 从站点名解析出类名（文件名）
     * @param string $site_name
     * @return string 类文件名
     */
    public static function getFileName(string $site_name): string
    {
        return isset(self::SITENAME_TO_FILENAME_MAP[$site_name]) ? self::SITENAME_TO_FILENAME_MAP[$site_name] : $site_name;
    }

    /**
     * 获取配置
     * @param null $key 配置键值
     * @param null $default 默认
     * @return array|mixed|null
     */
    public static function getConfig($key = null, $default = null)
    {
        if ($key === null) {
            return self::$conf;
        }
        $key_array = \explode('.', $key);
        $value = self::$conf;
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }

    /**
     * 从类名解析出站点名（配置里站点的键名）
     * @param string $class_name
     * @return string
     */
    public static function getSiteName(string $class_name): string
    {
        $classname_to_sitename_map = array_flip(static::SITENAME_TO_FILENAME_MAP);
        if (array_key_exists($class_name, $classname_to_sitename_map)) {
            $siteName = $classname_to_sitename_map[$class_name];
        } else {
            $siteName = $class_name;
        }
        return $siteName;
    }

    /**
     * 获取种子详情页
     * @access public
     * @param string $url
     * @return string
     */
    public static function getTorrentDetails(string $url = ''): string
    {
        $host = self::getHost();
        $cookie = self::getCookie();
        $user_agent = self::getUserAgent();
        $url = (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) ? $url : $host . $url;

        echo '正在抓取详情页：' . $url . PHP_EOL;
        $details_html = download($url, $cookie, $user_agent);
        return static::checkDetailsHtml($details_html);
    }

    /**
     * 获得当前站点HOST
     * @return string
     */
    protected static function getHost(): string
    {
        //站点配置
        $sites = static::$conf['sites'];
        $protocol = isset($sites['is_https']) && ($sites['is_https'] === 0) ? 'http://' : 'https://';
        $domain = $sites['base_url'];
        return $protocol . $domain . '/';   // 示例：https://baidu.com/
    }

    /**
     * 获得站点配置的cookie
     * @return string
     */
    protected static function getCookie(): string
    {
        //站点配置
        $config = self::$conf['site'];
        $cookie = !empty($config['cookie']) ? $config['cookie'] : '';
        if (empty($cookie)) {
            die('站点：' . self::$site_name . '，配置缺失或cookie未配置' . PHP_EOL . PHP_EOL);
        }
        return $cookie;
    }

    /**
     * 获得用户浏览器UA
     * @return string
     */
    protected static function getUserAgent(): string
    {
        //常规配置
        $default = empty(static::$conf['default']) ? [] : static::$conf['default'];
        return !empty($default['ua']) ? $default['ua'] : Constant::UserAgent;
    }

    /**
     * 检查详情页html
     * - 子类应该实现检测方法
     * @param string $details_html
     * @return string
     */
    protected static function checkDetailsHtml(string $details_html = ''): string
    {
        if (strpos($details_html, '没有该ID的种子') !== false) {
            echo '种子已被删除！' . PHP_EOL;
            return '';
        }
        return $details_html;
    }

    /**
     * 获得种子列表页
     * - 适用于原生NexusPHP
     * @param string $url
     * @return array|null
     */
    public static function getTorrentListByNexusPHP(string $url)
    {
        $host = self::getHost();
        $cookie = self::getCookie();
        $user_agent = self::getUserAgent();
        $url = (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) ? $url : $host . $url;

        echo '正在抓取种子列表页：' . $url . PHP_EOL;
        $html = download($url, $cookie, $user_agent);
        $data = Selector::select($html, "//*[@class='torrentname']");
        if (!$data) {
            echo "登录信息过期，请重新设置！ \n";
            return null;
        }
        return $data;
    }

    /**
     * 执行
     *
     * @param string
     */
    public static function run()
    {
        //cli(static::$conf, true);exit;
        echo "正在初始化RPC链接..." . PHP_EOL;
        Rpc::init(static::$site_name, static::getTorrentDownloadMethod(static::$site_name), static::$conf);
        $html = static::get();
        if (empty($html)) {
            exit(1);
        }
        $data = static::decode($html);
        //cli($data, true);exit;
        Rpc::call($data);
        exit(0);
    }

    /**
     * 初始化配置
     */
    public static function init()
    {
        // TODO: Implement init() method.
    }

    /**
     * 取站点下载种子时使用的方法(post/get)
     * @param string $site_name
     * @return string
     */
    public static function getTorrentDownloadMethod(string $site_name): string
    {
        $method = Constant::SITE_DOWNLOAD_METHOD_POST;
        return in_array($site_name, $method) ? 'POST' : 'GET';
    }

    /**
     * 接口方法，在类中实现
     * 请求url，获取html页面
     * @param string $url
     * @return array|null
     */
    public static function get($url = 'torrents.php')
    {
        // TODO: Implement get() method.
        return null;
    }

    /**
     * 接口方法，在类中实现
     * 解码html为种子数组
     * @param array|string $html
     * @return array
     * Array
     * (
     * [id] => 118632
     * [h1] => CCTV5+ 2019 ATP Men's Tennis Final 20191115B HDTV 1080i H264-HDSTV
     * [title] => 央视体育赛事频道 2019年ATP男子网球年终总决赛 单打小组赛 纳达尔VS西西帕斯 20191115[优惠剩余时间：4时13分]
     * [details] => https://xxx.me/details.php?id=118632
     * [download] => https://xxx.me/download.php?id=118632
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
     */
    public static function decode($html = array())
    {
        // TODO: Implement decode() method.
        return [];
    }

    /**
     * 获得用户配置中的站点下载种子时候的附加参数
     * - 例如：https=1&ipv6=1
     * @return string
     */
    protected static function getUrlJoin(): string
    {
        //站点配置
        $config = self::$conf['site'];
        if (!empty($config['url_join'])) {
            return http_build_query($config['url_join']);
        }
        return '';
    }
}
