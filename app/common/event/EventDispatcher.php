<?php
declare(strict_types=1);
namespace app\common\event;

use Throwable;

/**
 * 事件调度器
 * Class EventDispatcher
 */
class EventDispatcher implements ListenerProviderInterface, EventDispatcherInterface
{
    /**
     * @var EventListenerInterface
     */
    protected $eventListeners = [];

    /**
     * EventDispatcher constructor.
     * @param EventListenerInterface ...$listeners
     */
    public function __construct($listeners)
    {
        $eventListeners = [];
        foreach ($listeners as $listener) {
            foreach ($listener->events() as $event) {
                $eventListeners[$event][] = $listener;
            }
        }
        $this->eventListeners = $eventListeners;
    }

    /**
     * 检出当前事件的所有监听器
     *
     * @param object $event
     *   An event for which to return the relevant listeners.
     * @return iterable[callable]
     *   An iterable (array, iterator, or generator) of callables.  Each
     *   callable MUST be type-compatible with $event.
     */
    public function getListenersForEvent(object $event): iterable
    {
        $class     = get_class($event);
        $listeners = $this->eventListeners[$class] ?? [];
        $iterable  = [];
        foreach ($listeners as $listener) {
            $iterable[] = [$listener, 'process'];
        }
        return $iterable;
    }

    /**
     * 派发当前事件到所有监听器的process处理方法
     *
     * @param object $event     当前事件对象
     * @return string
     *   The Event that was passed, now modified by listeners.
     */
    public function dispatch(object $event)
    {
        foreach ($this->getListenersForEvent($event) as $callback) {
            try {
                call_user_func($callback, $event);
            } catch (Throwable $ex) {
                //Logger::error(__METHOD__ . '调度异常：' . $ex->getMessage());
            }
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }
        return $event;
    }
}
