<?php

namespace IYUU\Notify;

use app\common\components\Curl as ICurl;

/**
 * Bark通知
 */
class Bark implements INotify
{
    /**
     * @var string
     */
    private $bark_v2_server;
    /**
     * @var string
     */
    private $device_key;
    /**
     * @var string
     */
    private $group = 'IYUU';

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->bark_v2_server = $config['server'];
        $this->device_key = $config['device_key'];
        if (!empty($config['group']))
            $this->group = $config['group'];
    }

    /**
     * @param string $title
     * @param string $content
     * @return false|string
     */
    public function send(string $title, string $content)
    {
        $desp = empty($content) ? date("Y-m-d H:i:s") : $content;
        $data = array(
            "group" => $this->group,
            "title" => $title,
            "body" => $desp,
            "device_key" => $this->device_key,
            /*"sound" => "minuet.caf",
            "badge" => 1,
            "icon" => "https://xxxx.xx/avatar.jpg",
            "url" => "https://github.com/Finb"*/
        );
        return ICurl::http_post($this->bark_v2_server, $data, true);
    }
}
