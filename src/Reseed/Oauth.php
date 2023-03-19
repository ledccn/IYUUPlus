<?php

namespace IYUU\Reseed;

use Curl\Curl;

/**
 * IYUU用户注册、认证
 */
class Oauth
{
    /**
     * 登录缓存路径
     */
    const SiteLoginCache = RUNTIME_PATH . DS . 'db' . DS . 'siteLoginCache_{}.json';

    /**
     * 配置
     * @var array
     */
    private static $conf = [];

    /**
     * 用户验证字段映射表
     */
    const VERITY_FIELD_MAP = [
        //默认字段名字
        '' => 'passkey',
        //朱雀
        'zhuque' => 'torrent_key'
    ];

    /**
     * 初始化
     * @param array $config
     */
    public static function init(array $config = [])
    {
        $dir = dirname(self::SiteLoginCache);
        is_dir($dir) or mkdir($dir, 0777, true);
        self::$conf = $config;
    }

    /**
     * 用户注册与登录
     * 作用：在服务器端实现微信用户与合作站点用户id的关联
     * 参数：爱语飞飞token + 合作站点用户id + sha1(合作站点密钥passkey) + 合作站点标识
     * @param string $apiUrl
     * @param array $sites
     * @return bool
     */
    public static function login(string $apiUrl = '', array $sites = array()): bool
    {
        // 云端下发合作的站点标识
        if (empty($sites)) {
            die('云端下发合作站点信息失败，请稍后重试');
        }
        $_sites = array_column($sites, 'site');
        $ret = false;
        $token = self::getSign();
        foreach ($_sites as $k => $site) {
            if (is_file(str_replace('{}', $site, self::SiteLoginCache))) {
                // 存在鉴权缓存
                $ret = true;
                continue;
            }
            //取推荐站点验证字段名字
            $filed = static::VERITY_FIELD_MAP[$site] ?? static::VERITY_FIELD_MAP[''];
            if (isset(self::$conf['sites'][$site][$filed]) && self::$conf['sites'][$site][$filed] && isset(self::$conf['sites'][$site]['id']) && self::$conf['sites'][$site]['id']) {
                $user_id = self::$conf['sites'][$site]['id'];
                $passkey = self::$conf['sites'][$site][$filed];

                $curl = new Curl();
                $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
                $data = [
                    'token' => $token,
                    'id' => $user_id,
                    'passkey' => sha1($passkey),     // 避免泄露用户passkey秘钥
                    'site' => $site,
                ];
                $res = $curl->get($apiUrl, $data);
                cli($res->response);

                $rs = json_decode($res->response, true);
                if (isset($rs['ret']) && ($rs['ret'] === 200) && isset($rs['data']['success']) && $rs['data']['success']) {
                    self::setSiteLoginCache($site, $rs);
                    $ret = true;
                } else {
                    $msg = !empty($rs['msg']) ? $rs['msg'] : '远端服务器无响应，请稍后重试！';
                    $msg = !empty($rs['data']['errmsg']) ? $rs['data']['errmsg'] : $msg;
                    echo $msg . PHP_EOL;
                }
            } else {
                echo $site . '合作站点参数配置不完整，请同时填写passkey和用户id。' . PHP_EOL;
                echo '合作站点鉴权配置，请查阅：https://www.iyuu.cn/archives/337/' . PHP_EOL . PHP_EOL;
            }
        }
        return $ret;
    }

    /**
     * 从配置文件内读取爱语飞飞token作为鉴权参数
     * @return string
     */
    public static function getSign(): string
    {
        $token = empty(self::$conf['iyuu.cn']) ? '' : self::$conf['iyuu.cn'];
        if (empty($token) || strlen($token) < 46) {
            echo "缺少辅种接口请求参数：爱语飞飞token " . PHP_EOL;
            echo "请访问https://iyuu.cn 用微信扫码申请。" . PHP_EOL . PHP_EOL;
            exit(1);
        }
        return $token;
    }

    /**
     * 写鉴权成功缓存
     * @desc 作用：减少对服务器请求，跳过鉴权提示信息；
     * @param string $site
     * @param array $array
     * @return void
     */
    private static function setSiteLoginCache(string $site, array $array): void
    {
        $json = json_encode($array, JSON_UNESCAPED_UNICODE);
        $file = str_replace('{}', $site, self::SiteLoginCache);
        $file_pointer = @fopen($file, "w");
        $worldsnum = @fwrite($file_pointer, $json);
        @fclose($file_pointer);
    }
}
