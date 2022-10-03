<?php

namespace IYUU\Rss;

use DOMDocument;

class hdtorrents extends AbstractRss
{
    /**
     * 站点标志
     * @var string
     */
    public $site = 'hd-torrents';

    /**
     * 抽象方法，在类中实现
     * 解码html为种子数组
     * @param string $html
     * @return array
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
                $link = $item->getElementsByTagName('link')->item(0)->nodeValue;
                $guid = md5($link);
                $time = strtotime($item->getElementsByTagName('pubDate')->item(0)->nodeValue);
                // 提取id
                $offsetStr = 'hash=';
                $offset = strpos($link, $offsetStr) + strlen($offsetStr);
                $len = strpos($link, "&", $offset) - $offset;
                $id = substr($link, $offset, $len);
                $torrent['id'] = $id;
                $torrent['h1'] = $item->getElementsByTagName('title')->item(0)->nodeValue;
                $torrent['title'] = '';
                $torrent['details'] = '';
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
