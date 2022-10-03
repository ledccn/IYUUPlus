<?php

namespace app\controller;

use app\domain\Users as domainUsers;
use support\Request;
use support\Response;

/**
 * Class Index
 * @access private 常驻内存运行，禁止执行器调用
 * @package app\controller
 */
class Index
{
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

    public function view(Request $request): Response
    {
        return view('index/view', ['name' => 'webman']);
    }

    public function json(Request $request): Response
    {
        return json(['code' => 0, 'msg' => 'ok']);
    }

    public function file(Request $request): Response
    {
        $file = $request->file('upload');
        if ($file && $file->isValid()) {
            $file->move(public_path() . '/files/myfile.' . $file->getUploadExtension());
            return json(['code' => 0, 'msg' => 'upload success']);
        }
        return json(['code' => 1, 'msg' => 'file not found']);
    }
}
