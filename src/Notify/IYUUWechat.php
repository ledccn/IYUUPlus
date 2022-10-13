<?php

namespace IYUU\Notify;

use app\common\components\Curl as ICurl;

/**
 * 爱语飞飞微信模板消息通知
 */
class IYUUWechat implements INotify
{
    /**
     * @var string
     */
    private $token;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->token = $config['token'];
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
        return ICurl::http_post('https://iyuu.cn/' . $this->token . '.send', $data);
    }
}
