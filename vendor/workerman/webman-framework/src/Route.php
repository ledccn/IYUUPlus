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
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Webman\Route\Route as RouteObject;
use function array_diff;
use function array_values;
use function class_exists;
use function explode;
use function FastRoute\simpleDispatcher;
use function in_array;
use function is_array;
use function is_callable;
use function is_file;
use function is_scalar;
use function is_string;
use function json_encode;
use function method_exists;
use function strpos;

/**
 * Class Route
 * @package Webman
 */
class Route
{
    /**
     * @var Route
     */
    protected static $instance = null;

    /**
     * @var GroupCountBased
     */
    protected static $dispatcher = null;

    /**
     * @var RouteCollector
     */
    protected static $collector = null;

    /**
     * @var null|callable
     */
    protected static $fallback = [];

    /**
     * @var array
     */
    protected static $nameList = [];

    /**
     * @var string
     */
    protected static $groupPrefix = '';

    /**
     * @var bool
     */
    protected static $disableDefaultRoute = [];

    /**
     * @var RouteObject[]
     */
    protected static $allRoutes = [];

    /**
     * @var RouteObject[]
     */
    protected $routes = [];

    /**
     * @var Route[]
     */
    protected $children = [];

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function get(string $path, $callback): RouteObject
    {
        return static::addRoute('GET', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function post(string $path, $callback): RouteObject
    {
        return static::addRoute('POST', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function put(string $path, $callback): RouteObject
    {
        return static::addRoute('PUT', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function patch(string $path, $callback): RouteObject
    {
        return static::addRoute('PATCH', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function delete(string $path, $callback): RouteObject
    {
        return static::addRoute('DELETE', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function head(string $path, $callback): RouteObject
    {
        return static::addRoute('HEAD', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function options(string $path, $callback): RouteObject
    {
        return static::addRoute('OPTIONS', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function any(string $path, $callback): RouteObject
    {
        return static::addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $callback);
    }

    /**
     * @param $method
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    public static function add($method, string $path, $callback): RouteObject
    {
        return static::addRoute($method, $path, $callback);
    }

    /**
     * @param string|callable $path
     * @param callable|null $callback
     * @return static
     */
    public static function group($path, callable $callback = null): Route
    {
        if ($callback === null) {
            $callback = $path;
            $path = '';
        }
        $previousGroupPrefix = static::$groupPrefix;
        static::$groupPrefix = $previousGroupPrefix . $path;
        $previousInstance = static::$instance;
        $instance = static::$instance = new static;
        static::$collector->addGroup($path, $callback);
        static::$groupPrefix = $previousGroupPrefix;
        static::$instance = $previousInstance;
        if ($previousInstance) {
            $previousInstance->addChild($instance);
        }
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
        if (is_array($options) && !empty($options)) {
            $diffOptions = array_diff($options, ['index', 'create', 'store', 'update', 'show', 'edit', 'destroy', 'recovery']);
            if (!empty($diffOptions)) {
                foreach ($diffOptions as $action) {
                    static::any("/$name/{$action}[/{id}]", [$controller, $action])->name("$name.{$action}");
                }
            }
            // 注册路由 由于顺序不同会导致路由无效 因此不适用循环注册
            if (in_array('index', $options)) static::get("/$name", [$controller, 'index'])->name("$name.index");
            if (in_array('create', $options)) static::get("/$name/create", [$controller, 'create'])->name("$name.create");
            if (in_array('store', $options)) static::post("/$name", [$controller, 'store'])->name("$name.store");
            if (in_array('update', $options)) static::put("/$name/{id}", [$controller, 'update'])->name("$name.update");
            if (in_array('show', $options)) static::get("/$name/{id}", [$controller, 'show'])->name("$name.show");
            if (in_array('edit', $options)) static::get("/$name/{id}/edit", [$controller, 'edit'])->name("$name.edit");
            if (in_array('destroy', $options)) static::delete("/$name/{id}", [$controller, 'destroy'])->name("$name.destroy");
            if (in_array('recovery', $options)) static::put("/$name/{id}/recovery", [$controller, 'recovery'])->name("$name.recovery");
        } else {
            //为空时自动注册所有常用路由
            if (method_exists($controller, 'index')) static::get("/$name", [$controller, 'index'])->name("$name.index");
            if (method_exists($controller, 'create')) static::get("/$name/create", [$controller, 'create'])->name("$name.create");
            if (method_exists($controller, 'store')) static::post("/$name", [$controller, 'store'])->name("$name.store");
            if (method_exists($controller, 'update')) static::put("/$name/{id}", [$controller, 'update'])->name("$name.update");
            if (method_exists($controller, 'show')) static::get("/$name/{id}", [$controller, 'show'])->name("$name.show");
            if (method_exists($controller, 'edit')) static::get("/$name/{id}/edit", [$controller, 'edit'])->name("$name.edit");
            if (method_exists($controller, 'destroy')) static::delete("/$name/{id}", [$controller, 'destroy'])->name("$name.destroy");
            if (method_exists($controller, 'recovery')) static::put("/$name/{id}/recovery", [$controller, 'recovery'])->name("$name.recovery");
        }
    }

    /**
     * @return RouteObject[]
     */
    public static function getRoutes(): array
    {
        return static::$allRoutes;
    }

    /**
     * disableDefaultRoute.
     *
     * @return void
     */
    public static function disableDefaultRoute($plugin = '')
    {
        static::$disableDefaultRoute[$plugin] = true;
    }

    /**
     * @param string $plugin
     * @return bool
     */
    public static function hasDisableDefaultRoute(string $plugin = ''): bool
    {
        return static::$disableDefaultRoute[$plugin] ?? false;
    }

    /**
     * @param $middleware
     * @return $this
     */
    public function middleware($middleware): Route
    {
        foreach ($this->routes as $route) {
            $route->middleware($middleware);
        }
        foreach ($this->getChildren() as $child) {
            $child->middleware($middleware);
        }
        return $this;
    }

    /**
     * @param RouteObject $route
     */
    public function collect(RouteObject $route)
    {
        $this->routes[] = $route;
    }

    /**
     * @param string $name
     * @param RouteObject $instance
     */
    public static function setByName(string $name, RouteObject $instance)
    {
        static::$nameList[$name] = $instance;
    }

    /**
     * @param string $name
     * @return null|RouteObject
     */
    public static function getByName(string $name): ?RouteObject
    {
        return static::$nameList[$name] ?? null;
    }

    /**
     * @param Route $route
     * @return void
     */
    public function addChild(Route $route)
    {
        $this->children[] = $route;
    }

    /**
     * @return Route[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param string $method
     * @param string $path
     * @return array
     */
    public static function dispatch(string $method, string $path): array
    {
        return static::$dispatcher->dispatch($method, $path);
    }

    /**
     * @param string $path
     * @param callable|mixed $callback
     * @return callable|false|string[]
     */
    public static function convertToCallable(string $path, $callback)
    {
        if (is_string($callback) && strpos($callback, '@')) {
            $callback = explode('@', $callback, 2);
        }

        if (!is_array($callback)) {
            if (!is_callable($callback)) {
                $callStr = is_scalar($callback) ? $callback : 'Closure';
                echo "Route $path $callStr is not callable\n";
                return false;
            }
        } else {
            $callback = array_values($callback);
            if (!isset($callback[1]) || !class_exists($callback[0]) || !method_exists($callback[0], $callback[1])) {
                echo "Route $path " . json_encode($callback) . " is not callable\n";
                return false;
            }
        }

        return $callback;
    }

    /**
     * @param array|string $methods
     * @param string $path
     * @param callable|mixed $callback
     * @return RouteObject
     */
    protected static function addRoute($methods, string $path, $callback): RouteObject
    {
        $route = new RouteObject($methods, static::$groupPrefix . $path, $callback);
        static::$allRoutes[] = $route;

        if ($callback = static::convertToCallable($path, $callback)) {
            static::$collector->addRoute($methods, $path, ['callback' => $callback, 'route' => $route]);
        }
        if (static::$instance) {
            static::$instance->collect($route);
        }
        return $route;
    }

    /**
     * Load.
     * @param mixed $paths
     * @return void
     */
    public static function load($paths)
    {
        if (!is_array($paths)) {
            return;
        }
        static::$dispatcher = simpleDispatcher(function (RouteCollector $route) use ($paths) {
            Route::setCollector($route);
            foreach ($paths as $configPath) {
                $routeConfigFile = $configPath . '/route.php';
                if (is_file($routeConfigFile)) {
                    require_once $routeConfigFile;
                }
                if (!is_dir($pluginConfigPath = $configPath . '/plugin')) {
                    continue;
                }
                $dirIterator = new RecursiveDirectoryIterator($pluginConfigPath, FilesystemIterator::FOLLOW_SYMLINKS);
                $iterator = new RecursiveIteratorIterator($dirIterator);
                foreach ($iterator as $file) {
                    if ($file->getBaseName('.php') !== 'route') {
                        continue;
                    }
                    $appConfigFile = pathinfo($file, PATHINFO_DIRNAME) . '/app.php';
                    if (!is_file($appConfigFile)) {
                        continue;
                    }
                    $appConfig = include $appConfigFile;
                    if (empty($appConfig['enable'])) {
                        continue;
                    }
                    require_once $file;
                }
            }
        });
    }

    /**
     * SetCollector.
     * @param RouteCollector $route
     * @return void
     */
    public static function setCollector(RouteCollector $route)
    {
        static::$collector = $route;
    }

    /**
     * Fallback.
     * @param callable|mixed $callback
     * @param string $plugin
     * @return void
     */
    public static function fallback(callable $callback, string $plugin = '')
    {
        static::$fallback[$plugin] = $callback;
    }

    /**
     * GetFallBack.
     * @param string $plugin
     * @return callable|null
     */
    public static function getFallback(string $plugin = ''): ?callable
    {
        return static::$fallback[$plugin] ?? null;
    }

    /**
     * @return void
     * @deprecated
     */
    public static function container()
    {

    }

}
