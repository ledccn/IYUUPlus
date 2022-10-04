<?php

namespace IYUU\Reseed\Events;

/**
 * 事件：客户端连接成功
 */
class ClientLinkSuccessEvent
{
    /**
     * 用户辅种的下载器
     * @var array
     */
    private $clients;

    /**
     * 下载器客户端连接
     * @var array
     */
    private $links;

    /**
     * @param array $clients
     * @param array $links
     */
    public function __construct(array $clients, array $links)
    {
        $this->clients = $clients;
        $this->links = $links;
    }

    /**
     * @return array
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * @return array
     */
    public function getLinks(): array
    {
        return $this->links;
    }
}
