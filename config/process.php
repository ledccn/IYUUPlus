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

use support\view\Raw;
use support\view\Twig;
use support\view\Blade;
use support\view\ThinkPHP;

return [
    // 文件更新检测
    'monitor' => [
        'handler' => process\Monitor::class,
        'reloadable' => false,
        'constructor' => [
            // 监控这些目录
            'monitor_dir' => [
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/src',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ],
            // Files with these suffixes will be monitored
            'monitor_extensions' => [
                'php', 'html', 'htm', 'env'
            ],
        ]
    ],
    'IYUUTask' => [
        'handler' => process\Task::class
    ],
];
