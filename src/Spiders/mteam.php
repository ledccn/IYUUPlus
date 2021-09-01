<?php
namespace IYUU\Spiders;

use IYUU\Library\Selector;

class mteam extends SitesBase
{
    /**
     * 种子下载前缀
     */
    const downloadPrefix = 'download.php?id=';
    /**
     * 种子详情页前缀
     */
    const detailsPrefix = 'details.php?id=';
    // 网页编码
    const encoding = 'UTF-8';
    // 超时时间
    const CONNECTTIMEOUT = 30;
    const TIMEOUT = 600;
    /**
     * 获取的种子标志
     */
    public static $getTorrent = array('class="pro_free');
    /**
     * H&R 标志
     */
    public static $HR = array('class="hitandrun"','alt="H&amp;R"','title="H&amp;R"');
    /**
     * 解码后种子列表数组
     */
    public static $TorrentList = array();

    /**
     * 请求页面
     *
     * @param string        $url
     * @return array|null
     */
    public static function get($url = 'torrents.php')
    {
        if (!empty(static::$torrent_list_url)) {
            $url = static::$torrent_list_url;
        }
        return static::getTorrentListByNexusPHP($url);
    }

    /**
     * 解码
     *
     * @param array $data
     * @return array
     */
    public static function decode($data = array())
    {
        $url_join = static::getUrlJoin();
        $url_join = empty($url_join) ? '' : '&' . $url_join;
        foreach ($data as $k => $v) {
            $arr = array();
            // 种子id
            $regex = "/details.php\?id\=(\d+)/i";
            preg_match($regex, $v, $matchs_id);
            $arr['id'] = $matchs_id[1];
            // 种子地址
            $arr['url'] = self::downloadPrefix.$arr['id'];
            // 获取主标题
            $regex = '/<a title=[\'|\"](.*?)[\'|\"]/';
            if (preg_match($regex, $v, $matchs_h1)) {
                $arr['h1'] = $matchs_h1[1];
            } else {
                $arr['h1'] = '';
            }

            // 获取副标题(倒序算法)
            // 偏移量
            $h2StrStart = '<br />';
            $h2StrEnd = '</td><td width="80"';
            $h2_endOffset = strpos($v, $h2StrEnd);
            $temp = substr($v, 0, $h2_endOffset);
            $h2_offset = strrpos($temp, $h2StrStart);
            if ($h2_offset === false) {
                $arr['title'] = '';
            } else {
                $h2_startOffset = $h2_offset + strlen($h2StrStart);
                $h2_len = strlen($temp) - $h2_startOffset;
                //存在副标题
                $arr['title'] = substr($temp, $h2_startOffset, $h2_len);
                // 第二次过滤
                $arr['title'] = strip_tags($arr['title']);
            }

            // 组合返回数组
            static::$TorrentList[$k]['id'] = $arr['id'];
            static::$TorrentList[$k]['h1'] = $arr['h1'];
            static::$TorrentList[$k]['title'] = isset($arr['title']) && $arr['title'] ? $arr['title'] : '';
            static::$TorrentList[$k]['details'] = static::getHost() . static::detailsPrefix . $arr['id'];
            static::$TorrentList[$k]['download'] = static::getHost() . $arr['url'] . $url_join;
            static::$TorrentList[$k]['filename'] = $arr['id'].'.torrent';

            // 种子促销类型解码
            if (strpos($v, static::$getTorrent[0]) === false) {
                // 不免费
                static::$TorrentList[$k]['type'] = 1;
            } else {
                // 免费种子
                static::$TorrentList[$k]['type'] = 0;
            }
            // H&R检测
            foreach (static::$HR as $hrV) {
                if (strpos($v, $hrV) != false) {
                    static::$TorrentList[$k]['hr'] = 1;
                    // 删除
                    #unset( self::$TorrentList[$k] );
                    break;
                }
            }
            // 存活时间
            // 大小
            // 种子数
            // 下载数
            // 完成数
            // 完成进度
        }
        #p(self::$TorrentList);
        return self::$TorrentList;
    }
}
