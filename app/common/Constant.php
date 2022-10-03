<?php

namespace app\common;

/**
 * 全局常量定义
 */
class Constant
{
    const UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36';

    /**
     * 用户登录后用来保存IYUU token的Session键名
     * @var string
     */
    const Session_Token_Key = 'token';

    /**
     * 允许跳过AuthCheck中间件拦截的动作 (严格区分大小写)
     * 注意：此配置影响到app\middleware\AuthCheck中间件
     * @var array
     */
    const Skip_AuthCheck = ['Login', 'checkLogin', 'BindToken'];

    /**
     * API定义
     */
    const API_BASE = 'https://api.iyuu.cn';
    const API = [
        'login' => '/App.Api.Bind',
        'sites' => '/App.Api.Sites',
        'infohash' => '/App.Api.Infohash',
        'hash' => '/App.Api.Hash',
        'notify' => '/App.Api.Notify',
        'recommend' => '/App.Api.GetRecommendSites',
        'getSign' => '/App.Api.GetSign',
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
        'ret' => 200,
        'data' => [],
        'msg' => ''
    ];

    /**
     * 站点名转换为文件名，所使用的映射表
     */
    const SITENAME_TO_FILENAME_MAP = [
        '1ptba' => 'ptba',
        '52pt' => 'site52pt',
        'm-team' => 'mteam',
        'hd-torrents' => 'hdtorrents',
    ];

    /**
     * 使用POST请求方法下载种子的站点
     */
    const SITE_DOWNLOAD_METHOD_POST = ['hdsky', 'hdcity'];
}
