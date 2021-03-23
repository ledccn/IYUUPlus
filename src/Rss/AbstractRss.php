<?php
/**
 * 站点RSS解码抽象类
 */
namespace IYUU\Rss;

use DOMDocument;
use Curl\Curl;
use IYUU\Library\Rpc;
use app\domain\Rss as domainRss;

abstract class AbstractRss
{
    /**
     * 站点标志
     * @var string
     */
    public $site = '';
    /**
     * 域名
     * @var string
     */
    public $domain = '';
    public $host = 'https://{}/';
    /**
     * 下载种子的请求类型
     */
    public $method = 'GET';
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
    // 网页编码
    const encoding = 'UTF-8';
    // 超时时间
    const CONNECTTIMEOUT = 30;
    const TIMEOUT = 600;
    /**
     * curl
     * @var Curl
     */
    public $curl = null;
    /**
     * cookie
     */
    public $cookies = '';
    /**
     * 浏览器 User-Agent
     */
    public $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36';
    /**
     * passkey
     */
    public $passkey = '';

    /**
     * 运行时解析的配置
     * @var array
     */
    protected static $conf = [];

    /**
     * 站点名转换为文件名，所使用的映射表
     */
    const SITENAME_TO_FILENAME_MAP = [
        '1ptba' => 'ptba',
        '52pt'  => 'site52pt',
        'm-team'=> 'mteam',
        'hd-torrents'=> 'hdtorrents',
    ];

    /**
     * 实例化
     * @param string $uuid      任务标识
     * @return mixed 返回站点的rss解码实例
     */
    public static function getInstance($uuid)
    {
        $filename = self::getCliInput($uuid);
        // 转小写
        $filename = strtolower($filename);
        $file = __DIR__ . DIRECTORY_SEPARATOR .$filename.'.php';
        if (!is_file($file)) {
            die($file.' 文件不存在');
        }
        $className = 'IYUU\Rss\\'.$filename;
        if (class_exists($className)) {
            echo $filename." RSS解码类正在实例化！".PHP_EOL;
            return new $className();
        } else {
            die($filename.' RSS解码类不存在');
        }
    }

    /**
     * 解析命令行参数 【静态方法】
     * @param string $uuid 任务标识
     * @return string 类文件名
     */
    protected static function getCliInput($uuid)
    {
        self::$conf = domainRss::configParser($uuid);
        if (empty(self::$conf['site'])) {
            die('解析计划任务失败：用户未配置的站点。'.PHP_EOL);
        }
        if (empty(self::$conf['sites'])) {
            die('解析计划任务失败：用户配置的站点，当前不受支持。'.PHP_EOL);
        }
        if (empty(self::$conf['clients'])) {
            die('解析计划任务失败：当前下载器可能已经删除，请编辑RSS下载任务，重选下载器。'.PHP_EOL);
        }
        echo microtime(true).' 命令行参数解析完成！'.PHP_EOL;
        //cli(self::$conf);
        $siteName = self::$conf['site']['name'];
        return isset(self::SITENAME_TO_FILENAME_MAP[$siteName]) ? self::SITENAME_TO_FILENAME_MAP[$siteName] : $siteName;
    }

    /**
     * 构造方法，配置应用信息
     * @param bool $init
     */
    final public function __construct($init = true)
    {
        if ($init) {
            echo $this->site." 正在初始化RSS配置...". PHP_EOL;
            cli(self::$conf);
            $this->_initialize();
            $this->init();
            echo $this->site." RSS解码类实例化，成功！".PHP_EOL;
        }
    }

