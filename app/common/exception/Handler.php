<?php

namespace app\common\exception;

use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * Class Handler
 * @package app\common\exception
 */
class Handler extends \support\exception\Handler
{
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

        if ($this->debug) {
            $rs['traces'] = (string)$exception;
        }
        return new Response(200, $header, json_encode($rs, JSON_UNESCAPED_UNICODE));
    }
}
