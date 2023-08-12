<?php
namespace Webman\Console;

use Doctrine\Inflector\InflectorFactory;

class Util
{
    public static function nameToNamespace($name)
    {
        $namespace = ucfirst($name);
        $namespace = preg_replace_callback(['/-([a-zA-Z])/', '/(\/[a-zA-Z])/'], function ($matches) {
            return strtoupper($matches[1]);
        }, $namespace);
        return str_replace('/', '\\' ,ucfirst($namespace));
    }

    public static function classToName($class)
    {
        $class = lcfirst($class);
        return preg_replace_callback(['/([A-Z])/'], function ($matches) {
            return '_' . strtolower($matches[1]);
        }, $class);
    }

    public static function nameToClass($class)
    {
        $class = preg_replace_callback(['/-([a-zA-Z])/', '/_([a-zA-Z])/'], function ($matches) {
            return strtoupper($matches[1]);
        }, $class);

        if (!($pos = strrpos($class, '/'))) {
            $class = ucfirst($class);
        } else {
            $path = substr($class, 0, $pos);
            $class = ucfirst(substr($class, $pos + 1));
            $class = "$path/$class";
        }
        return $class;
    }

    public static function guessPath($base_path, $name, $return_full_path = false)
    {
        if (!is_dir($base_path)) {
            return false;
        }
        $names = explode('/', trim(strtolower($name), '/'));
        $realname = [];
        $path = $base_path;
        foreach ($names as $name) {
            $finded = false;
            foreach (scandir($path) ?: [] as $tmp_name) {
                if (strtolower($tmp_name) === $name && is_dir("$path/$tmp_name")) {
                    $path = "$path/$tmp_name";
                    $realname[] = $tmp_name;
                    $finded = true;
                    break;
                }
            }
            if (!$finded) {
                return false;
            }
        }
        $realname = implode(DIRECTORY_SEPARATOR, $realname);
        return $return_full_path ? get_realpath($base_path . DIRECTORY_SEPARATOR . $realname) : $realname;
    }
}
