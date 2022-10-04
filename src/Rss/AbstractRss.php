<?php

namespace IYUU\Rss;

use app\common\Constant;
use app\domain\ConfigParser\Rss as domainRss;
use Curl\Curl;
use DOMDocument;
use DOMNode;
use IYUU\Library\Rpc;
use function explode;

abstract class AbstractRss
{
    const encoding = 'UTF-8';
    const CONNECTTIMEOUT = 30;
    const TIMEOUT = 600;
    /**
     * 站点名转换为文件名，所使用的映射表
     */
    const SITENAME_TO_FILENAME_MAP = Constant::SITENAME_TO_FILENAME_MAP;
    /**
     * 运行时解析的配置
     * @var array
     */
    protected static $conf = [];
    // 网页编码
    /**
     * 站点标志
     * @var string
     */
    public $site = '';
    // 超时时间
    /**
     * 域名
     * @var string
     */
    public $host = 'https://{}/';
    /**
     * 种子下载前缀
     */
    public $downloadPrefix = 'download.php?id=';
    /**
     * 种子详情页前缀
     */
    public $detailsPrefix = 'details.php?id=';
    /**
     * RSS订阅的默认页面
     */
    public $rss_page = 'torrentrss.php?rows=50&linktype=dl&passkey={}';
    /**
     * curl
     * @var Curl
     */
    public $curl = null;
    /**
     * passkey
     */
    public $passkey = '';
    /**
     * 种子ID正则
     * @var string
     */
    public $torrent_id_regex = '/id=(\d+)/i';

    /**
     * 构造方法，配置应用信息
     * @param bool $init 是否初始化（domainRss获取全部站点名时候，需要到）
     */
    final public function __construct(bool $init = true)
    {
        if ($init) {
            echo $this->site . " 正在初始化RSS配置..." . PHP_EOL;
            //cli(self::$conf);
            $this->_initialize();
            $this->init();
            echo $this->site . " RSS解码类实例化，成功！" . PHP_EOL;
        }
    }

    /**
     * 初始化 第一步，初始化父类的关键参数
     */
    final protected function _initialize()
    {
        //云端下发
        $this->host = static::getHost();   // 示例：https://baidu.com/

        // 初始化curl
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false); // 禁止验证对等证书
        //$this->curl->setOpt(CURLOPT_SSL_VERIFYHOST, 2);     // 检查证书
        $this->curl->setOpt(CURLOPT_CONNECTTIMEOUT, self::CONNECTTIMEOUT);  // 超时
        $this->curl->setOpt(CURLOPT_TIMEOUT, self::TIMEOUT);                // 超时
        $this->curl->setOpt(CURLOPT_FOLLOWLOCATION, 1); // 自动跳转，跟随请求Location
        $this->curl->setOpt(CURLOPT_MAXREDIRS, 2);      // 递归次数
        $this->curl->setUserAgent(static::getUserAgent());
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
     * 初始化 第二步，子类可以重写此方法
     */
    protected function init()
    {
        //站点配置
        $config = static::$conf['site'];
        $this->passkey = !empty($config['passkey']) ? $config['passkey'] : '';
        if (empty($this->passkey)) {
            die($this->site . ' 没有配置密钥，初始化错误。' . PHP_EOL);
        }
    }

    /**
     * 实例化
     * @param string $uuid 任务标识
     * @return mixed 返回站点的rss解码实例
     */
    public static function getInstance(string $uuid)
    {
        $filename = self::getCliInput($uuid);
        // 转小写
        $filename = strtolower($filename);
        $file = __DIR__ . DIRECTORY_SEPARATOR . $filename . '.php';
        if (!is_file($file)) {
            die($file . ' 文件不存在');
        }
        $className = 'IYUU\\Rss\\' . $filename;
        if (class_exists($className)) {
            echo $filename . " RSS解码类正在实例化！" . PHP_EOL;
            return new $className();
        } else {
            die($filename . ' RSS解码类不存在');
        }
    }

