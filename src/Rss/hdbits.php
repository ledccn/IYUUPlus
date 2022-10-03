<?php

namespace IYUU\Rss;

use DOMDocument;

class hdbits extends AbstractRss
{
    /**
     * 站点标志
     * @var string
     */
    public $site = 'hdbits';
    /**
     * 种子下载前缀
     */
    public $downloadPrefix = 'download.php?id=';
    /**
     * 种子详情页前缀
     */
    public $detailsPrefix = 'details.php?id=';
    /**
     * RSS订阅页面
     */
    public $rss_page = 'rss/feed?passkey={}';

    /**
     * 抽象方法，在类中实现
     * 请求url，获取html页面
     * @param string $url
     * @return array
     */
    public function get($url = '')
    {
        if ($url == '') {
            $url = $this->rss_page;
        }
        $url = str_replace("{}", $this->passkey, $url);
        echo $this->site . " 正在请求RSS... {$url}" . PHP_EOL;
        $res = $this->curl->get($this->host . $url);
        if ($res->http_status_code == 200) {
            if (strpos((string)$res->response, 'Authentication failed') !== false) {
                echo 'passkey填写错误，请重新填写！';
                return null;
            }
            echo "RSS获取信息，成功！ \n";
            return $res->response;
        }
        echo "RSS获取信息失败，请重试！ \n";
        return null;
    }

    /**
     * 抽象方法，在类中实现
     * 解码html为种子数组
     * @param string $html
     * @return array
     * )
     */
    public function decode($html = '')
    {
        echo "正在解码RSS资源..." . PHP_EOL;
        try {
            $items = [];
            $xml = new DOMDocument();
            $xml->loadXML($html);
            $elements = $xml->getElementsByTagName('item');
            foreach ($elements as $item) {
                $this->filterNexusPHP($item);
                $id = $item->getElementsByTagName('guid')->item(0)->nodeValue;
                $details = $item->getElementsByTagName('link')->item(0)->nodeValue;
                $link = $item->getElementsByTagName('enclosure')->item(0) != null ? $item->getElementsByTagName('enclosure')->item(0)->getAttribute('url') : $item->getElementsByTagName('link')->item(0)->nodeValue;
                $guid = md5($link);
                $time = strtotime($item->getElementsByTagName('pubDate')->item(0)->nodeValue);
                $torrent['id'] = $id;
                $torrent['h1'] = $item->getElementsByTagName('title')->item(0)->nodeValue;
                $torrent['title'] = '';
                $torrent['details'] = $details;
                $torrent['download'] = $link;
                $torrent['filename'] = $id . '.torrent';
                $torrent['type'] = 0;   // 免费0
                $torrent['time'] = date("Y-m-d H:i:s", $time);
                $torrent['guid'] = $guid;
                $items[] = $torrent;
            }
            #p($items);
            #exit;
            return $items;
        } catch (\Exception $e) {
            die('[AbstractRss ERROR] ' . $e->getMessage() . PHP_EOL);
        }
    }
}