    /**
     * 初始化 第一步
     */
    final protected function _initialize()
    {
        //常规配置
        $default = empty(static::$conf['default']) ? [] : static::$conf['default'];
        $this->userAgent = isset($default['ua']) && $default['ua'] ? $default['ua'] : $this->userAgent;

        //云端下发
        $sites = static::$conf['sites'];
        $protocol = isset($sites['is_https']) && ($sites['is_https'] === 0) ? 'http://' : 'https://';
        $this->domain = $sites['base_url'];
        $this->host = $protocol . $this->domain . '/';   // 示例：https://baidu.com/

        // 初始化curl
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false); // 禁止验证对等证书
        $this->curl->setOpt(CURLOPT_SSL_VERIFYHOST, 2);     // 检查证书
        $this->curl->setOpt(CURLOPT_CONNECTTIMEOUT, self::CONNECTTIMEOUT);  // 超时
        $this->curl->setOpt(CURLOPT_TIMEOUT, self::TIMEOUT);                // 超时
        $this->curl->setUserAgent($this->userAgent);
    }

    /**
     * 初始化 第二步
     */
    protected function init()
    {
        //站点配置
        $config = static::$conf['site'];
        $this->cookies = isset($config['cookie']) && $config['cookie'] ? $config['cookie'] : '';
        $this->passkey = isset($config['passkey']) && $config['passkey'] ? $config['passkey'] : '';
        if (empty($this->passkey)) {
            die($this->site.' 没有配置密钥，初始化错误。'.PHP_EOL);
        }
    }

    /**
     * 助手函数
     * @param DOMDocument $element
     * @param string $tagName
     * @param string $attName
     * @return mixed
     */
    protected function getXml($element, $tagName='', $attName='')
    {
        if (empty($attName)) {
            return $element->getElementsByTagName($tagName);
        }
        $tag = $element->getElementsByTagName($tagName)->item(0);
        if ($tag->hasAttributes()) {
            return $tag->getAttribute($attName);
        } else {
            return null;
        }
    }

    /**
     * 过滤XML文档中不需要的元素
     * @param DOMDocument $item
     * @return mixed
     */
    protected function filterNexusPHP(&$item)
    {
        $node = $item->getElementsByTagName('description')->item(0);
        if ($node != null) {
            return $item->removeChild($node);
        }
        return $item;
    }

    /**
     * NexusPHP通用RSS解码
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
                $this->filterNexusPHP($item);
                $link = $item->getElementsByTagName('enclosure')->item(0) != null ? $item->getElementsByTagName('enclosure')->item(0)->getAttribute('url') : $item->getElementsByTagName('link')->item(0)->nodeValue;
                $guid = $item->getElementsByTagName('guid')->item(0) != null ? $item->getElementsByTagName('guid')->item(0)->nodeValue : md5($link);
                $details = $item->getElementsByTagName('link')->item(0)->nodeValue;
                $time = strtotime($item->getElementsByTagName('pubDate')->item(0)->nodeValue);
                $length = $item->getElementsByTagName('enclosure')->item(0)->getAttribute('length');
                // 提取id
                if (preg_match('/id=(\d+)/i', $details, $match)) {
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
                $torrent['filename'] = $id.'.torrent';
                $torrent['type'] = 0;   // 免费0
                $torrent['time'] = date("Y-m-d H:i:s", $time);
                $torrent['size'] = getFilesize($length);
                $torrent['length'] = $length;
                $torrent['guid'] = $guid;
                $items[] = $torrent;
            }
            return $items;
        } catch (\Exception $e) {
            die('[AbstractRss NexusPHP ERROR] ' . $e->getMessage() . PHP_EOL);
        }
    }

    /**
     * 公共方法：实现rss订阅下载
     * @return void
     */
    public function run()
    {
        echo "正在初始化RPC链接...". PHP_EOL;
        Rpc::init($this->site, $this->method, self::$conf);
        $html = $this->get();
        #p($html);
        $this->checkCallback($html);
        $data = $this->decode($html);
        echo "已解码，正在推送给RPC下载器...". PHP_EOL;
        #p($data);exit;
        Rpc::call($data);
        exit(0);
    }

    /**
     * 请求url，获取html页面
     * @return string
     */
    public function get()
    {
        if (!empty(static::$conf['urladdress'])) {
            $url = static::$conf['urladdress'];
        } else {
            $url = $this->rss_page;
        }
        $url = str_replace("{}", $this->passkey, $url);
        echo $this->site." 正在请求RSS... {$url}". PHP_EOL;
        $url = (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) ? $url : $this->host . $url;
        $res = $this->curl->get($url);
        if ($res->http_status_code == 200) {
            echo "RSS获取信息，成功！". PHP_EOL;
            return $res->response;
        }
        echo "RSS获取信息失败，请重试！". PHP_EOL;
        return null;
    }

    /**
     * 回调函数
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
     * 抽象方法，在类中实现
     * 解码html为种子数组
     * @param string $html
     * @return array
     * Array
        (
            [id] => 118632
            [h1] => CCTV5+ 2019 ATP Men's Tennis Final 20191115B HDTV 1080i H264-HDSTV
            [title] => 央视体育赛事频道 2019年ATP男子网球年终总决赛 单打小组赛 纳达尔VS西西帕斯 20191115[优惠剩余时间：4时13分]
            [details] => https://xxx.me/details.php?id=118632
            [download] => https://xxx.me/download.php?id=118632
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
     */
    abstract public function decode($html = '');
}
