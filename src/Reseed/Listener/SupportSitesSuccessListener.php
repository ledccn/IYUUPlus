<?php

namespace IYUU\Reseed\Listener;

use app\common\event\EventListenerInterface;
use IYUU\Reseed\Events\SupportSitesSuccessEvent;

/**
 * 事件监听器：监听支持站点列表获取成功之后
 */
class SupportSitesSuccessListener implements EventListenerInterface
{
    public function events(): array
    {
        return [
            SupportSitesSuccessEvent::class,
        ];
    }

    public function process(object $event): void
    {
        echo '事件监听器执行：' . __METHOD__;
    }
}
