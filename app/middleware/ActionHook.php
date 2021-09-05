<?php
namespace app\middleware;

use support\bootstrap\Container;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * [中间件] 控制器钩子
 * @descr 每个控制器执行前调用beforeAction()，执行后调用afterAction()
 * Class ActionHook
 * @package app\middleware
 */
class ActionHook implements MiddlewareInterface
{
    public function process(Request $request, callable $next) : Response
    {
        if ($request->controller) {
            // 禁止直接访问beforeAction afterAction
            if ($request->action === 'beforeAction' || $request->action === 'afterAction') {
                return response('<h1>404 Not Found</h1>', 404);
            }
            $controller = Container::get($request->controller);
            //请求前的流程
            if (method_exists($controller, 'beforeAction')) {
                $before_response = call_user_func([$controller, 'beforeAction'], $request);
                if ($before_response instanceof Response) {
                    return $before_response;
                }
            }
            $response = $next($request);
            //请求后的流程
            if (method_exists($controller, 'afterAction')) {
                $after_response = call_user_func([$controller, 'afterAction'], $request, $response);
                if ($after_response instanceof Response) {
                    return $after_response;
                }
            }
            return $response;
        }
        return $next($request);
    }
}
