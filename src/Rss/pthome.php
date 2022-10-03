<?php
/**
 * pthome RSS解码类
 */

namespace IYUU\Rss;

class pthome extends AbstractRss
{
    /**
     * 站点标志
     * @var string
     */
    public $site = 'pthome';

    /**
     * 初始化 第二步
     */
    public function init()
    {
        //站点配置
        $config = static::$conf['site'];
        $this->passkey = isset($config['downHash']) && $config['downHash'] ? $config['downHash'] : '';
        if (empty($this->passkey)) {
            die($this->site . ' 没有配置密钥，初始化错误。' . PHP_EOL);
        }
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
