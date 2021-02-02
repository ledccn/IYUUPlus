<?php
namespace app\controller;

use support\Request;
use support\Response;
use app\common\exception\BusinessException;
use app\common\Config;
use app\common\Constant;

/**
 * Class Status
 * @package app\controller
 */
class Status extends BaseController
{
    /**
     * 系统日志
     * @param Request $request
     * @return Response
     */
    public function syslog(Request $request): Response
    {
        $rs = self::RS;
        $config = config('server');
        $log_file = Config::get($config['log_file'], 'raw', '', true);
        $stdout_file = Config::get($config['stdout_file'], 'raw', '', true);
        $rs['data'] = [
            'log_file'      => $log_file,
            'stdout_file'   => $stdout_file,
        ];
        return json($rs);
    }
}
