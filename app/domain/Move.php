<?php
namespace app\domain;

use app\common\Config as Conf;
use app\common\Constant;

class Move
{
    const Delimiter = '{#**#}';
    /**
     * 根据参数，解析转移做种时运行时配置
     * @param string $uuid
     * @return array
     */
    public static function configParser($uuid = ''):array
    {
        $rs = [
            'clients'       => [],
            'form_clients'  => [],
            'to_clients'    => [],
            'path_filter'   => [],
            'path_selector' => [],
            'path_type'     => 0,
            'path_rule'     => [],
            'skip_check'    => 0,
            'paused'        => 1,
            'delete_torrent'=> 0,
        ];
        if (empty($uuid)) {
            return $rs;
        }
        $cronFilename = Config::filename['crontab'];
        $cron = Conf::get($cronFilename, Constant::config_format, []);
        $cron = array_key_exists($uuid, $cron) ? $cron[$uuid] : [];
        //检查使能
        if (isset($cron['switch']) && $cron['switch'] === 'on') {
            //IYUU密钥
            $iyuu = Config::getIyuu();
            $rs['iyuu.cn'] = $iyuu['iyuu.cn'];

            //解析下载器
            $clients = Config::getClients();
            if (!empty($cron['form_clients']) && !empty($cron['to_clients']) && !empty($clients)) {
                $form = $cron['form_clients'];
                $to = $cron['to_clients'];
                //来源下载器 与 目标下载器 不允许相同
                if ($form != $to) {
                    $rs['clients'] = array_filter($clients, function ($k) use ($form, $to) {
                        $haystack = [$form, $to];
                        return in_array($k, $haystack);
                    }, ARRAY_FILTER_USE_KEY);

                    $rs['form_clients'] = isset($clients[$form]) ? $clients[$form] : [];
                    $rs['to_clients'] = isset($clients[$to]) ? $clients[$to] : [];
                }
            }

            //解析过滤器的目录
            $folder = Config::getFolder();
            self::getDir('path_filter', $cron, $folder, $rs);

            //解析选择器的目录
            self::getDir('path_selector', $cron, $folder, $rs);

            //解析路径转换类型 (默认：0 相等)
            $rs['path_type'] = isset($cron['path_type']) ? intval($cron['path_type']) : 0;

            //解析路径转换规则
            if (!empty($cron['path_rule'])) {
                //第一步：先分隔每一行
                $path_rule = explode("\n", self::replaceBr($cron['path_rule']));

                //第二步：解析每一行
                $rs['path_rule'] = self::getPathRule($path_rule);
            } else {
                //当路径转移规则为空时，默认路径相等
                $rs['path_type'] = 0;
            }

            //解析跳校验
            $rs['skip_check'] = isset($cron['skip_check']) ? 1 : 0;

            //解析转移后暂停
            $rs['paused'] = isset($cron['paused']) ? 1 : 0;

            //解析转移后删种
            $rs['delete_torrent'] = isset($cron['delete_torrent']) ? 1 : 0;
        }

        return $rs;
    }

    /**
     * 从目录配置中挑选需要的数据
     * @param string $key       配置的键
     * @param array  $cron      定时任务配置
     * @param array  $folder    目录配置
     * @param array  $rs        返回的转移做种配置
     */
    private static function getDir($key, $cron, $folder, &$rs)
    {
        //检查并初始化
        if (!isset($rs[$key])) {
            $rs[$key] = [];
        }
        //挑选数据
        if (!empty($cron[$key]) && !empty($folder)) {
            $path_filter = explode(',', $cron[$key]);
            foreach ($path_filter as $value) {
                $k = trim($value);
                $dir = isset($folder[$k]) ? $folder[$k]['dir'] : null;
                if ($dir) {
                    $rs[$key][] = $dir;
                }
            }
        }
    }

    /**
     * 处理Linux、Windows换行符差异
     * @param string $str
     * @return string
     */
    private static function replaceBr(string $str = ''):string
    {
        while (strpos($str, "\r\n") !== false) {
            $str = str_replace("\r\n", "\n", $str);
        }
        return $str;
    }

    /**
     * 根据分隔符，解析每一行路径转换规则
     * @param array $path_rule
     * @return array
     */
    private static function getPathRule(array $path_rule = []):array
    {
        $rule = [];
        if (count($path_rule)) {
            foreach ($path_rule as $key => $value) {
                if (strpos($value, self::Delimiter) !== false) {
                    $item = explode(self::Delimiter, $value);
                    if (count($item) === 2) {
                        $item = array_map(function ($v) {
                            return trim($v);
                        }, $item);
                        if ($item[0]) {
                            $rule[] = [$item[0] => $item[1]];
                        }
                    }
                } else {
                    if (trim($value)) {
                        $item = [trim($value) => ''];
                        $rule[] = $item;
                    }
                }
            }
        }
        return $rule;
    }
}
