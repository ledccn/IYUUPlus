<?php
namespace app\domain;

use app\common\Config as Conf;
use app\common\Constant;
use support\Request;

/**
 * 配置文件的增删改查
 * @access private 常驻内存运行，禁止执行器调用
 */
class Config
{
    /**
     * 配置文件名 [不含扩展名]
     */
    const filename = [
        'clients' => 'clients',
        'crontab' => 'crontab',
        'default' => 'default',
        'filter'  => 'filter',
        'folder'  => 'folder',
        'init'    => 'init',
        'iyuu'    => 'iyuu',
        'mail'    => 'mail',
        'sites'   => 'sites',
        'sms'     => 'sms',
        'user'    => 'user',
        'user_sites'=> 'user_sites',
        'weixin'  => 'weixin',
    ];

    /**
     * 主入口
     * @param string $config_filename       配置文件名
     * @param Request $request              请求对象
     * @return array
     */
    public static function main(string $config_filename, Request $request):array
    {
        $rs = Constant::RS;
        // 取值优先级：get > post
        $action = $request->get(Constant::action) ? $request->get(Constant::action) : $request->post(Constant::action);
        if ($action === 'get') {
            // 返回原始数据
            $rs['data'] = Conf::get($config_filename, Constant::config_format, []);
            return $rs;
        } else {
            switch ($config_filename) {
                case 'clients':
                case 'filter':
                case 'folder':
                case 'crontab':
                    return self::uuid($config_filename, $request);
                case 'user_sites':
                    return self::uuid($config_filename, $request, 'name');
                default:
                    return self::default($config_filename, $request);
            }
        }
    }

    /**
     * 用UUID模拟数据库主键实现配置的增删改查
     * @param string $config_filename       配置文件名
     * @param Request $request              请求对象
     * @param null $PRIMARY                 主键
     * @return array
     */
    public static function uuid(string $config_filename, Request $request, $PRIMARY = null):array
    {
        $rs = Constant::RS;
        $old_config = Conf::get($config_filename, Constant::config_format, []);
        // 取值优先级：get > post
        $action = $request->get(Constant::action) ? $request->get(Constant::action) : $request->post(Constant::action);
        switch ($action) {
            case 'add':     // 增
                $data = $request->post();
                self::createDataExcludeKeys($data);
                $uuid = is_null($PRIMARY) ? getUUID() : ($data[$PRIMARY] ?? getUUID());
                $data[Constant::uuid] = $uuid;
                $config_filename === self::filename['crontab'] and Crontab::createHock($data);  //计划任务
                $data = array_merge($old_config, [$data[Constant::uuid] => $data]);
                Conf::set($config_filename, $data, Constant::config_format);
                env('APP_DEBUG', false) and Conf::set($config_filename, $data, 'array');  // 调试
                $rs['data'] = ['add_num' => 1];
                break;
            case 'del':     // 删
                $uuid = $request->get(Constant::uuid);
                $config_filename === self::filename['crontab'] and Crontab::deleteHock($uuid);  //计划任务
                if ($uuid && array_key_exists($uuid, $old_config)) {
                    unset($old_config[$uuid]);
                    Conf::set($config_filename, $old_config, Constant::config_format);
                    env('APP_DEBUG', false) and Conf::set($config_filename, $old_config, 'array');  // 调试
                    $rs['data'] = ['delete_num' => 1];
                }
                break;
            case 'edit':     // 改
                $data = $request->post();
                self::createDataExcludeKeys($data);
                $uuid = $request->post(Constant::uuid);
                if ($uuid && array_key_exists($uuid, $old_config)) {
                    $config_filename === self::filename['crontab'] and Crontab::createHock($data);  //计划任务
                    $old_config[$uuid] = $data;
                    Conf::set($config_filename, $old_config, Constant::config_format);
                    env('APP_DEBUG', false) and Conf::set($config_filename, $old_config, 'array');  // 调试
                    $rs['data'] = ['update_num' => 1];
                }
                break;
            default:     // 查 tableList
                $total = count($old_config);
                if ($total >= 1) {
                    $rs['data'] = [
                        'total' => $total,
                        'items' => array_values($old_config)
                    ];
                } else {
                    $rs['data'] = [
                        'total' => 0,
                        'items' => []
                    ];
                }
                break;
        }

        return $rs;
    }

    /**
     * 简单操作的增删改查
     * @param string $config_filename
     * @param Request $request
     * @return array
     */
    public static function default(string $config_filename, Request $request):array
    {
        $data = $request->post();
        self::createDataExcludeKeys($data);
        Conf::set($config_filename, $data, Constant::config_format);
        return Constant::RS;
    }

    /**
     * 排除字段
     * @param $data
     */
    protected static function createDataExcludeKeys(&$data) {
        if (is_array($data)) {
            foreach ([Constant::config_filename, Constant::action] as $key) {
                unset($data[$key]);
            }
        }
    }

    /**
     * 把旧配置格式转换为新格式 [兼容性处理]
     */
    public static function format()
    {}
}
