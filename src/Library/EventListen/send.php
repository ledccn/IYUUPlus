<?php
namespace IYUU\Library\EventListen;

use app\common\event\EventListenerInterface;

class send implements EventListenerInterface
{
    /**
     * 需要订阅的事件
     * - 返回要监听的事件数组，可监听多个事件
     * @return array
     */
    public function events():array
    {
        return [];
    }

    /**
     * 订阅器的处理方法(事件触发后，会执行该方法)
     *
     * @param string $event
     */
    public function process(string $event):void
    {
    }
}
