<?php

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use app\common\Constant;

/**
 * [中间件] 拦截未登录的Session
 * @access private 常驻内存运行，禁止执行器调用
 */
class AuthCheck implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $session = $request->session();
        $action = $request->action;
        $skip = in_array($action, Constant::Skip_AuthCheck);    // 严格区分大小写
        if ($skip || $session->get(Constant::Session_Token_Key)) {
            //刷新SESSION修改时间，避免被GC清除
            $session->set('last_time', time());
            // 不拦截：账号登录、检查登录、绑定token、存在Session等
            return $next($request);
        }
        // 拦截条件：token不存在Session未登录 & 非指定的操作
        return redirect('/page/login.html');
    }
}
