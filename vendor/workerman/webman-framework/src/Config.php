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

class Config
{

    /**
     * @var array
     */
    protected static $_config = [];

    /**
     * @var string
     */
    protected static $_configPath = '';

    /**
     * @var bool
     */
    protected static $_loaded = false;

    /**
     * @param string $config_path
     * @param array $exclude_file
     * @param string|null $key
     * @return void
     */
    public static function load(string $config_path, array $exclude_file = [], string $key = null)
    {
        static::$_configPath = $config_path;
        if (!$config_path) {
            return;
        }
        static::$_loaded = false;
        $config = static::loadFromDir($config_path, $exclude_file);
        if (!$config) {
            static::$_loaded = true;
            return;
        }
        if ($key !== null) {
            foreach (\array_reverse(\explode('.', $key)) as $k) {
                $config = [$k => $config];
            }
        }
        static::$_config = \array_replace_recursive(static::$_config, $config);
        static::formatConfig();
        static::$_loaded = true;
    }

    /**
     * This deprecated method will certainly be removed in the future
     * 
     * @deprecated
     * @param string $config_path
     * @param array $exclude_file
     * @return void
     */
    public static function reload(string $config_path, array $exclude_file = [])
    {
        static::load($config_path, $exclude_file);
    }

    /**
     * @return void
     */
    public static function clear()
    {
        static::$_config = [];
    }

    /**
     * @return void
     */
    protected static function formatConfig()
    {
        $config = static::$_config;
        // Merge log config
        foreach ($config['plugin'] ?? [] as $firm => $projects) {
            if (isset($projects['app'])) {
                foreach ($projects['log'] ?? [] as $key => $item) {
                    $config['log']["plugin.$firm.$key"] = $item;
                }
            }
            foreach ($projects as $name => $project) {
                if (!\is_array($project)) {
                    continue;
                }
                foreach ($project['log'] ?? [] as $key => $item) {
                    $config['log']["plugin.$firm.$name.$key"] = $item;
                }
            }
        }
        // Merge database config
        foreach ($config['plugin'] ?? [] as $firm => $projects) {
            if (isset($projects['app'])) {
                foreach ($projects['database']['connections'] ?? [] as $key => $connection) {
                    $config['database']['connections']["plugin.$firm.$key"] = $connection;
                }
            }
            foreach ($projects as $name => $project) {
                if (!\is_array($project)) {
                    continue;
                }
                foreach ($project['database']['connections'] ?? [] as $key => $connection) {
                    $config['database']['connections']["plugin.$firm.$name.$key"] = $connection;
                }
            }
        }
        if (!empty($config['database']['connections'])) {
            $config['database']['default'] = $config['database']['default'] ?? key($config['database']['connections']);
        }
        // Merge thinkorm config
        foreach ($config['plugin'] ?? [] as $firm => $projects) {
            if (isset($projects['app'])) {
                foreach ($projects['thinkorm']['connections'] ?? [] as $key => $connection) {
                    $config['thinkorm']['connections']["plugin.$firm.$key"] = $connection;
                }
            }
            foreach ($projects as $name => $project) {
                if (!\is_array($project)) {
                    continue;
                }
                foreach ($project['thinkorm']['connections'] ?? [] as $key => $connection) {
                    $config['thinkorm']['connections']["plugin.$firm.$name.$key"] = $connection;
                }
            }
        }
        if (!empty($config['thinkorm']['connections'])) {
            $config['thinkorm']['default'] = $config['thinkorm']['default'] ?? \key($config['thinkorm']['connections']);
        }
        // Merge redis config
        foreach ($config['plugin'] ?? [] as $firm => $projects) {
            if (isset($projects['app'])) {
                foreach ($projects['redis'] ?? [] as $key => $connection) {
                    $config['redis']["plugin.$firm.$key"] = $connection;
                }
            }
            foreach ($projects as $name => $project) {
                if (!\is_array($project)) {
                    continue;
                }
                foreach ($project['redis'] ?? [] as $key => $connection) {
                    $config['redis']["plugin.$firm.$name.$key"] = $connection;
                }
            }
        }
        static::$_config = $config;
    }

    /**
     * @param string $config_path
     * @param array $exclude_file
     * @return array
     */
    public static function loadFromDir(string $config_path, array $exclude_file = [])
    {
        $all_config = [];
        $dir_iterator = new \RecursiveDirectoryIterator($config_path, \FilesystemIterator::FOLLOW_SYMLINKS);
        $iterator = new \RecursiveIteratorIterator($dir_iterator);
        foreach ($iterator as $file) {
            /** var SplFileInfo $file */
            if (\is_dir($file) || $file->getExtension() != 'php' || \in_array($file->getBaseName('.php'), $exclude_file)) {
                continue;
            }
            $app_config_file = $file->getPath() . '/app.php';
            if (!\is_file($app_config_file)) {
                continue;
            }
            $relative_path = \str_replace($config_path . DIRECTORY_SEPARATOR, '', substr($file, 0, -4));
            $explode = \array_reverse(\explode(DIRECTORY_SEPARATOR, $relative_path));
            if (\count($explode) >= 2) {
                $app_config = include $app_config_file;
                if (empty($app_config['enable'])) {
                    continue;
                }
            }
            $config = include $file;
            foreach ($explode as $section) {
                $tmp = [];
                $tmp[$section] = $config;
                $config = $tmp;
            }
            $all_config = \array_replace_recursive($all_config, $config);
        }
        return $all_config;
    }

    /**
     * @param string|null $key
     * @param mixed $default
     * @return array|mixed|void|null
     */
    public static function get(string $key = null, $default = null)
    {
        if ($key === null) {
            return static::$_config;
        }
        $key_array = \explode('.', $key);
        $value = static::$_config;
        $finded = true;
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                if (static::$_loaded) {
                    return $default;
                }
                $finded = false;
                break;
            }
            $value = $value[$index];
        }
        if ($finded) {
            return $value;
        }
        return static::read($key, $default);
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return array|mixed|null
     */
    protected static function read(string $key, $default = null)
    {
        $path = static::$_configPath;
        if ($path === '') {
            return $default;
        }
        $keys = $key_array = \explode('.', $key);
        foreach ($key_array as $index => $section) {
            unset($keys[$index]);
            if (\is_file($file = "$path/$section.php")) {
                $config = include $file;
                return static::find($keys, $config, $default);
            }
            if (!\is_dir($path = "$path/$section")) {
                return $default;
            }
        }
        return $default;
    }

    /**
     * @param array $key_array
     * @param mixed $stack
     * @param mixed $default
     * @return array|mixed
     */
    protected static function find(array $key_array, $stack, $default)
    {
        if (!\is_array($stack)) {
            return $default;
        }
        $value = $stack;
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }

}
