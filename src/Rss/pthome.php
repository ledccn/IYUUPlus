<?php
/**
 * pthome RSS解码类
 */
namespace IYUU\Rss;

use Curl\Curl;
use DOMDocument;
use DOMXpath;
use IYUU\Library\Rpc;

class pthome extends AbstractRss
{
    /**
     * 站点标志
     * @var string
     */
    public $site = 'pthome';
    public $rss_page = 'torrentrss.php?rows=50&exp=180&linktype=dl&passkey={}';
    /**
     * 抽象方法，在类中实现
     * 请求url，获取html页面
     * @param string    $url
     * @return string
     */
    public function get($url = '')
    {
        global $configALL;
        $config = $configALL[$this->site];
        $passkey = isset($config['rss']) && $config['rss'] ? $config['rss'] : '';
        if (empty($passkey)) {
            die($this->site.' 没有配置密钥，初始化错误。'.PHP_EOL);
        }
        if ($url == '') {
            $url = $this->rss_page;
        }
        $url = str_replace("{}", $passkey, $url);
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
