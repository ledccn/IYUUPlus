<?php
namespace app\controller;

use app\common\exception\BusinessException;
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

    public function __construct()
    {
    }

    /**
     * 接口参数
     * @param string $name 接口参数名字
     * @param mixed $value 接口参数解析后的值
     */
    public function __set($name, $value) {
        $this->$name = $value;
    }

    /**
     * 获取接口参数
     * @param string $name 接口参数名字
     * @return mixed
     * @throws BusinessException
     */
    public function __get($name) {
        if(!isset($this->$name) || empty($name)) {
            throw new BusinessException(sprintf('$this->%s not null', $name));
        }

        return $this->$name;
    }

    /**
     * 默认控制器
     * @descr 检查未登录重定向
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $url = domainUsers::isLogin($request) ? '/index.html' : '/page/login.html';
        return redirect($url);
    }
}
