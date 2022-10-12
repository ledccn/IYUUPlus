<?php

namespace IYUU\Notify;

use app\domain\Config;

class NotifyFactory
{
    /**
     * 缓存的通知渠道
     * @var array<string, INotify> | null
     */
    private static $notify_channels = null;

    private static function init()
    {
        $result = [];
        $notifyConfigs = Config::getNotify();
        foreach ($notifyConfigs as $key => $option) {
            $result[$key] = self::create($option['type'], $option['options']);
        }
        self::$notify_channels = $result;
    }

    /**
     * @param string $name
     * @return null | INotify
     */
    public static function get(string $name)
    {
        if (self::$notify_channels == null) {
            self::init();
        }
        return self::$notify_channels[$name];
    }

    /**
     * @param string $type
     * @param array $options
     * @return INotify
     * @throws Error
     */
    private static function create(string $type, array $options)
    {
        switch ($type) {
            case 'iyuu':
                return new IYUUWechat($options);
            case 'serverchan':
                return new ServerChan($options);
            case 'bark':
                return new Bark($options);
            case 'sms':
            case 'email':
                throw new Error("unimplemented type `$type`");
            default:
                throw new Error("unknown notify type `$type`");
        }
    }
}
