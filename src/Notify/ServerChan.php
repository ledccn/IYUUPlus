<?php

namespace IYUU\Notify;

use app\common\components\Curl as ICurl;

/**
 * Server酱
 */
class ServerChan implements INotify
{
    /**
     * @var string
     */
    private $key;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->key = $config['key'];
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
            'text' => $title,
            'desp' => $desp,
        );
        return ICurl::http_post('https://sctapi.ftqq.com/' . $this->key . '.send', $data);
    }
}
