<?php
namespace IYUU\Library;

/**
 * 上下文
 * Class Context
 * @package IYUU\Library
 */
class Context
{
    /**
     * @var null|Context
     */
    private static $_instance = null;

    /**
     * 配置信息（只读）
     * @var array
     */
    private $config;

    /**
     * 被重载的上下文Session数据保存在此
     * @var array
     */
    private $session = [];

    /**
     * 私有化构造函数
     * Context constructor.
     * @param array $config
     */
    private function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 返回当前实例（单例模式）
     * - 首次获取实例可以传入$config数组初始化
     * @param array $config
     * @return Context|null
     */
    public static function getInstance(array $config = []): Context
    {
        if (static::$_instance === null) {
            static::$_instance = new static($config);
        }
        return static::$_instance;
    }

    /**
     * 完整事件数据包中的参数 (支持.分隔符)
     * @param string|int $key
     * @param mixed $default
     * @return mixed|null
     */
    public function get($key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        $key_array = \explode('.', $key);
        $value = $this->config;
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }

    /**
     * 获取Session
     * @param string|null $key
     * @param mixed $default
     * @return array|mixed|null
     */
    public function getSession(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->session;
        }
        $key_array = \explode('.', $key);
        $value = $this->session;
        foreach ($key_array as $index) {
            if (!array_key_exists($index, $value)) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }

    /**
     * 设置Session
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setSession(string $key, $value): Context
    {
        $this->session[$key] = $value;
        return $this;
    }

    /**
     * 移除指定Session
     * @param string $key
     * @return Context
     */
    public function unsetSession(string $key): Context
    {
        unset($this->session[$key]);
        return $this;
    }

    /**
     * 读取不可访问属性的值时，__get() 会被调用
     *
     * @param string $name 参数名字
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->getSession($name, null);
    }

    /**
     * 在给不可访问属性赋值时，__set() 会被调用
     *
     * @param string $name 参数名字
     * @param mixed $value 参数解析后的值
     */
    public function __set(string $name, $value)
    {
        $this->setSession($name, $value);
    }

    /**
     * 当对不可访问属性调用 isset() 或 empty() 时，__isset() 会被调用
     *
     * @param string|int $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->session[$name]);
    }

    /**
     * 当对不可访问属性调用 unset() 时，__unset() 会被调用
     *
     * @param string|int $name
     */
    public function __unset(string $name): void
    {
        unset($this->session[$name]);
    }
}
