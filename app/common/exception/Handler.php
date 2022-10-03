<?php

namespace app\common\exception;

use Throwable;
use Webman\Exception\ExceptionHandler;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * @access private 常驻内存运行，禁止执行器调用
 * Class Handler
 * @package app\common\exception
 */
class Handler extends ExceptionHandler
{
    /**
     * 记录日志
     * @param Throwable $exception
     */
    public function report(Throwable $exception)
    {
        //仅调试模式记录日志
        if ($this->_debug) {
            parent::report($exception);
        }
    }

    /**
     * 渲染返回
     * @param Request $request
     * @param Throwable $exception
     * @return Response
     */
    public function render(Request $request, Throwable $exception): Response
    {
        $header = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Connection' => 'close',
            'Pragma' => 'no-cache'
        ];
        $code = $exception->getCode();
        $error = $exception->getMessage();
        $rs = [
            'ret' => $code,
            'data' => [],
            'msg' => $error
        ];

        if ($this->_debug) {
            $rs['traces'] = (string)$exception;
        }
        return new Response(200, $header, json_encode($rs, JSON_UNESCAPED_UNICODE));
    }
}
