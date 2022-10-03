<?php
declare(strict_types=1);

namespace app\common\event;

/**
 * 事件监听器接口
 * Interface EventListenerInterface
 */
interface EventListenerInterface
{
    /**
     * 监听的事件
     * @return array
     */
    public function events(): array;

    /**
     * 处理事件
     * @param object $event
     */
    public function process(object $event): void;
}