    /**
     * 解析命令行参数 【静态方法】
     * @param string $uuid 任务标识
     * @return string 类文件名
     */
    private static function getCliInput(string $uuid): string
    {
        self::$conf = domainRss::parser($uuid);
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
            die('解析计划任务失败：当前下载器可能已经删除，请编辑RSS下载任务，重选下载器。' . PHP_EOL);
        }
        echo microtime(true) . ' 命令行参数解析完成！' . PHP_EOL;
        //cli(self::$conf);
        $siteName = self::$conf['site']['name'];
        return static::getFileName($siteName);
    }

    /**
     * 从站点名解析出类名（文件名）
     * @param string $site_name
     * @return string 类文件名
     */
    public static function getFileName(string $site_name): string
    {
        return self::SITENAME_TO_FILENAME_MAP[$site_name] ?? $site_name;
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
        $key_array = explode('.', $key);
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
     * 公共方法：实现rss订阅下载，子类可以重写此方法
     * @return void
     */
    public function run()
    {
        echo "正在初始化RPC链接..." . PHP_EOL;
        Rpc::init($this->site, static::getTorrentDownloadMethod($this->site), self::$conf);
        $html = $this->get();
        #cli($html);
        $this->checkCallback($html);
        $data = $this->decode($html);
        echo "已解码，正在推送给RPC下载器..." . PHP_EOL;
        //cli($data);exit;
        Rpc::call($data);
        exit(0);
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
     * 请求url，获取html页面，子类可以重写此方法
     * @return string
     */
    public function get()
    {
        if (!empty(static::$conf['urladdress'])) {
            $url = static::$conf['urladdress'];
        } else {
            $url = $this->rss_page;
        }
        if (empty($url)) {
            die('缺少 rss.page 配置');
        }
        $url = str_replace("{}", $this->passkey, $url);
        echo $this->site . " 正在请求RSS... {$url}" . PHP_EOL;
        $url = (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) ? $url : $this->host . $url;
        $res = $this->curl->get($url);
        //cli($res);exit;
        if ($res->http_status_code == 200) {
            echo "RSS获取信息，成功！" . PHP_EOL;
            return $res->response;
        }
        echo "RSS获取信息失败，请重试！" . PHP_EOL;
        return null;
    }

    /**
     * 回调函数，子类可以重写此方法
     * @param string $html
     */
    public function checkCallback($html = '')
    {
        if (strpos((string)$html, 'invalid passkey') !== false) {
            die('passkey填写错误，请重新填写！');
        }
        if (is_null($html)) {
            exit(1);
        }
    }

    /**
     * 抽象方法，在子类中实现
     * 解码html为种子数组
     * @param string $html
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
    abstract public function decode($html = '');

    /**
     * NexusPHP通用RSS解码，子类可以重写此方法
     * @param string $html
     * @return array
     */
    protected function NexusPHP($html = '')
    {
        try {
            $items = [];
            $xml = new DOMDocument();
            $xml->loadXML($html);
            $elements = $xml->getElementsByTagName('item');
            foreach ($elements as $item) {
                $item = $this->filterNexusPHP($item);
                $link = $item->getElementsByTagName('enclosure')->item(0) != null ? $item->getElementsByTagName('enclosure')->item(0)->getAttribute('url') : $item->getElementsByTagName('link')->item(0)->nodeValue;
                $guid = $item->getElementsByTagName('guid')->item(0) != null ? $item->getElementsByTagName('guid')->item(0)->nodeValue : md5($link);
                $details = $item->getElementsByTagName('link')->item(0)->nodeValue;
                $time = strtotime($item->getElementsByTagName('pubDate')->item(0)->nodeValue);
                $length = $item->getElementsByTagName('enclosure')->item(0)->getAttribute('length');
                // 提取id
                if (preg_match($this->torrent_id_regex, $details, $match)) {
                    $id = $match[1];
                } else {
                    continue;
                }
                $torrent = [];
                $torrent['id'] = $id;
                $torrent['h1'] = $item->getElementsByTagName('title')->item(0)->nodeValue;
                $torrent['title'] = '';
                $torrent['details'] = $details;
                $torrent['download'] = $link;
                $torrent['filename'] = $id . '.torrent';
                $torrent['type'] = 0;   // 免费0
                $torrent['time'] = date("Y-m-d H:i:s", $time);
                $torrent['size'] = dataSize($length);
                $torrent['length'] = $length;
                $torrent['guid'] = $guid;
                $items[] = $torrent;
            }
            return $items;
        } catch (\Exception $e) {
            die(__METHOD__ . '[ERROR] ' . $e->getMessage() . PHP_EOL);
        }
    }

    /**
     * 过滤XML文档中不需要的元素，子类可以重写此方法
     * @param DOMDocument $item
     * @return DOMDocument | DOMNode
     */
    protected function filterNexusPHP($item)
    {
        $node = $item->getElementsByTagName('description')->item(0);
        if ($node != null) {
            $item->removeChild($node);
        }
        return $item;
    }
}
