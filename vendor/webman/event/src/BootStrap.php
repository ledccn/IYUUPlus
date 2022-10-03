<?php

namespace Webman\Event;

use support\Container;
use support\Log;

class BootStrap implements \Webman\Bootstrap
{
    /**
     * @var array
     */
    protected static $events = [];

    /**
     * @param $worker
     * @return mixed|void
     */
    public static function start($worker)
    {
        static::getEvents([config()]);
    }

    protected static function convertCallable($callback)
    {
        if (\is_array($callback)) {
            $callback = \array_values($callback);
            if (isset($callback[1]) && \is_string($callback[0]) && \class_exists($callback[0])) {
                $callback = [Container::get($callback[0]), $callback[1]];
            }
        }
        return $callback;
    }

    /**
     * @param $configs
     * @return void
     */
    protected static function getEvents($configs)
    {
        foreach ($configs as $config) {
            if (!is_array($config)) {
                continue;
            }
            if (isset($config['event']) && is_array($config['event']) && !isset($config['event']['app']['enable'])) {
                $events = [];
                foreach ($config['event'] as $event_name => $callbacks) {
                    $callbacks = static::convertCallable($callbacks);
                    if (is_callable($callbacks)) {
                        $events[$event_name] = [$callbacks];
                        Event::on($event_name, $callbacks);
                        continue;
                    }
                    if (!is_array($callbacks)) {
                        $msg = "Events: $event_name => " .var_export($callbacks, true) . " is not callable\n";
                        echo $msg;
                        Log::error($msg);
                        continue;
                    }
                    foreach ($callbacks as $callback) {
                        $callback = static::convertCallable($callback);
                        if (is_callable($callback)) {
                            $events[$event_name][] = $callback;
                            Event::on($event_name, $callback);
                            continue;
                        }
                        $msg = "Events: $event_name => " . var_export($callback, true) . " is not callable\n";
                        echo $msg;
                        Log::error($msg);
                    }
                }
                static::$events = array_merge_recursive(static::$events, $events);
                unset($config['event']);
            }
            static::getEvents($config);
        }
    }

}
