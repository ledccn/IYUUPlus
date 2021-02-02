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
use Webman\App;

/**
 * Class Route
 * @package Webman
 */
class Route
{
    /**
     * @var Route[]
     */
    protected static $_instance = [];

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
     * @var string
     */
    protected static $_groupPrefix = '';

    /**
     * @var Route[]
     */
    protected static $_nameList = [];

    /**
     * @var string|null
     */
    protected $_name = null;

    /**
     * @var array
     */
    protected $_methods = [];

    /**
     * @var string
     */
    protected $_path = '';

    /**
     * @var callable
     */
    protected $_callback = null;

    /**
     * Route constructor.
     * @param $methods
     * @param $path
     */
    public function __construct($methods, $path, $callback)
    {
        $this->_methods = (array) $methods;
        $this->_path = $path;
        $this->_callback = $callback;
    }

    /**
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function get($path, $callback)
    {
        return static::addRoute('GET', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function post($path, $callback)
    {
        return static::addRoute('POST', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function put($path, $callback)
    {
        return static::addRoute('PUT', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function patch($path, $callback)
    {
        return static::addRoute('PATCH', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function delete($path, $callback)
    {
        return static::addRoute('DELETE', $path, $callback);
    }
    
    /**
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function head($path, $callback)
    {
        return static::addRoute('HEAD', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function options($path, $callback)
    {
        return static::addRoute('OPTIONS', $path, $callback);
    }

    /**
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function any($path, $callback)
    {
        return static::addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $callback);
    }

    /**
     * @param $method
     * @param $path
     * @param $callback
     * @return Route
     */
    public static function add($method, $path, $callback)
    {
        return static::addRoute($method, $path, $callback);
    }
    
    /**
     * @param $path
     * @param $callback
     */
    public static function group($path, $callback)
    {
        static::$_groupPrefix = $path;
        static::$_collector->addGroup($path, $callback);
        static::$_groupPrefix = '';
    }

    /**
     * @return mixed|null
     */
    public function getName()
    {
        return $this->_name ?? null;
    }

    /**
     * @param $name
     * @return $this
     */
    public function name($name)
    {
        $this->_name = $name;
        static::$_nameList[$name] = $this;
        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * @return array
     */
    public function getMethods()
    {
        return $this->_methods;
    }

    /**
     * @return callable
     */
    public function getCallback()
    {
        return $this->_callback;
    }

    /**
     * @param $name
     * @return null|Route
     */
    public static function getByName($name)
    {
        return static::$_nameList[$name] ?? null;
    }

    /**
     * @param $parameters
     * @return string
     */
    public function url($parameters = [])
    {
        if (empty($parameters)) {
            return $this->_path;
        }
        return preg_replace_callback('/\{(.*?)(?:\:[^\}]*?)*?\}/', function ($matches) use ($parameters) {
            if (isset($parameters[$matches[1]])) {
                return $parameters[$matches[1]];
            }
            return $matches[0];
        }, $this->_path);
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
        if (\is_array($callback)) {
            $callback = \array_values($callback);
        }
        if (\is_callable($callback)) {
            if (\is_array($callback) && \is_string($callback[0])) {
                return [App::container()->get($callback[0]), $callback[1]];
            }
            return $callback;
        }
        $callback = \explode('@', $callback);
        if (isset($callback[1]) && \class_exists($callback[0]) && \is_callable([App::container()->get($callback[0]), $callback[1]])) {
            return [App::container()->get($callback[0]), $callback[1]];
        }
        echo "Route set to $path is not callable\n";
        return false;
    }

    /**
     * @param $method
     * @param $path
     * @param $callback
     * @return static
     */
    protected static function addRoute($method, $path, $callback)
    {
        static::$_hasRoute = true;
        $route = new static($method, static::$_groupPrefix . $path, $callback);
        if ($callback = static::convertToCallable($path, $callback)) {
            static::$_collector->addRoute($method, $path, $callback);
        }
        return $route;
    }

    /**
     * @return bool
     */
    public static function load($route_config_file)
    {
        static::$_dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $route) use ($route_config_file) {
            Route::setCollector($route);
            if (\is_file($route_config_file)) {
                require_once $route_config_file;
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
}
