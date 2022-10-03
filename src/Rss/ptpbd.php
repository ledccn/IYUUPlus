<?php

namespace IYUU\Rss;

use DOMDocument;
use Exception;

class ptpbd extends AbstractRss
{
    /**
     * 站点标志
     * @var string
     */
    public $site = 'ptpbd';

    /**
     * 用户必须自己配置rss地址
     * @var string
     */
    public $rss_page = '';

    /**
     * 抽象方法，在类中实现
     * 请求url，获取html页面
     * @return string|null
     */
    public function get()
    {
        return parent::get();
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
        try {
            $items = [];
            $xml = new DOMDocument();
            $xml->loadXML($html);
            $elements = $xml->getElementsByTagName('item');
            foreach ($elements as $item) {
                $this->filterNexusPHP($item);
                $link = $item->getElementsByTagName('link')->item(0)->nodeValue;
                $guid = $item->getElementsByTagName('guid')->item(0) != null ? md5($item->getElementsByTagName('guid')->item(0)->nodeValue) : md5($link);
                $details = $item->getElementsByTagName('comments')->item(0)->nodeValue;
                $time = strtotime($item->getElementsByTagName('pubDate')->item(0)->nodeValue);
                // 提取种子id
                if (preg_match('/id=(\d+)/i', $link, $match)) {
                    $id = $match[1];
                } else {
                    continue;
                }
                // 提取种子分组id
                if (preg_match('/id=(\d+)/i', $details, $match)) {
                    $group_id = $match[1];
                    $torrent['group_id'] = $group_id;
                }
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
            //p($items);
            //exit;
            return $items;
        } catch (Exception $e) {
            die(__METHOD__ . ' [ERROR] ' . $e->getMessage() . PHP_EOL);
        }
    }
}
