<?php

namespace app\common;

use InvalidArgumentException;

/**
 * 数据结构基类
 */
class DataStruct
{
    /**
     * 当前数据
     * @var array
     */
    protected $__DataStructBase;

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->__DataStructBase = $data;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->__DataStructBase;
    }

    /**
     * 输出Json数据
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->__DataStructBase, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 设置 $this->data
     * @param string|int $key
     * @param mixed $value
     * @return DataStruct
     */
    public function set($key, $value): DataStruct
    {
        if (!is_array($this->__DataStructBase)) {
            throw new InvalidArgumentException('$this->data必须为数组');
        }
        if ($key === null) {
            $this->__DataStructBase[] = $value;
        } else {
            $this->__DataStructBase[$key] = $value;
        }
        return $this;
    }

    /**
     * 当对不可访问属性调用 isset() 或 empty() 时，__isset() 会被调用
     *
     * @param string|int $name
     * @return bool
     */
    public function __isset($name): bool
    {
        return isset($this->__DataStructBase[$name]);
    }

    /**
     * 当对不可访问属性调用 unset() 时，__unset() 会被调用
     *
     * @param string|int $name
     */
    public function __unset($name)
    {
        unset($this->__DataStructBase[$name]);
    }

    /**
     * 当访问不可访问属性时调用
     * @param null $name
     * @return array|string|null
     */
    public function __get($name = null)
    {
        return $this->get($name);
    }

    /**
     * 获取配置项参数【支持 . 分割符】
     * @param string|int|null $offset
     * @param null $default
     * @return array|string|null
     */
    public function get($offset = null, $default = null)
    {
        if (null === $offset) {
            return $this->__DataStructBase;
        }
        $key_array = explode('.', $offset);
        $value = $this->__DataStructBase;
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }
}
