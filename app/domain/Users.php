<?php
namespace app\domain;

use support\Request;
use app\common\exception\BusinessException;
use app\common\components\Curl;
use app\common\Constant;
use app\common\Config as Conf;

/**
 * 用户逻辑操作 [无状态静态类]
 * @access private 常驻内存运行，禁止执行器调用
 */
class Users
{
    /**
     * 检查用户Session是否已登录
     * @param Request $request
     * @return bool
     */
    public static function isLogin(Request $request):bool
    {
        $session = $request->session();
        $has = $session->has(Constant::Session_Token_Key);
        return $has ? true : false;
    }

    /**
     * 检查token及绑定状态
     * - 接口api.iyuu.cn返回的信息，原样返回给前端
     * @descr 接口地址 https://api.iyuu.cn/index.php?s=App.Api.Sites
     *        接口文档 https://api.iyuu.cn/docs.php?service=App.Api.Sites&detail=1&type=fold
     * @param string $token
     * @param Request $request
     * @return array
     */
    public static function checkToken(string $token, Request $request):array
    {
        $curl = Curl::one();
        $api_url = Constant::API_BASE;
        $api_action = Constant::API['sites'];
        $url = sprintf('%s%s?sign=%s&version=%s', $api_url, $api_action, $token, IYUU_VERSION());
        file_put_contents(db_path().'/_url.json',print_r($url, true));
        $res = $curl->get($url);
        $rs = json_decode($res->response, true);
        file_put_contents(db_path().'/_response.json',print_r($res->response, true));
        file_put_contents(db_path().'/_api.json',print_r($rs, true));
        if (isset($rs['ret']) && ($rs['ret'] === 200) && isset($rs['data']['sites']) && is_array($rs['data']['sites'])) {
            $sites = array_column($rs['data']['sites'], null, 'site');
            Conf::set('sites', $sites, Constant::config_format);
            Conf::set('iyuu', ['iyuu.cn' => $token], Constant::config_format);
            // 验证通过，写入Session
            $session = $request->session();
            $session->set(Constant::Session_Token_Key, $token);
        } else {
            /**
             * 接口api.iyuu.cn在用户的token未激活时，会返回403状态码 和 推荐站点列表，供用户填写绑定信息
             */
            if (isset($rs['ret']) && ($rs['ret'] === 403) && isset($rs['data']['recommend']) && is_array($rs['data']['recommend'])) {
                //用户未绑定合作站点
                $recommend = $rs['data']['recommend'];
                Conf::set('recommend', $recommend, Constant::config_format);
                Conf::set('iyuu', ['iyuu.cn' => $token], Constant::config_format);
            }
        }
        return $rs;
    }

    /**
     * 用户绑定
     * @descr 接口地址 https://api.iyuu.cn/index.php?s=App.User.Login
     *        接口文档 https://api.iyuu.cn/docs.php?service=App.User.Login&detail=1&type=fold
     * @param string $token
     * @param Request $request
     * @return array
     */
    public static function bindToken(string $token, Request $request):array
    {
        $curl = Curl::one();
        $url = Constant::API_BASE . Constant::API['login'];
        $data = [
            'token'  => $token,
            'id'     => $request->post('id') + 0,
            'passkey'=> sha1($request->post('passkey')),     // 避免泄露用户passkey秘钥
            'site'   => $request->post('site'),
        ];
        $res = $curl->get($url, $data);
        $rs = json_decode($res->response, true);
        if (isset($rs['ret']) && ($rs['ret'] === 200) && isset($rs['data']['success']) && $rs['data']['success']) {
            //绑定成功
            return self::checkToken($token, $request);
        } else {
            //绑定失败
            $rs['ret'] = 401;
            $msg = !empty($rs['msg']) ? $rs['msg'] : '远端服务器无响应，请稍后重试！';
            $msg = !empty($rs['data']['errmsg']) ? $rs['data']['errmsg'] : $msg;
            $rs['msg'] = $msg;
            return $rs;
        }
    }

    /**
     * 验证用户输入的密码
     * @param Request $request
     * @return bool
     * @throws BusinessException
     */
    public static function checkPassword(Request $request):bool
    {
        $password = $request->get('password');
        if (empty($password)) {
            throw new BusinessException('密码不能为空', 250);
        }
        $userProfile = Conf::get(self::getUserProfileName(), 'json');
        if (is_null($userProfile)) {
            //初次使用，设置用户密码
            return self::setPassword($password);
        }

        //验证密码
        $salt = $userProfile['salt'];
        if ($userProfile['pass_hash'] !== self::createPassHash($password, $salt)) {
            throw new BusinessException('密码错误，请重新输入！',250);
        }
        return true;
    }

    /**
     * 设置用户密码
     * @param string $password
     * @return bool
     * @throws BusinessException
     */
    public static function setPassword(string $password):bool
    {
        //粗略验证
        if (strlen($password) < 6) {
            throw new BusinessException('密码必须超过6位', 250);
        }
        if (in_array($password, ['admin','root','adminadmin','administrator'])) {
            throw new BusinessException('密码太弱，不安全', 250);
        }

        $salt = getUUID();  //太淡了，加点盐
        $userProfile = [
            'pass_hash' => self::createPassHash($password, $salt),
            'salt'      => $salt,
            'created_at'   => time()
        ];

        return Conf::set(self::getUserProfileName(), $userProfile, 'json');
    }

    /**
     * 生成密码hash
     * @param string $password
     * @param string $salt
     * @return string
     */
    public static function createPassHash(string $password, string $salt):string
    {
        return md5($salt . $password . $salt);
    }

    /**
     * 用户特征文件名
     * @return string
     */
    public static function getUserProfileName():string
    {
        return 'userProfile';
    }
}
