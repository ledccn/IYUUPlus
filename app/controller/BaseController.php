<?php

namespace app\controller;

use app\common\Constant;
use app\domain\Users as domainUsers;
use support\Request;
use support\Response;

/**
 * 控制器基类
 * @access private 常驻内存运行，禁止执行器调用
 */
class BaseController
{
    /**
     * 接口返回数据结构
     * @var array
     */
    const RS = Constant::RS;

    /**
     * 默认控制器
     * @descr 检查未登录重定向
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $url = domainUsers::isLogin($request) ? '/index.html' : '/page/login.html';
        return redirect($url);
    }
}
