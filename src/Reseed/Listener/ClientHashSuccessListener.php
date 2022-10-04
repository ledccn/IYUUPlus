<?php

namespace IYUU\Reseed\Listener;

use app\common\event\EventListenerInterface;
use IYUU\Reseed\Events\ClientHashSuccessEvent;

/**
 * 事件监听器：监听获取到下载器做种哈希
 */
class ClientHashSuccessListener implements EventListenerInterface
{
    public function events(): array
    {
        return [
            ClientHashSuccessEvent::class
        ];
    }

    public function process(object $event): void
    {
        echo '事件监听器执行：' . __METHOD__;
    }
}
