<?php
namespace app\common;

/**
 * 全局常量定义
 * @access private 常驻内存运行，禁止执行器调用
 */
class Constant
{
    const UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36';

    //用户登录后用来保存IYUU token的Session键名
    const Session_Token_Key = 'token';
    /**
     * API定义
     */
    const API_BASE = 'http://api.iyuu.cn';
    const API = [
        'login'   => '/App.Api.Bind',
        'sites'   => '/App.Api.Sites',
        'infohash'=> '/App.Api.Infohash',
        'hash'    => '/App.Api.Hash',
        'notify'  => '/App.Api.Notify',
        'recommend'  =>  '/Api/GetRecommendSites',
        'getSign'   => '/App.Api.GetSign',
    ];

    /**
     * 配置文件默认保存格式
     */
    const config_format = 'json';

    /**
     * 编辑配置时配置文件的键名
     */
    const config_filename = 'config_filename';

    /**
     * 编辑配置时动作的键名
     */
    const action = 'action';

    /**
     * 模拟数据库主键UUID的键名
     */
    const uuid = 'uuid';

    /**
     * 接口返回的数据结构
     * @var array
     */
    const RS = [
        'ret'   =>  200,
        'data'  =>  [],
        'msg'   =>  ''
    ];

    /**
     * 全局错误码
     */
}
