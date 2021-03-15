<?php
/**
 * 站点RSS解码抽象类
 */
namespace IYUU\Rss;

use Curl\Curl;
use DOMDocument;
use IYUU\Library\Rpc;

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
     * 构造方法，配置应用信息
     * @param bool $init
     */
    public function __construct($init = true)
    {
        if ($init) {
            echo $this->site." 正在初始化RSS配置...". PHP_EOL;
            $this->init();
            echo $this->site." RSS解码类实例化，成功！".PHP_EOL;
        }
    }

    /**
     * 实例化
     * @param string $site      站点名字
     * @return mixed 返回站点的rss解码实例
     */
    public static function getInstance($site)
    {
        // 转小写
        $name = strtolower($site);
        $file = __DIR__ . DIRECTORY_SEPARATOR .$name.'.php';
        if (!is_file($file)) {
            die($file.' 文件不存在');
        }
        $className = 'IYUU\Rss\\'.$name;
        if (class_exists($className)) {
            echo $name." RSS解码类正在实例化！".PHP_EOL;
            return new $className();
        } else {
            die($name.' RSS解码类不存在');
        }
    }

    /**
     * 初始化配置
     */
    protected function init()
    {
        global $argv;
        global $configALL;
        if (!isset($configALL[$this->site])) {
            die('config.php缺少'.$this->site.'站点配置，实例化RSS解码类失败'.PHP_EOL);
        }
        $config = $configALL[$this->site];
        $this->cookies = isset($config['cookie']) && $config['cookie'] ? $config['cookie'] : '';
        $this->userAgent = isset($configALL['default']['userAgent']) && $configALL['default']['userAgent'] ? $configALL['default']['userAgent'] : $this->userAgent;
        $this->passkey = isset($config['passkey']) && $config['passkey'] ? $config['passkey'] : '';
        if (empty($this->passkey)) {
            die($this->site.' 没有配置密钥，初始化错误。'.PHP_EOL);
        }
        if (!isset($configALL['sitesALL'][$this->site])) {
            die($this->site.' 缺少JSON文件，实例化RSS解码类失败'.PHP_EOL);
        }
        $this->domain = $configALL['sitesALL'][$this->site]['base_url'];
        $this->host = str_replace("{}", $this->domain, $this->host);
        // 初始化curl
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false); // 禁止验证对等证书
        $this->curl->setOpt(CURLOPT_SSL_VERIFYHOST, 2);     // 检查证书
        $this->curl->setOpt(CURLOPT_CONNECTTIMEOUT, self::CONNECTTIMEOUT);  // 超时
        $this->curl->setOpt(CURLOPT_TIMEOUT, self::TIMEOUT);                // 超时
        $this->curl->setUserAgent($this->userAgent);
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
     * @param string    $url
     * @return void
     */
    public function run($url = '')
    {
        echo "正在初始化RPC链接...". PHP_EOL;
        Rpc::init($this->site, $this->method);
        $html = $this->get($url);
        #p($html);
        $this->checkCallback($html);
        $data = $this->decode($html);
        echo "已解码，正在推送给RPC下载器...". PHP_EOL;
        #p($data);exit;
        Rpc::call($data);
        exit(0);
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
     * 请求url，获取html页面
     * @param string        $url
     * @return array
     */
    abstract public function get($url = '');

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
