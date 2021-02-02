<?php
namespace app\common\components;

use app\common\Constant;
use Curl\Curl as ICurl;

/**
 * 单例Curl
 * @access private 常驻内存运行，禁止执行器调用
 */
class Curl
{
    /**
     * 单例
     * @var null | ICurl
     */
    protected static $_instance = null;

    /**
     * 私有化构造函数，避免外部new
     */
    private function __construct()
    {
    }

    /**
     * 单例
     * @param bool $reset
     * @return ICurl
     */
    public static function one($reset = true)
    {
        if (self::$_instance === null) {
            self::$_instance = new ICurl();
        } else {
            // 重置
            if ($reset) {
                self::$_instance->reset();
            }
        }

        // 设置UserAgent
        self::$_instance->setUserAgent(Constant::UserAgent);
        return self::$_instance;
    }
}
