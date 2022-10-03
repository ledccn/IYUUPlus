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

namespace support\view;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Webman\View;

/**
 * Class Blade
 * @package support\view
 */
class Twig implements View
{
    /**
     * @var array
     */
    protected static $_vars = [];

    /**
     * @param string|array $name
     * @param mixed $value
     */
    public static function assign($name, $value = null)
    {
        static::$_vars = \array_merge(static::$_vars, \is_array($name) ? $name : [$name => $value]);
    }

    /**
     * @param string $template
     * @param array $vars
     * @param string|null $app
     * @return string
     */
    public static function render(string $template, array $vars, string $app = null)
    {
        static $views = [];
        $request = \request();
        $plugin = $request->plugin ?? '';
        $app = $app === null ? $request->app : $app;
        $config_prefix = $plugin ? "plugin.$plugin." : '';
        $view_suffix = \config("{$config_prefix}view.options.view_suffix", 'html');
        $key = "{$plugin}-{$request->app}";
        if (!isset($views[$key])) {
            $base_view_path = $plugin ? \base_path() . "/plugin/$plugin/app" : \app_path();
            $view_path = $app === '' ? "$base_view_path/view/" : "$base_view_path/$app/view/";
            $views[$key] = new Environment(new FilesystemLoader($view_path), \config("{$config_prefix}view.options", []));
        }
        $vars = \array_merge(static::$_vars, $vars);
        $content = $views[$key]->render("$template.$view_suffix", $vars);
        static::$_vars = [];
        return $content;
    }
}
