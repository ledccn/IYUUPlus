<?php

namespace app\controller;

use support\Request;
use support\Response;
use app\domain\Crontab;
use app\domain\ConfigParser\Move as domainMove;
use app\domain\ConfigParser\Reseed as domainReseed;
use app\domain\ConfigParser\Rss as domainRss;
use Throwable;

/**
 * Class Task
 * @access private 常驻内存运行，禁止执行器调用
 * @package app\controller
 */
class Task extends BaseController
{
    /**
     * 根据参数，解析转移任务配置
     * @param Request $request
     * @return Response
     */
    public function moveConfig(Request $request): Response
    {
        $rs = self::RS;
        $uuid = $request->get('uuid');
        $rs['data'] = domainMove::parser($uuid);
        return json($rs);
    }

    /**
     * 根据参数，解析辅种任务配置
     * @param Request $request
     * @return Response
     */
    public function reseedConfig(Request $request): Response
    {
        $rs = self::RS;
        $uuid = $request->get('uuid');
        $rs['data'] = domainReseed::parser($uuid);
        return json($rs);
    }

    /**
     * 根据参数，解析RSS下载任务配置
     * @param Request $request
     * @return Response
     */
    public function rssConfig(Request $request): Response
    {
        $rs = self::RS;
        $uuid = $request->get('uuid');
        $rs['data'] = domainRss::parser($uuid);
        return json($rs);
    }

    /**
     * 开启|关闭，计划任务
     * @param Request $request
     * @return Response
     */
    public function switch(Request $request): Response
    {
        $rs = self::RS;
        $uuid = $request->get('uuid');
        $switch = $request->get('switch');
        //TODO...
        return json(domainReseed::parser($uuid));
    }

    /**
     * 手动执行计划任务
     * @param Request $request
     * @return Response
     */
    public function start(Request $request): Response
    {
        $rs = self::RS;
        $uuid = $request->get('uuid');
        $rs['data'] = [
            'success' => Crontab::runCron($uuid)
        ];
        return json($rs);
    }

    /**
     * 停止正在执行的计划任务
     * @param Request $request
     * @return Response
     */
    public function stop(Request $request): Response
    {
        $rs = self::RS;
        $uuid = $request->get('uuid');
        $pid_file = Crontab::getPidFile($uuid);
        clearstatcache();
        $rs['data'] = [
            'success' => (is_file($pid_file) and unlink($pid_file)) or !is_file($pid_file),
        ];

        return json($rs);
    }

    /**
     * 查看任务的log
     * @param Request $request
     * @return Response
     */
    public function logs(Request $request): Response
    {
        $rs = self::RS;
        $uuid = $request->get('uuid');
        $last_line_number = $request->get('last_line_number', 50);
        try {
            $logs = Crontab::readLogs($uuid, $last_line_number);
        } catch (Throwable $throwable) {
            $logs = $throwable->getMessage();
        }

        $rs['data'] = [
            'uuid' => $uuid,
            'logs' => $logs,
        ];
        return json($rs);
    }

    /**
     * 清空任务log
     * @param Request $request
     * @return Response
     */
    public function clearLogs(Request $request): Response
    {
        $rs = self::RS;
        $uuid = $request->get('uuid');
        $rs['data'] = [
            'uuid' => $uuid,
            'success' => Crontab::clearLogs($uuid)
        ];
        return json($rs);
    }

    /**
     * 清理所有计划任务的日志
     * @param Request $request
     * @return Response
     */
    public function clearAllLogs(Request $request): Response
    {
        $rs = self::RS;
        $rs['data'] = [
            'success' => Crontab::clearAllLogs()
        ];
        return json($rs);
    }

    /**
     * 清理任务缓存
     * @param Request $request
     * @return Response
     */
    public function clearCache(Request $request): Response
    {
        $rs = self::RS;
        $ret = false;

        $clear_type = $request->get('type');
        switch ($clear_type) {
            case 'clearReseedCache':
                $ret = domainReseed::clearReseedCache();
                break;
            case 'clearMoveCache':
                $ret = domainReseed::clearMoveCache();
                break;
            default:
                break;
        }

        $rs['data'] = [
            'success' => $ret
        ];
        return json($rs);
    }

    /**
     * 刷新所有任务
     * @descr 不同平台的配置，会造成command错误，需要重新解析命令
     * @param Request $request
     * @return Response
     */
    public function refresh(Request $request): Response
    {
        $rs = self::RS;
        $ret = true;
        try {
            Crontab::onWorkerStart();
        } catch (\Exception $exception) {
            $ret = false;
        }
        $rs['data'] = [
            'success' => $ret
        ];
        return json($rs);
    }
}
