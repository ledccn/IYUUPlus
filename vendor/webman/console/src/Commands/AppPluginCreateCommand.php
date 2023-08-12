<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webman\Console\Util;

class AppPluginCreateCommand extends Command
{
    protected static $defaultName = 'app-plugin:create';
    protected static $defaultDescription = 'App Plugin Create';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'App plugin name');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $output->writeln("Create App Plugin $name");

        if (strpos($name, '/') !== false) {
            $output->writeln('<error>Bad name, name must not contain character \'/\'</error>');
            return self::FAILURE;
        }

        // Create dir config/plugin/$name
        if (is_dir($plugin_config_path = base_path()."/plugin/$name")) {
            $output->writeln("<error>Dir $plugin_config_path already exists</error>");
            return self::FAILURE;
        }

        $this->createAll($name);

        return self::SUCCESS;
    }

    /**
     * @param $name
     * @return void
     */
    protected function createAll($name)
    {
        $base_path = base_path();
        $this->mkdir("$base_path/plugin/$name/app/controller", 0777, true);
        $this->mkdir("$base_path/plugin/$name/app/model", 0777, true);
        $this->mkdir("$base_path/plugin/$name/app/middleware", 0777, true);
        $this->mkdir("$base_path/plugin/$name/app/view/index", 0777, true);
        $this->mkdir("$base_path/plugin/$name/config", 0777, true);
        $this->mkdir("$base_path/plugin/$name/public", 0777, true);
        $this->mkdir("$base_path/plugin/$name/api", 0777, true);
        $this->createFunctionsFile("$base_path/plugin/$name/app/functions.php");
        $this->createControllerFile("$base_path/plugin/$name/app/controller/IndexController.php", $name);
        $this->createViewFile("$base_path/plugin/$name/app/view/index/index.html");
        $this->createConfigFiles("$base_path/plugin/$name/config", $name);
        $this->createApiFiles("$base_path/plugin/$name/api", $name);
    }

    /**
     * @param $path
     * @return void
     */
    protected function mkdir($path)
    {
        if (is_dir($path)) {
            return;
        }
        echo "Create $path\r\n";
        mkdir($path, 0777, true);
    }

    /**
     * @param $path
     * @param $name
     * @return void
     */
    protected function createControllerFile($path, $name)
    {
        $content = <<<EOF
<?php

namespace plugin\\$name\\app\\controller;

use support\\Request;

class IndexController
{

    public function index()
    {
        return view('index/index', ['name' => '$name']);
    }

}

EOF;
        file_put_contents($path, $content);

    }

    /**
     * @param $path
     * @return void
     */
    protected function createViewFile($path)
    {
        $content = <<<EOF
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="/favicon.ico"/>
    <title>webman app plugin</title>

</head>
<body>
hello <?=htmlspecialchars(\$name)?>
</body>
</html>


EOF;
        file_put_contents($path, $content);

    }


    /**
     * @param $file
     * @return void
     */
    protected function createFunctionsFile($file)
    {
        $content = <<<EOF
<?php
/**
 * Here is your custom functions.
 */



EOF;
        file_put_contents($file, $content);
    }

    /**
     * @param $base
     * @param $name
     * @return void
     */
    protected function createApiFiles($base, $name)
    {
        $content = <<<EOF
<?php

namespace plugin\\$name\api;

use plugin\admin\api\Menu;

class Install
{
    /**
     * 安装
     *
     * @param \$version
     * @return void
     */
    public static function install(\$version)
    {
        // 导入菜单
        if(\$menus = static::getMenus()) {
            Menu::import(\$menus);
        }
    }

    /**
     * 卸载
     *
     * @param \$version
     * @return void
     */
    public static function uninstall(\$version)
    {
        // 删除菜单
        foreach (static::getMenus() as \$menu) {
            Menu::delete(\$menu['key']);
        }
    }

    /**
     * 更新
     *
     * @param \$from_version
     * @param \$to_version
     * @param \$context
     * @return void
     */
    public static function update(\$from_version, \$to_version, \$context = null)
    {
        // 删除不用的菜单
        if (isset(\$context['previous_menus'])) {
            static::removeUnnecessaryMenus(\$context['previous_menus']);
        }
        // 导入新菜单
        if (\$menus = static::getMenus()) {
            Menu::import(\$menus);
        }
    }

    /**
     * 更新前数据收集等
     *
     * @param \$from_version
     * @param \$to_version
     * @return array|array[]
     */
    public static function beforeUpdate(\$from_version, \$to_version)
    {
        // 在更新之前获得老菜单，通过context传递给 update
        return ['previous_menus' => static::getMenus()];
    }

    /**
     * 获取菜单
     *
     * @return array|mixed
     */
    public static function getMenus()
    {
        clearstatcache();
        if (is_file(\$menu_file = __DIR__ . '/../config/menu.php')) {
            \$menus = include \$menu_file;
            return \$menus ?: [];
        }
        return [];
    }

    /**
     * 删除不需要的菜单
     *
     * @param \$previous_menus
     * @return void
     */
    public static function removeUnnecessaryMenus(\$previous_menus)
    {
        \$menus_to_remove = array_diff(Menu::column(\$previous_menus, 'name'), Menu::column(static::getMenus(), 'name'));
        foreach (\$menus_to_remove as \$name) {
            Menu::delete(\$name);
        }
    }

}
EOF;

        file_put_contents("$base/Install.php", $content);

    }

    /**
     * @param $base
     * @param $name
     * @return void
     */
    protected function createConfigFiles($base, $name)
    {
        // app.php
        $content = <<<EOF
<?php

use support\\Request;

return [
    'debug' => true,
    'controller_suffix' => 'Controller',
    'controller_reuse' => false,
    'version' => '1.0.0'
];

EOF;
        file_put_contents("$base/app.php", $content);

        // menu.php
        $content = <<<EOF
<?php

return [];

EOF;
        file_put_contents("$base/menu.php", $content);

        // autoload.php
        $content = <<<EOF
<?php
return [
    'files' => [
        base_path() . '/plugin/$name/app/functions.php',
    ]
];
EOF;
        file_put_contents("$base/autoload.php", $content);

        // container.php
        $content = <<<EOF
<?php
return new Webman\\Container;

EOF;
        file_put_contents("$base/container.php", $content);


        // database.php
        $content = <<<EOF
<?php
return  [];

EOF;
        file_put_contents("$base/database.php", $content);

        // exception.php
        $content = <<<EOF
<?php

return [
    '' => support\\exception\\Handler::class,
];

EOF;
        file_put_contents("$base/exception.php", $content);

        // log.php
        $content = <<<EOF
<?php

return [
    'default' => [
        'handlers' => [
            [
                'class' => Monolog\\Handler\\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/$name.log',
                    7,
                    Monolog\\Logger::DEBUG,
                ],
                'formatter' => [
                    'class' => Monolog\\Formatter\\LineFormatter::class,
                    'constructor' => [null, 'Y-m-d H:i:s', true],
                ],
            ]
        ],
    ],
];

