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
        'filter' => 'filter',
        'folder' => 'folder',
        'init' => 'init',
        'iyuu' => 'iyuu',
        'notify' => 'notify',
        'mail' => 'mail',
        'sites' => 'sites',
        'sms' => 'sms',
        'user' => 'user',
        'user_sites' => 'user_sites',
        'weixin' => 'weixin',
        'userProfile' => 'userProfile',
    ];

    /**
     * 主入口
     * @param string $config_filename 配置文件名
     * @param Request $request 请求对象
     * @return array
     */
    public static function main(string $config_filename, Request $request): array
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
                case 'notify':
                    return self::uuid($config_filename, $request, 'name');
                default:
                    return self::default($config_filename, $request);
            }
        }
    }

    /**
     * 把老的通知数据格式改成新的
     * 删除 weixin.json 把发送条件合并到定时任务里
     * 初始化 notify.json，添加默认的 iyuu 通道
     */
    private static function migration_notify(array $crontab_configs): array
    {
        self::patchNotify();
        if (empty($crontab_configs)) return $crontab_configs;
        if (isset(current($crontab_configs)['notify'])) return $crontab_configs;
        $wx = self::getWeixin();
        foreach ($crontab_configs as $uuid => $item) {
            $crontab_configs[$uuid]['notify'] = [
                'enable' => $wx['switch'] ?? 'on',
                'channel' => 'iyuu',
                'notify_on_change' => $wx['notify_on_change'] ?? 'off',
            ];
        }
        return $crontab_configs;
    }

    /**
     * 用UUID模拟数据库主键实现配置的增删改查
     * @param string $config_filename 配置文件名
     * @param Request $request 请求对象
     * @param null $PRIMARY 主键
     * @return array
     */
    public static function uuid(string $config_filename, Request $request, $PRIMARY = null): array
    {
        $rs = Constant::RS;
        $old_config = Conf::get($config_filename, Constant::config_format, []);
        if ($config_filename === 'crontab' && !empty($old_config)) {
            $old_config = self::migration_notify($old_config);
            Conf::set('crontab', $old_config, Constant::config_format);
            env('APP_DEBUG', false) and Conf::set('crontab', $old_config, 'array');
        }
        if ($config_filename === 'notify' && empty($old_config)) {
            $old_config = self::patchNotify();;
        }
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
     * 排除字段
     * @param $data
     */
    protected static function createDataExcludeKeys(&$data)
    {
        if (is_array($data)) {
            foreach ([Constant::config_filename, Constant::action] as $key) {
                unset($data[$key]);
            }
        }
    }

    /**
     * 简单操作的增删改查
     * @param string $config_filename
     * @param Request $request
     * @return array
     */
    public static function default(string $config_filename, Request $request): array
    {
        $data = $request->post();
        self::createDataExcludeKeys($data);
        Conf::set($config_filename, $data, Constant::config_format);
        return Constant::RS;
    }

    /**
     * IYUU密钥
     * @return array
     */
    public static function getIyuu(): array
    {
        return Conf::get(self::filename['iyuu'], Constant::config_format, []);
    }

    /**
     * 默认配置
     * @return array
     */
    public static function getDefault(): array
    {
        return Conf::get(self::filename['default'], Constant::config_format, []);
    }

    /**
     * 客户端
     * @return array
     */
    public static function getClients(): array
    {
        return Conf::get(self::filename['clients'], Constant::config_format, []);
    }

    /**
     * 目录
     * @return array
     */
    public static function getFolder(): array
    {
        return Conf::get(self::filename['folder'], Constant::config_format, []);
    }

    /**
     * 所有站点
     * @return array
     */
    public static function getSites(): array
    {
        return Conf::get(self::filename['sites'], Constant::config_format, []);
    }

    /**
     * 过滤器
     * @return array
     */
    public static function getFilter(): array
    {
        return Conf::get(self::filename['filter'], Constant::config_format, []);
    }

    /**
     * 微信配置
     * @return array
     */
    public static function getWeixin(): array
    {
        return Conf::get(self::filename['weixin'], Constant::config_format, []);
    }

    /**
     * 通知配置
     * @return object[]
     */
    public static function getNotify(): array
    {
        $config = Conf::get(self::filename['notify'], Constant::config_format, []);
        if (empty($config)) {
            $iyuu_config = self::getIyuu();
            if (!empty($iyuu_config)) {
                return self::patchNotify();
            }
        }
        return $config;
    }

    /**
     * patch 通知配置
     * @return array
     */
    private static function patchNotify(): array
    {
        $old = Conf::get(self::filename['notify'], Constant::config_format, []);
        if (!empty($old)) return $old;
        $iyuu_config = self::getIyuu();
        $config = [
            'iyuu' => [
                'uuid' => 'iyuu',
                'name' => 'iyuu',
                'type' => 'iyuu',
                'options' => ['token' => $iyuu_config['iyuu.cn']],
            ],
        ];
        Conf::set(self::filename['notify'], $config, Constant::config_format);
        env('APP_DEBUG', false) and Conf::set(self::filename['notify'], $config, 'array');
        return $config;
    }

    /**
     * 根据uuid获取计划任务配置
     * @param string $uuid
     * @return array
     */
    public static function getCronByUUID(string $uuid = ''): array
    {
        $cron = self::getCrontab();
        return array_key_exists($uuid, $cron) ? $cron[$uuid] : [];
    }

    /**
     * 计划任务
     * @return array
     */
    public static function getCrontab(): array
    {
        return Conf::get(self::filename['crontab'], Constant::config_format, []);
    }

    /**
     * 禁用用户已经配置过的站点
     * @param array $sites
     * @return array
     */
    public static function disabledUserSites(array &$sites): array
    {
        $user_sites = self::getUserSites();
        array_walk($sites, function (&$v, $k) use ($user_sites) {
            if (array_key_exists($k, $user_sites)) {
                $v['disabled'] = true;
            }
        });
        return $sites;
    }

    /**
     * 用户拥有的站点
     * @return array
     */
    public static function getUserSites(): array
    {
        return Conf::get(self::filename['user_sites'], Constant::config_format, []);
    }

    /**
     * 禁用用户未配置的站点
     * @param array $sites
     * @return array
     */
    public static function disabledNotConfiguredUserSites(array &$sites): array
    {
        $user_sites = self::getUserSites();
        array_walk($sites, function (&$v, $k) use ($user_sites) {
            if (!array_key_exists($k, $user_sites)) {
                $v['disabled'] = true;
            }
        });
        return $sites;
    }
}
