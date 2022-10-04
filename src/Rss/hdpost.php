<?php

namespace IYUU\Rss;

class hdpost extends AbstractRss
{
    /**
     * 站点标志
     * @var string
     */
    public $site = 'hdpost';

    /**
     * 种子下载前缀
     */
    public $downloadPrefix = 'torrents/download/';
    /**
     * 种子详情页前缀
     */
    public $detailsPrefix = 'torrents/';
    /**
     * RSS订阅页面
     */
    public $rss_page = 'rss/14.{passkey}';

    /**
     * 种子ID正则
     * @var string
     */
    public $torrent_id_regex = '#torrents/(\d+)#i';

    /**
     * 抽象方法，在类中实现
     * 请求url，获取html页面
     * @param string $url
     * @return string
     */
    public function get($url = '')
    {
        // 1. 入口参数为准
        if (!empty(static::$conf['urladdress'])) {
            $url = static::$conf['urladdress'];
        } else {
            $url = $this->rss_page;
        }

        // 2. 替换
        $url = str_replace("{rsskey}", static::getConfig('site.rsskey', ''), $url);
        echo $this->site . " 正在请求RSS... {$url}" . PHP_EOL;
        $url = (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) ? $url : $this->host . $url;
        $res = $this->curl->get($url);
        if ($res->http_status_code == 200) {
            echo "RSS获取信息，成功！ \n";
            return $res->response;
        }
        echo "RSS获取信息失败，请重试！ \n";
        return '';
    }

    /**
     * 抽象方法，在类中实现
     * 解码html为种子数组
     * @param string $html
     * @return array
     */
    public function decode($html = '')
    {
        echo "正在解码RSS资源..." . PHP_EOL;
        return $this->NexusPHP($html);
    }
}
