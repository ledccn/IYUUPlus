<?php

namespace app\common;

use InvalidArgumentException;
use support\Response;
use function explode;

/**
 * 定义业务返回的数据结构
 * Class Message
 * @package david\common
 */
class Message
{
    /**
     * 状态码
     * @var int
     */
    protected $code = -1;

    /**
     * 消息
     * @var string
     */
    protected $msg = '';

    /**
     * 数据
     * @var array
     */
    protected $data = [];

    /**
     * 构造函数
     * Message constructor.
     *
     * @param int $code 业务返回状态码
     * @param string $msg 业务返回消息
     * @param array|null $data 业务返回数据
     */
    public function __construct(int $code = -1, string $msg = '', ?array $data = [])
    {
        $this->code = $code;
        $this->msg = $msg;
        $this->data = $data;
    }

    /**
     * 获取状态码
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * 设置状态码
     * @param int $code
     * @return $this
     */
    public function setCode(int $code): Message
    {
        $this->code = $code;
        return $this;
    }

    /**
     * 获取消息
     * @return string
     */
    public function getMsg(): string
    {
        return $this->msg;
    }

    /**
     * 设置消息
     * @param string $msg
     * @return $this
     */
    public function setMsg(string $msg): Message
    {
        $this->msg = $msg;
        return $this;
    }

    /**
     * 获取数据
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 设置数据
     * @param array $data
     * @return $this
     */
    public function setData(array $data): Message
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 支持 . 分割符，来获取$this->data的数据
     * @param null $key
     * @param null $default
     * @return array|mixed|object|null
     */
    public function get($key = null, $default = null)
    {
        if ($key === null) {
            return $this->data;
        }
        $key_array = explode('.', $key);
        $value = $this->data;
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }

    /**
     * 设置 $this->data
     * @param string|int $key
     * @param mixed $value
     * @return $this
     */
    public function set($key, $value): Message
    {
        if (!is_array($this->data)) {
            throw new InvalidArgumentException('$this->data必须为数组');
        }
        if ($key === null) {
            $this->data[] = $value;
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }

    /**
     * 成功响应
     * @param array $data
     * @return Response
     */
    public function success(array $data = []): Response
    {
        $this->setCode(200);
        if ($data) {
            $this->setData($data);
        }
        return new Response(200, ['Content-Type' => 'application/json'], $this->toString());
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        $str = json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
        return is_string($str) ? $str : '';
    }

    /**
     * 获取类的成员，转为数组
     * @return array
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'msg' => $this->msg,
            'data' => is_array($this->data) && empty($this->data) ? (object)$this->data : $this->data
        ];
    }

    /**
     * 失败响应
     * @param string $msg
     * @param array $data
     * @param int $code
     * @return Response
     */
    public function fail(string $msg = '', array $data = [], int $code = 400): Response
    {
        if ($msg) {
            $this->setMsg($msg);
        }
        if ($data) {
            $this->setData($data);
        }
        $this->setCode($code);
        return new Response(200, ['Content-Type' => 'application/json'], $this->toString());
    }

    /**
     * 直接发送响应
     * @return Response
     */
    public function response(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], $this->toString());
    }

    /**
     * 魔法方法，当把此类当做字符串输出时，自动调用此方法
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
