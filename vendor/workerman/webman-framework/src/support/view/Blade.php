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

use Jenssegers\Blade\Blade as BladeView;
use Webman\View;
use function app_path;
use function array_merge;
use function base_path;
use function config;
use function is_array;
use function request;
use function runtime_path;

/**
 * Class Blade
 * composer require jenssegers/blade
 * @package support\view
 */
class Blade implements View
{
    /**
     * @var array
     */
    protected static $vars = [];

    /**
     * Assign.
     * @param string|array $name
     * @param mixed $value
     */
    public static function assign($name, $value = null)
    {
        static::$vars = array_merge(static::$vars, is_array($name) ? $name : [$name => $value]);
    }

    /**
     * Render.
     * @param string $template
     * @param array $vars
     * @param string|null $app
     * @param string|null $plugin
     * @return string
     */
    public static function render(string $template, array $vars, string $app = null, string $plugin = null): string
    {
        static $views = [];
        $request = request();
        $plugin = $plugin === null ? ($request->plugin ?? '') : $plugin;
        $app = $app === null ? $request->app : $app;
        $configPrefix = $plugin ? "plugin.$plugin." : '';
        $baseViewPath = $plugin ? base_path() . "/plugin/$plugin/app" : app_path();
        $key = "$plugin-$app";
        if (!isset($views[$key])) {
            $viewPath = $app === '' ? "$baseViewPath/view" : "$baseViewPath/$app/view";
            $views[$key] = new BladeView($viewPath, runtime_path() . '/views');
            $extension = config("{$configPrefix}view.extension");
            if ($extension) {
                $extension($views[$key]);
            }
        }
        $vars = array_merge(static::$vars, $vars);
        $content = $views[$key]->render($template, $vars);
        static::$vars = [];
        return $content;
    }
}
