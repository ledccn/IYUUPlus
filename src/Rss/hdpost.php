<?php
namespace IYUU\Rss;

use DOMDocument;
use Exception;

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
     * 抽象方法，在类中实现
     * 请求url，获取html页面
     * @param string    $url
     * @return string
     */
    public function get($url = '')
    {
        // 1. 入口参数为准
        if ($url == '') {
            $url = $this->rss_page;
        }

        // 2. 替换
        $url = str_replace("{passkey}", $this->passkey, $url);
        echo $this->site." 正在请求RSS... {$url}". PHP_EOL;
        $res = $this->curl->get($this->host . $url);
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
     * @param string    $html
     * @return array
     */
    public function decode($html = '')
    {
        echo "正在解码RSS资源...". PHP_EOL;
        try {
            $items = [];
            $xml = new DOMDocument();
            $xml->loadXML($html);
            $elements = $xml->getElementsByTagName('item');
            foreach ($elements as $item) {
                $this->filterNexusPHP($item);
                $link = ($item->getElementsByTagName('enclosure')->item(0) != null) ? $item->getElementsByTagName('enclosure')->item(0)->getAttribute('url') : $item->getElementsByTagName('link')->item(0)->nodeValue;
                $guid = ($item->getElementsByTagName('guid')->item(0) != null) ? $item->getElementsByTagName('guid')->item(0)->nodeValue : md5($link);
                $details = $item->getElementsByTagName('link')->item(0)->nodeValue;
                $time = strtotime($item->getElementsByTagName('pubDate')->item(0)->nodeValue);
                $length = $item->getElementsByTagName('enclosure')->item(0)->getAttribute('length');
                // 提取id
                if (preg_match('#torrents/(\d+)#i', $details, $match)) {
                    $id = $match[1];
                } else {
                    continue;
                }
                $torrent['id'] = $id;
                $torrent['h1'] = $item->getElementsByTagName('title')->item(0)->nodeValue;
                $torrent['title'] = '';
                $torrent['details'] = $details;
                $torrent['download'] = $link;
                $torrent['filename'] = $id.'.torrent';
                $torrent['type'] = 0;   // 免费0
                $torrent['time'] = date("Y-m-d H:i:s", $time);
                $torrent['size'] = dataSize($length);
                $torrent['length'] = $length;
                $torrent['guid'] = $guid;
                $items[] = $torrent;
            }
            return $items;
        } catch (Exception $e) {
            die(__METHOD__ . '[ERROR] ' . $e->getMessage() . PHP_EOL);
        }
    }
}
