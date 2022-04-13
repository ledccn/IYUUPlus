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
use Psr\Container\ContainerInterface;
use Webman\Route\Route as RouteObject;
use function FastRoute\simpleDispatcher;

/**
 * Class Route
 * @package Webman
 */
class Route
{
    /**
     * @var ContainerInterface
     */
    protected static $_container = null;

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
     * @var bool
     */
    protected static $_hasRoute = false;

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
    protected static $_disableDefaultRoute = false;

    /**
     * @var RouteObject[]
     */
    protected static $_allRoutes = [];

    /**
     * @var RouteObject[]
     */
    protected $_routes = [];

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public static function get($path, $callback)
    {
        return static::addRoute('GET', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public static function post($path, $callback)
    {
        return static::addRoute('POST', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public static function put($path, $callback)
    {
        return static::addRoute('PUT', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public static function patch($path, $callback)
    {
        return static::addRoute('PATCH', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public static function delete($path, $callback)
    {
        return static::addRoute('DELETE', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public static function head($path, $callback)
    {
        return static::addRoute('HEAD', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public static function options($path, $callback)
    {
        return static::addRoute('OPTIONS', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public static function any($path, $callback)
    {
        return static::addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $callback);
    }

    /**
     * @param $method
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    public static function add($method, $path, $callback)
    {
        return static::addRoute($method, $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function group($path, $callback)
    {
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
        if(is_array($options) && !empty($options)){
            $diffOptions = array_diff($options,['index','create','store','update','show','edit','destroy','recovery']);
            if(!empty($diffOptions)){
                foreach ($diffOptions as $action) {
                    static::any("/{$name}/{$action}[/{id}]", [$controller,$action])->name("{$name}.{$action}");
                }
            }
            // 注册路由 由于顺序不同会导致路由无效 因此不适用循环注册
            if (in_array('index',$options)) static::get( "/{$name}", [$controller,'index'])->name("{$name}.index");
            if (in_array('create',$options)) static::get( "/{$name}/create", [$controller,'create'])->name("{$name}.create");
            if (in_array('store',$options)) static::post( "/{$name}", [$controller,'store'])->name("{$name}.store");
            if (in_array('update',$options)) static::put("/{$name}/{id}", [$controller,'update'])->name("{$name}.update");
            if (in_array('show',$options)) static::get( "/{$name}/{id}", [$controller,'show'])->name("{$name}.show");
            if (in_array('edit',$options)) static::get( "/{$name}/{id}/edit", [$controller,'edit'])->name("{$name}.edit");
            if (in_array('destroy',$options)) static::delete("/{$name}/{id}", [$controller,'destroy'])->name("{$name}.destroy");
            if (in_array('recovery',$options)) static::put( "/{$name}/{id}/recovery", [$controller,'recovery'])->name("{$name}.recovery");
        }else{
            //为空时自动注册所有常用路由
            if (method_exists($controller, 'index')) static::get("/{$name}", [$controller,'index'])->name("{$name}.index");
            if (method_exists($controller, 'create')) static::get("/{$name}/create", [$controller,'create'])->name("{$name}.create");
            if (method_exists($controller, 'store')) static::post("/{$name}", [$controller,'store'])->name("{$name}.store");
            if (method_exists($controller, 'update')) static::put( "/{$name}/{id}", [$controller,'update'])->name("{$name}.update");
            if (method_exists($controller, 'show')) static::get("/{$name}/{id}", [$controller,'show'])->name("{$name}.show");
            if (method_exists($controller, 'edit')) static::get("/{$name}/{id}/edit", [$controller,'edit'])->name("{$name}.edit");
            if (method_exists($controller, 'destroy')) static::delete( "/{$name}/{id}", [$controller,'destroy'])->name("{$name}.destroy");
            if (method_exists($controller, 'recovery')) static::put("/{$name}/{id}/recovery", [$controller,'recovery'])->name("{$name}.recovery");
        }
    }

    /**
     * @return array
     */
    public static function getRoutes()
    {
        return static::$_allRoutes;
    }

    /**
     * disableDefaultRoute.
     */
    public static function disableDefaultRoute()
    {
        static::$_disableDefaultRoute = true;
    }

    /**
     * @return bool
     */
    public static function hasDisableDefaultRoute()
    {
        return static::$_disableDefaultRoute === true;
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
    public static function setByName($name, RouteObject $instance)
    {
        static::$_nameList[$name] = $instance;
    }

    /**
     * @param $name
     * @return null|RouteObject
     */
    public static function getByName($name)
    {
        return static::$_nameList[$name] ?? null;
    }


    /**
     * @param $method
     * @param $path
     * @return array
     */
    public static function dispatch($method, $path)
    {
        return static::$_dispatcher->dispatch($method, $path);
    }

    /**
     * @param $path
     * @param $callback
     * @return array|bool|callable
     */
    public static function convertToCallable($path, $callback)
    {
        if (\is_string($callback) && \strpos($callback, '@')) {
            $callback = \explode('@', $callback, 2);
        }

        if (\is_array($callback)) {
            $callback = \array_values($callback);
            if (isset($callback[1]) && \is_string($callback[0]) && \class_exists($callback[0])) {
                $callback = [static::container()->get($callback[0]), $callback[1]];
            }
        }

        if (!\is_callable($callback)) {
            echo "Route set to $path is not callable\n";
            return false;
        }

        return $callback;
    }

    /**
     * @param $methods
     * @param $path
     * @param $callback
     * @return RouteObject
     */
    protected static function addRoute($methods, $path, $callback)
    {
        static::$_hasRoute = true;
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
     * @return bool
     */
    public static function load($config_path)
    {
        if (!is_dir($config_path)) {
            $config_path = pathinfo($config_path, PATHINFO_DIRNAME);
        }
        static::$_dispatcher = simpleDispatcher(function (RouteCollector $route) use ($config_path) {
            Route::setCollector($route);
            $route_config_file = $config_path . '/route.php';
            if (\is_file($route_config_file)) {
                require_once $route_config_file;
            }
            foreach (glob($config_path.'/plugin/*/*/route.php') as $file) {
                $app_config_file = pathinfo($file, PATHINFO_DIRNAME).'/app.php';
                if (!is_file($app_config_file)) {
                    continue;
                }
                $app_config = include $app_config_file;
                if (empty($app_config['enable'])) {
                    continue;
                }
                require_once $file;
            }
        });
        return static::$_hasRoute;
    }

    /**
     * @param $route
     */
    public static function setCollector($route)
    {
        static::$_collector = $route;
    }

    /**
     * @param callable $callback
     */
    public static function fallback(callable $callback) {
        if (is_callable($callback)) {
            static::$_fallback = $callback;
        }
    }

    /**
     * @return callable|null
     */
    public static function getFallback() {
        return is_callable(static::$_fallback) ? static::$_fallback : null;
    }

    /**
     * @param $container
     * @return ContainerInterface
     */
    public static function container($container = null)
    {
        if ($container) {
            static::$_container = $container;
        }
        if (!static::$_container) {
            static::$_container = App::container();
        }
        return static::$_container;
    }
}
