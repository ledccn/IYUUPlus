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

/**
 * Class Util
 * @package Webman
 */
class Util
{
    /**
     * @param string $path
     * @return array
     */
    public static function scanDir(string $base_path, $with_base_path = true): array
    {
        if (!is_dir($base_path)) {
            return [];
        }
        $paths = \array_diff(\scandir($base_path), array('.', '..')) ?: [];
        return $with_base_path ? \array_map(function($path) use ($base_path) {
            return $base_path . DIRECTORY_SEPARATOR . $path;
        }, $paths) : $paths;
    }

}
