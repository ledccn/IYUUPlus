<?php
declare(strict_types=1);
namespace IYUU\Library\Event;

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
     * @param string $event
     */
    public function process(string $event): void;
}
