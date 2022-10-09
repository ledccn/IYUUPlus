<?php

namespace IYUU\Notify;

use app\common\components\Curl as ICurl;

class IYUUWechat implements INotify
{
    /**
     * @var string
     */
    private $token;

    public function __construct(array $config)
    {
        $this->token = $config['token'];
    }

    public function send(string $title, string $content): bool
    {
        $desp = empty($content) ? date("Y-m-d H:i:s") : $content;
        $data = array(
            'text' => $title,
            'desp' => $desp,
        );
        return ICurl::http_post('https://iyuu.cn/' . $this->token . '.send', $data);
    }
}
