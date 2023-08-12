<?php
/**
 * 路由配置
 */

use Webman\Http\Request;
use Webman\Route;

Route::fallback(function (Request $request) {
    $response = strtoupper($request->method()) === 'OPTIONS' ? response('', 204) : json(['code' => 404, 'msg' => '404 not found']);
    $response->withHeaders([
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Origin' => "*",
        'Access-Control-Allow-Methods' => '*',
        'Access-Control-Allow-Headers' => '*',
    ]);
    return $response;
});
