<?php

namespace IYUU\Notify;

/**
 * 定义通知接口
 */
interface INotify
{
    /**
     * 构造函数
     * @param array $config
     */
    public function __construct(array $config);

    /**
     * 发送通知
     * @param string $title
     * @param string $content
     * @return mixed
     */
    public function send(string $title, string $content);
}