EOF;
        file_put_contents("$base/log.php", $content);

        // middleware.php
        $content = <<<EOF
<?php

return [
    '' => [
        
    ]
];

EOF;
        file_put_contents("$base/middleware.php", $content);

        // process.php
        $content = <<<EOF
<?php
return [];

EOF;
        file_put_contents("$base/process.php", $content);

        // redis.php
        $content = <<<EOF
<?php
return [
    'default' => [
        'host' => '127.0.0.1',
        'password' => null,
        'port' => 6379,
        'database' => 0,
    ],
];

EOF;
        file_put_contents("$base/redis.php", $content);

        // route.php
        $content = <<<EOF
<?php

use Webman\\Route;


EOF;
        file_put_contents("$base/route.php", $content);

        // static.php
        $content = <<<EOF
<?php

return [
    'enable' => true,
    'middleware' => [],    // Static file Middleware
];

EOF;
        file_put_contents("$base/static.php", $content);

        // translation.php
        $content = <<<EOF
<?php

return [
    // Default language
    'locale' => 'zh_CN',
    // Fallback language
    'fallback_locale' => ['zh_CN', 'en'],
    // Folder where language files are stored
    'path' => base_path() . "/plugin/$name/resource/translations",
];

EOF;
        file_put_contents("$base/translation.php", $content);

        // view.php
        $content = <<<EOF
<?php

use support\\view\\Raw;
use support\\view\\Twig;
use support\\view\\Blade;
use support\\view\\ThinkPHP;

return [
    'handler' => Raw::class
];

EOF;
        file_put_contents("$base/view.php", $content);

        // thinkorm.php
        $content = <<<EOF
<?php

return [];

EOF;
        file_put_contents("$base/thinkorm.php", $content);

    }

}
