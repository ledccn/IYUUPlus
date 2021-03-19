<?php
/**
 * totheglory RSS解码类
 */
namespace IYUU\Rss;

use Curl\Curl;
use DOMDocument;
use DOMXpath;
use IYUU\Library\Rpc;

class ttg extends AbstractRss
{
    /**
     * 站点标志
     * @var string
     */
    public $site = 'ttg';
    /**
     * RSS订阅页面
     */
    public $rss_page = 'putrss.php?par={}&ssl=yes';
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
        $this->passkey = isset($config['rss']) && $config['rss'] ? $config['rss'] : '';
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
