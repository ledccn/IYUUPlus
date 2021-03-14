<?php
/**
 * hdcity RSS解码类
 */
namespace IYUU\Rss;

use Curl\Curl;
use DOMDocument;
use DOMXpath;
use IYUU\Library\Rpc;

class hdcity extends AbstractRss
{
    /**
     * 站点标志
     * @var string
     */
    public $site = 'hdcity';
    /**
     * RSS订阅页面
     */
    public $rss_page = 'trss?rows=50&linktype=dl&passkey={}';
    /**
     * 初始化配置
     */
    public function init()
    {
        global $configALL;
        if (!isset($configALL[$this->site])) {
            throw new \Exception('缺少站点配置，实例化RSS解码类失败');
        }
        $config = $configALL[$this->site];
        $this->cookies = $config['cookie'];
        $this->userAgent = isset($config['userAgent']) && $config['userAgent'] ? $config['userAgent'] : $configALL['default']['userAgent'];
        $this->passkey = isset($config['rss']) ? $config['rss'] : '';
        if (!isset($configALL['sitesALL'])) {
            throw new \Exception('缺少站点JSON文件，实例化RSS解码类失败');
        }
        $this->domain = $configALL['sitesALL'][$this->site]['base_url'];
        $this->host = str_replace("{}", $this->domain, $this->host);
        // 初始化curl
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false); // 禁止验证证书
        $this->curl->setOpt(CURLOPT_SSL_VERIFYHOST, false); // 不检查证书
        $this->curl->setOpt(CURLOPT_CONNECTTIMEOUT, self::CONNECTTIMEOUT);  // 超时
        $this->curl->setOpt(CURLOPT_TIMEOUT, self::TIMEOUT);                // 超时
    }
    /**
     * 抽象方法，在类中实现
     * 请求url，获取html页面
     * @param string    $url
     * @return string
     */
    public function get($url = '')
    {
        if ($url == '') {
            $url = $this->rss_page;
        }
        $url = str_replace("{}", $this->passkey, $url);
        echo "正在请求RSS... {$url}". PHP_EOL;
        $res = $this->curl->get($this->host.$url);
        if ($res->http_status_code == 200) {
            echo "RSS获取信息，成功！ \n";
            return $res->response;
        }
        echo "RSS获取信息失败，请重试！ \n";
        return null;
    }

    /**
     * 抽象方法，在类中实现
     * 解码html为种子数组
     * @param string    $html
     * @return array
     */
    public function decode($html = '')
    {
        echo "正在解码RSS资源...". PHP_EOL;
        return $this->NexusPHP($html);
    }
}
