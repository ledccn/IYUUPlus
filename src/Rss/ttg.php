<?php
/**
 * totheglory RSS解码类
 */

namespace IYUU\Rss;

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
     * RSS专用密钥
     * @var string
     */
    public $down_hash = '';

    public function init()
    {
        //站点配置
        $config = static::$conf['site'];
        $this->passkey = !empty($config['passkey']) ? $config['passkey'] : '';
        $this->down_hash = !empty($config['downHash']) ? $config['downHash'] : '';
        if (empty($this->down_hash)) {
            die($this->site . ' 没有配置RSS专用密钥，请去RSS订阅页面生成[putrss.php?par={密钥在这里}&ssl=yes]，初始化错误。' . PHP_EOL);
        }
        if (empty($this->passkey)) {
            die($this->site . ' 没有配置密钥，初始化错误。' . PHP_EOL);
        }
        $this->rss_page = str_replace("{}", $this->down_hash, $this->rss_page);
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

    /**
     * 辅种时添加种子的方法
     * @param array $items
     * @return array
     */
    public function formatTorrent(array $items): array
    {
        $host = static::getHost();
        $passkey = static::getConfig('site.passkey');
        $download_page = static::getConfig('sites.download_page');
        foreach ($items as $k => &$torrent) {
            $id = $torrent['id'];
            $torrent['download'] = $host . str_replace(['{}', '{passkey}'], [$id, $passkey], $download_page);
        }
        return $items;
    }
}
