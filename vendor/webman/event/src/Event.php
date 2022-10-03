<?php

namespace Webman\Event;

use Psr\Log\LoggerInterface;
use support\Log;

class Event
{
    /**
     * @var array
     */
    protected static $eventMap = [];

    /**
     * @var array
     */
    protected static $prefixEventMap = [];

    /**
     * @var int
     */
    protected static $id = 0;

    /**
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * @param $event_name
     * @param $callback
     * @return int
     */
    public static function on($event_name, callable $callback): int
    {
        $is_prefix_name = $event_name[strlen($event_name)-1] === '*';
        if ($is_prefix_name) {
            static::$prefixEventMap[substr($event_name, 0, -1)][++static::$id] = $callback;
        } else {
            static::$eventMap[$event_name][++static::$id] = $callback;
        }
        return static::$id;
    }

    /**
     * @param $event_name
     * @param $id
     * @return int
     */
    public static function off($event_name, int $id): int
    {
        if (isset(static::$eventMap[$event_name][$id])) {
            unset(static::$eventMap[$event_name][$id]);
            return 1;
        }
        return 0;
    }

    /**
     * @param $event_name
     * @param $data
     * @return int
     */
    public static function emit($event_name, $data): int
    {
        $success_count = 0;
        $callbacks = static::$eventMap[$event_name]??[];
        foreach (static::$prefixEventMap as $name => $callback_items) {
            if (strpos($event_name, $name) === 0) {
                $callbacks = array_merge($callbacks, $callback_items);
            }
        }
        ksort($callbacks);
        foreach ($callbacks as $callback) {
            try {
                $ret = $callback($data, $event_name);
                $success_count++;
            } catch (\Throwable $e) {
                if (!static::$logger && is_callable('\support\Log::error')) {
                    static::$logger = Log::channel();
                }
                if (static::$logger) {
                    static::$logger->error($e);
                }
                continue;
            }
            if ($ret === false) {
                return $success_count;
            }
        }
        return $success_count;
    }

    /**
     * @return array
     */
    public static function list(): array
    {
        $callbacks = [];
        foreach (static::$eventMap as $event_name => $callback_items) {
            foreach ($callback_items as $id => $callback_item) {
                $callbacks[$id] = [$event_name, $callback_item];
            }
        }
        foreach (static::$prefixEventMap as $event_name => $callback_items) {
            foreach ($callback_items as $id => $callback_item) {
                $callbacks[$id] = [$event_name.'*', $callback_item];
            }
        }
        ksort($callbacks);
        return $callbacks;
    }
}