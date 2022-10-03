<?php

namespace app\controller;

use support\Request;
use support\Response;
use app\common\exception\BusinessException;
use app\common\Config;
use app\common\Constant;
use app\domain\Config as domainConfig;
use app\domain\ConfigParser\Rss as domainRss;
use app\domain\ConfigParser\Spiders as domainSpiders;
use app\domain\Users as domainUsers;

/**
 * Class Api
 * @access private 常驻内存运行，禁止执行器调用
 * @package app\controller
 */
class Api extends BaseController
{
    /**
     * 登录 [跳过AuthCheck中间件]
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function Login(Request $request): Response
    {
        $rs = self::RS;
        $token = $request->get('token');
        domainUsers::checkPassword($request);
        if (check_token($token)) {
            $rs = domainUsers::checkToken($token, $request);
        } else {
            $rs['ret'] = 401;
            $rs['msg'] = 'Token格式错误！';
        }

        return json($rs);
    }

    /**
     * 查询用户Session是否已登录 [跳过AuthCheck中间件]
     * @desc 因静态页无法响应301/302状态码，所以加入此接口供前端主动调用
     * @param Request $request
     * @return Response
     */
    public function checkLogin(Request $request): Response
    {
        $rs = self::RS;
        $rs['data'] = [
            'is_login' => domainUsers::isLogin($request)
        ];

        return json($rs);
    }

    /**
     * 爱语飞飞Token与用户站点进行绑定 [跳过AuthCheck中间件]
     * @param Request $request
     * @return Response
     */
    public function BindToken(Request $request): Response
    {
        $rs = self::RS;
        $token = $request->post('token');
        if (check_token($token)) {
            $rs = domainUsers::bindToken($token, $request);
        } else {
            $rs['ret'] = 401;
            $rs['msg'] = 'Token格式错误！';
        }

        return json($rs);
    }

    /**
     * 退出登录
     * @param Request $request
     * @return Response
     */
    public function Logout(Request $request): Response
    {
        $request->session()->flush();
        return json(self::RS);
    }

    /**
     * 版本信息
     * @param Request $request
     * @return mixed
     */
    public function Version(Request $request): Response
    {
        return json(config());
    }

    /**
     * 获取菜单
     * @param Request $request
     * @return Response
     */
    public function Menu(Request $request): Response
    {
        $filepath = public_path() . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'init.json';
        $init = Config::get($filepath, 'json', [], true);
        //TODO 前端菜单注入接口
        return json($init);
    }

    /**
     * 配置接口{增、删、改、查}
     * @param Request $request
     * @return Response
     */
    public function Config(Request $request): Response
    {
        $rs = self::RS;
        $key = Constant::config_filename;
        // 取值优先级：get > post
        $config_filename = $request->get($key) ? $request->get($key) : $request->post($key);   // 值对应( /db/?.ext )这个文件名
        if ($config_filename) {
            $rs = domainConfig::main($config_filename, $request);
        } else {
            $rs['ret'] = 403;
            $rs['msg'] = 'config_filename错误！';
        }
        return json($rs);
    }

    /**
     * 获取站点列表
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function sitesList(Request $request): Response
    {
        $rs = self::RS;
        $sites = domainConfig::getSites();
        if (empty($sites)) {
            throw new BusinessException('您的账号尚未进行用户验证。', 401);
        }

        //过滤用户已配置站点
        if ($request->get('filter')) {
            domainConfig::disabledUserSites($sites);
        }
        ksort($sites);

        $rs['data']['sites'] = $sites;
        $rs['data']['total'] = count($sites);
        return json($rs);
    }

    /**
     * 清理系统级别的缓存、日志
     * @param Request $request
     * @return Response
     */
    public function Clear(Request $request): Response
    {
        $config = config('server');
        Config::set($config['log_file'], date('Y-m-d H:i:s') . ' 清理日志' . PHP_EOL, 'raw', true);
        Config::set($config['stdout_file'], date('Y-m-d H:i:s') . ' 清理日志' . PHP_EOL, 'raw', true);
        return json(['code' => 1, 'msg' => '清理成功', 'data' => []]);
    }

    /**
     * 获取所有RSS支持的站点
     * @param Request $request
     * @return Response
     */
    public function getAllRssClass(Request $request): Response
    {
        $rs = self::RS;
        $sites = domainRss::getAllRssClass();
        $sites = domainRss::formatSites($sites);
        //过滤用户未配置站点
        if ($request->get('filter')) {
            domainConfig::disabledNotConfiguredUserSites($sites);
        }

        $rs['data']['items'] = $sites;
        $rs['data']['total'] = count($sites);
        return json($rs);
    }

    /**
     * 获取所有免费种爬虫支持的站点
     * @param Request $request
     * @return Response
     */
    public function getAllSpidersClass(Request $request): Response
    {
        $rs = self::RS;
        $sites = domainSpiders::getAllSpidersClass();
        $sites = domainSpiders::formatSites($sites);
        //过滤用户未配置站点
        if ($request->get('filter')) {
            domainConfig::disabledNotConfiguredUserSites($sites);
        }

        $rs['data']['items'] = $sites;
        $rs['data']['total'] = count($sites);
        return json($rs);
    }
}
