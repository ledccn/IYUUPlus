<?php

namespace IYUU\Reseed\Listener;

use app\common\event\EventListenerInterface;
use IYUU\Reseed\Events\ClientLinkSuccessEvent;

/**
 * 事件监听器：监听客户端连接成功
 */
class ClientLinkSuccessListener implements EventListenerInterface
{
    public function events(): array
    {
        return [
            ClientLinkSuccessEvent::class
        ];
    }

    public function process(object $event): void
    {
        // TODO: Implement process() method.
    }
}
