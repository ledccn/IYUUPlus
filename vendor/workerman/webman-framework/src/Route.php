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

namespace Webman;

use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;
use Webman\Route\Route as RouteObject;
use function FastRoute\simpleDispatcher;

/**
 * Class Route
 * @package Webman
 */
class Route
{
    /**
     * @var Route
     */
    protected static $_instance = null;

    /**
     * @var GroupCountBased
     */
    protected static $_dispatcher = null;

    /**
     * @var RouteCollector
     */
    protected static $_collector = null;

    /**
     * @var null|callable
     */
    protected static $_fallback = null;

    /**
     * @var array
     */
    protected static $_nameList = [];

    /**
     * @var string
     */
    protected static $_groupPrefix = '';

    /**
     * @var bool
     */
    protected static $_disableDefaultRoute = [];

    /**
     * @var RouteObject[]
     */
    protected static $_allRoutes = [];

    /**
     * @var RouteObject[]
     */
    protected $_routes = [];

    /**
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    public static function get(string $path, $callback)
    {
        return static::addRoute('GET', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    public static function post(string $path, $callback)
    {
        return static::addRoute('POST', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    public static function put(string $path, $callback)
    {
        return static::addRoute('PUT', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    public static function patch(string $path, $callback)
    {
        return static::addRoute('PATCH', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    public static function delete(string $path, $callback)
    {
        return static::addRoute('DELETE', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    public static function head(string $path, $callback)
    {
        return static::addRoute('HEAD', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    public static function options(string $path, $callback)
    {
        return static::addRoute('OPTIONS', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    public static function any(string $path, $callback)
    {
        return static::addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $callback);
    }

    /**
     * @param $method
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    public static function add($method, string $path, $callback)
    {
        return static::addRoute($method, $path, $callback);
    }

    /**
     * @param string|callable $path
     * @param callable|null $callback
     * @return static
     */
    public static function group($path, callable $callback = null)
    {
        if ($callback === null) {
            $callback = $path;
            $path = '';
        }
        $previous_group_prefix = static::$_groupPrefix;
        static::$_groupPrefix = $previous_group_prefix . $path;
        $instance = static::$_instance = new static;
        static::$_collector->addGroup($path, $callback);
        static::$_instance = null;
        static::$_groupPrefix = $previous_group_prefix;
        return $instance;
    }

    /**
     * @param string $name
     * @param string $controller
     * @param array $options
     * @return void
     */
    public static function resource(string $name, string $controller, array $options = [])
    {
        $name = trim($name, '/');
        if (\is_array($options) && !empty($options)) {
            $diff_options = \array_diff($options, ['index', 'create', 'store', 'update', 'show', 'edit', 'destroy', 'recovery']);
            if (!empty($diff_options)) {
                foreach ($diff_options as $action) {
                    static::any("/{$name}/{$action}[/{id}]", [$controller, $action])->name("{$name}.{$action}");
                }
            }
            // 注册路由 由于顺序不同会导致路由无效 因此不适用循环注册
            if (\in_array('index', $options)) static::get("/{$name}", [$controller, 'index'])->name("{$name}.index");
            if (\in_array('create', $options)) static::get("/{$name}/create", [$controller, 'create'])->name("{$name}.create");
            if (\in_array('store', $options)) static::post("/{$name}", [$controller, 'store'])->name("{$name}.store");
            if (\in_array('update', $options)) static::put("/{$name}/{id}", [$controller, 'update'])->name("{$name}.update");
            if (\in_array('show', $options)) static::get("/{$name}/{id}", [$controller, 'show'])->name("{$name}.show");
            if (\in_array('edit', $options)) static::get("/{$name}/{id}/edit", [$controller, 'edit'])->name("{$name}.edit");
            if (\in_array('destroy', $options)) static::delete("/{$name}/{id}", [$controller, 'destroy'])->name("{$name}.destroy");
            if (\in_array('recovery', $options)) static::put("/{$name}/{id}/recovery", [$controller, 'recovery'])->name("{$name}.recovery");
        } else {
            //为空时自动注册所有常用路由
            if (\method_exists($controller, 'index')) static::get("/{$name}", [$controller, 'index'])->name("{$name}.index");
            if (\method_exists($controller, 'create')) static::get("/{$name}/create", [$controller, 'create'])->name("{$name}.create");
            if (\method_exists($controller, 'store')) static::post("/{$name}", [$controller, 'store'])->name("{$name}.store");
            if (\method_exists($controller, 'update')) static::put("/{$name}/{id}", [$controller, 'update'])->name("{$name}.update");
            if (\method_exists($controller, 'show')) static::get("/{$name}/{id}", [$controller, 'show'])->name("{$name}.show");
            if (\method_exists($controller, 'edit')) static::get("/{$name}/{id}/edit", [$controller, 'edit'])->name("{$name}.edit");
            if (\method_exists($controller, 'destroy')) static::delete("/{$name}/{id}", [$controller, 'destroy'])->name("{$name}.destroy");
            if (\method_exists($controller, 'recovery')) static::put("/{$name}/{id}/recovery", [$controller, 'recovery'])->name("{$name}.recovery");
        }
    }

