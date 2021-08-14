<?php
namespace app\common\components;

use app\common\Constant;
use app\common\exception\BusinessException;
use Curl\Curl as ICurl;

/**
 * 对Curl的单例包装，可以使用静态方法调用
 */
class Curl
{
    /**
     * 数据流的类型（表单默认）
     */
    const CONTENT_TYPE_DEFAULT = 'application/x-www-form-urlencoded; charset=UTF-8';

    /**
     * 数据流的类型（JSON）
     */
    const CONTENT_TYPE_JSON = 'application/json; charset=UTF-8';

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
        // 设置不检查证书
        self::$_instance->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        #self::$_instance->setOpt(CURLOPT_SSL_VERIFYHOST, 2);
        #self::$_instance->setOpt(CURLOPT_SSLVERSION, 1);

        // 设置UserAgent
        self::$_instance->setUserAgent(Constant::UserAgent);
        return self::$_instance;
    }

    /**
     * GET请求
     * @param string $url
     * @param array $data   数据
     * @param bool $reset   是否重置Curl(默认true)
     * @return ICurl
     */
    public static function get($url, $data = array(), bool $reset = true): ICurl
    {
        return static::one($reset)->get($url, $data);
    }

    /**
     * POST请求
     * @param string $url
     * @param array $data   数据
     * @param bool $asJson  是否Json
     * @param bool $reset   是否重置Curl(默认true)
     * @return ICurl
     */
    public static function post($url, $data = array(), $asJson = false, bool $reset = true): ICurl
    {
        static::one($reset);
        if ($asJson) {
            self::$_instance->setHeader('Content-Type', static::CONTENT_TYPE_JSON);
        } else {
            self::$_instance->setHeader('Content-Type', static::CONTENT_TYPE_DEFAULT);
        }
        return self::$_instance->post($url, $data, $asJson);
    }

    /**
     * 简易POST
     * @param string $url
     * @param $data
     * @return false|string
     */
    public static function http_post(string $url, $data)
    {
        $opts = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type: ' . static::CONTENT_TYPE_DEFAULT,
                'content' => http_build_query($data),
                'timeout' => 5
            ),
            // 解决SSL证书验证失败的问题
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            )
        );
        $context  = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }

    /**
     * (委托)在静态上下文中调用一个不可访问方法时，__callStatic() 会被调用
     * @param $method
     * @param $arguments
     * @return mixed
     * @throws BusinessException
     */
    public static function __callStatic($method, $arguments)
    {
        static::one(true);
        if (method_exists(self::$_instance, $method) && is_callable([self::$_instance, $method])) {
            return self::$_instance->{$method}(... $arguments);
        }
        throw new BusinessException($method. '不存在或不可调用');
    }
}
