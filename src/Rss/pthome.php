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