    /**
     * @return RouteObject[]
     */
    public static function getRoutes()
    {
        return static::$_allRoutes;
    }

    /**
     * disableDefaultRoute.
     *
     * @return void
     */
    public static function disableDefaultRoute($plugin = '')
    {
        static::$_disableDefaultRoute[$plugin] = true;
    }

    /**
     * @return bool
     */
    public static function hasDisableDefaultRoute($plugin = '')
    {
        return static::$_disableDefaultRoute[$plugin] ?? false;
    }

    /**
     * @param $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        foreach ($this->_routes as $route) {
            $route->middleware($middleware);
        }
    }

    /**
     * @param RouteObject $route
     */
    public function collect(RouteObject $route)
    {
        $this->_routes[] = $route;
    }

    /**
     * @param $name
     * @param RouteObject $instance
     */
    public static function setByName(string $name, RouteObject $instance)
    {
        static::$_nameList[$name] = $instance;
    }

    /**
     * @param $name
     * @return null|RouteObject
     */
    public static function getByName(string $name)
    {
        return static::$_nameList[$name] ?? null;
    }


    /**
     * @param string $method
     * @param string $path
     * @return array
     */
    public static function dispatch($method, string $path)
    {
        return static::$_dispatcher->dispatch($method, $path);
    }

    /**
     * @param string $path
     * @param callable $callback
     * @return callable|false|string[]
     */
    public static function convertToCallable(string $path, $callback)
    {
        if (\is_string($callback) && \strpos($callback, '@')) {
            $callback = \explode('@', $callback, 2);
        }

        if (!\is_array($callback)) {
            if (!\is_callable($callback)) {
                $call_str = \is_scalar($callback) ? $callback : 'Closure';
                echo "Route $path $call_str is not callable\n";
                return false;
            }
        } else {
            $callback = \array_values($callback);
            if (!isset($callback[1]) || !\class_exists($callback[0]) || !\method_exists($callback[0], $callback[1])) {
                echo "Route $path " . \json_encode($callback) . " is not callable\n";
                return false;
            }
        }

        return $callback;
    }

    /**
     * @param array $methods
     * @param string $path
     * @param callable $callback
     * @return RouteObject
     */
    protected static function addRoute($methods, string $path, $callback)
    {
        $route = new RouteObject($methods, static::$_groupPrefix . $path, $callback);
        static::$_allRoutes[] = $route;

        if ($callback = static::convertToCallable($path, $callback)) {
            static::$_collector->addRoute($methods, $path, ['callback' => $callback, 'route' => $route]);
        }
        if (static::$_instance) {
            static::$_instance->collect($route);
        }
        return $route;
    }

    /**
     * @param array $paths
     * @return void
     */
    public static function load($paths)
    {
        if (!\is_array($paths)) {
            return;
        }
        static::$_dispatcher = simpleDispatcher(function (RouteCollector $route) use ($paths) {
            Route::setCollector($route);
            foreach ($paths as $config_path) {
                $route_config_file = $config_path . '/route.php';
                if (\is_file($route_config_file)) {
                    require_once $route_config_file;
                }
                if (!is_dir($plugin_config_path = $config_path . '/plugin')) {
                    continue;
                }
                $dir_iterator = new \RecursiveDirectoryIterator($plugin_config_path, \FilesystemIterator::FOLLOW_SYMLINKS);
                $iterator = new \RecursiveIteratorIterator($dir_iterator);
                foreach ($iterator as $file) {
                    if ($file->getBaseName('.php') !== 'route') {
                        continue;
                    }
                    $app_config_file = pathinfo($file, PATHINFO_DIRNAME) . '/app.php';
                    if (!is_file($app_config_file)) {
                        continue;
                    }
                    $app_config = include $app_config_file;
                    if (empty($app_config['enable'])) {
                        continue;
                    }
                    require_once $file;
                }
            }
        });
    }

    /**
     * @param RouteCollector $route
     * @return void
     */
    public static function setCollector(RouteCollector $route)
    {
        static::$_collector = $route;
    }

    /**
     * @param callable $callback
     * @return void
     */
    public static function fallback(callable $callback)
    {
        static::$_fallback = $callback;
    }

    /**
     * @return callable|null
     */
    public static function getFallback()
    {
        return static::$_fallback;
    }

    /**
     * @deprecated
     * @return void
     */
    public static function container()
    {

    }

}
