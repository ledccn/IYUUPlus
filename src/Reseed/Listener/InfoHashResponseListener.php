<?php

namespace IYUU\Reseed\Listener;

use app\common\event\EventListenerInterface;
use IYUU\Reseed\Events\InfoHashResponseEvent;

/**
 * 事件监听器：监听请求服务器辅种接口，返回可辅种的infoHash
 */
class InfoHashResponseListener implements EventListenerInterface
{
    public function events(): array
    {
        return [
            InfoHashResponseEvent::class,
        ];
    }

    public function process(object $event): void
    {
        echo '事件监听器执行：' . __METHOD__;
    }
}
