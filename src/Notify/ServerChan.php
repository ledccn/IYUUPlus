<?php

namespace IYUU\Notify;

use app\common\components\Curl as ICurl;

class ServerChan implements INotify
{
    /**
     * @var string
     */
    private $key;

    public function __construct(array $config)
    {
        $this->key = $config['key'];
    }

    public function send(string $title, string $content): bool
    {
        $desp = empty($content) ? date("Y-m-d H:i:s") : $content;
        $data = array(
            'text' => $title,
            'desp' => $desp,
        );
        return ICurl::http_post('https://sctapi.ftqq.com/' . $this->key . '.send', $data);
    }
}
