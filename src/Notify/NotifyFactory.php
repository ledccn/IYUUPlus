<?php

namespace IYUU\Notify;

use app\domain\Config;
use Error;
use Exception;

class NotifyFactory
{
    /**
     * 缓存的通知渠道
     * @var array<string, INotify>
     */
    private static $notify_channels;

    /**
     * @param string $name
     * @return null | INotify
     * @throws Exception
     */
    public static function get(string $name): ?INotify
    {
        if (null === self::$notify_channels) {
            $notifyConfigs = Config::getNotify();
            foreach ($notifyConfigs as $key => $option) {
                self::$notify_channels[$key] = self::create($option['type'], $option['options']);
            }
        }
        return self::$notify_channels[$name];
    }

    /**
     * @param string $type
     * @param array $options
     * @return INotify
     * @throws Exception
     */
    private static function create(string $type, array $options): INotify
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
                throw new Exception("unimplemented type {$type}");
            default:
                throw new Exception("unknown notify type {$type}");
        }
    }
}
