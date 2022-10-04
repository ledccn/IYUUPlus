<?php

namespace IYUU\Reseed\Events;

/**
 * 事件：获取到下载器做种哈希
 */
class ClientHashSuccessEvent
{
    /**
     * @var array
     */
    private $hashArray;

    /**
     * @var array
     */
    private $linkClient;

    /**
     * 构造函数
     * @param array $hashArray 客户端做种哈希
     * @param array $linkClient 客户端连接
     */
    public function __construct(array $hashArray, array $linkClient)
    {
        $this->hashArray = $hashArray;
        $this->linkClient = $linkClient;
    }

    /**
     * @return array
     */
    public function getHashArray(): array
    {
        return $this->hashArray;
    }

    /**
     * @return array
     */
    public function getLinkClient(): array
    {
        return $this->linkClient;
    }

    /**
     * @param array $hashArray
     */
    public function setHashArray(array $hashArray): void
    {
        $this->hashArray = $hashArray;
    }
}
