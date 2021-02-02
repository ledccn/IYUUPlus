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
     * @param $config_path
     * @param array $exclude_file
     */
    public static function load($config_path, $exclude_file = [])
    {
        if (\strpos($config_path, 'phar://') === false) {
            foreach (\glob($config_path . '/*.php') as $file) {
                $basename = \basename($file, '.php');
                if (\in_array($basename, $exclude_file)) {
                    continue;
                }
                $config = include $file;
                static::$_config[$basename] = $config;
            }
        } else {
            $handler = \opendir($config_path);
            while (($filename = \readdir($handler)) !== false) {
                if ($filename != "." && $filename != "..") {
                    $basename = \basename($filename, ".php");
                    if (\in_array($basename, $exclude_file)) {
                        continue;
                    }
                    $config = include($config_path . '/' . $filename);
                    static::$_config[$basename] = $config;
                }
            }
            \closedir($handler);
        }
    }

    /**
     * @param null $key
     * @param null $default
     * @return array|mixed|null
     */
    public static function get($key = null, $default = null)
    {
        if ($key === null) {
            return static::$_config;
        }
        $key_array = \explode('.', $key);
        $value = static::$_config;
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }

    /**
     * @param $config_path
     * @param array $exclude_file
     */
    public static function reload($config_path, $exclude_file = [])
    {
        static::$_config = [];
        static::load($config_path, $exclude_file);
    }
}
