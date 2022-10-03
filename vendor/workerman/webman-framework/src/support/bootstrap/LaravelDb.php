<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support\bootstrap;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Events\Dispatcher;
use Illuminate\Pagination\Paginator;
use Jenssegers\Mongodb\Connection as MongodbConnection;
use support\Db;
use Throwable;
use Webman\Bootstrap;
use Workerman\Timer;
use Workerman\Worker;

/**
 * Class Laravel
 * @package support\Bootstrap
 */
class LaravelDb implements Bootstrap
{
    /**
     * @param Worker $worker
     *
     * @return void
     */
    public static function start($worker)
    {
        if (!class_exists(Capsule::class)) {
            return;
        }

        $config = \config('database', []);
        $connections = $config['connections'] ?? [];
        if (!$connections) {
            return;
        }

        $capsule = new Capsule;

        $capsule->getDatabaseManager()->extend('mongodb', function ($config, $name) {
            $config['name'] = $name;
            return new MongodbConnection($config);
        });

        $default = $config['default'] ?? false;
        if ($default) {
            $default_config = $connections[$config['default']];
            $capsule->addConnection($default_config);
        }

        foreach ($connections as $name => $config) {
            $capsule->addConnection($config, $name);
        }

        if (\class_exists(Dispatcher::class)) {
            $capsule->setEventDispatcher(new Dispatcher(new Container));
        }

        $capsule->setAsGlobal();

        $capsule->bootEloquent();

        // Heartbeat
        if ($worker) {
            Timer::add(55, function () use ($default, $connections) {
                if (!class_exists(Connection::class, false)) {
                    return;
                }
                foreach ($connections as $key => $item) {
                    if ($item['driver'] == 'mysql') {
                        try {
                            if ($key == $default) {
                                Db::select('select 1');
                            } else {
                                Db::connection($key)->select('select 1');
                            }
                        } catch (Throwable $e) {
                        }
                    }
                }
            });
        }

        // Paginator
        if (class_exists(Paginator::class)) {
            Paginator::queryStringResolver(function () {
                return request()->queryString();
            });
            Paginator::currentPathResolver(function () {
                return request()->path();
            });
            Paginator::currentPageResolver(function ($page_name = 'page') {
                $page = (int)request()->input($page_name, 1);
                return $page > 0 ? $page : 1;
            });
        }
    }
}
