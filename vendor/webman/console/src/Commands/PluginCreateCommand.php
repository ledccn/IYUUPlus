<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webman\Console\Util;

class PluginCreateCommand extends Command
{
    protected static $defaultName = 'plugin:create';
    protected static $defaultDescription = 'Plugin create';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addOption('name', 'name', InputOption::VALUE_REQUIRED, 'Plugin name, for example foo/my-admin');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = strtolower($input->getOption('name'));
        $output->writeln("Create Plugin $name");
        if (!strpos($name, '/')) {
            $output->writeln('<error>Bad name, name must contain character \'/\' , for example foo/MyAdmin</error>');
            return self::FAILURE;
        }

        $namespace = Util::nameToNamespace($name);

        // Create dir config/plugin/$name
        if (is_dir($plugin_config_path = config_path()."/plugin/$name")) {
            $output->writeln("<error>Dir $plugin_config_path already exists</error>");
            return self::FAILURE;
        }

        if (is_dir($plugin_path = base_path()."/vendor/$name")) {
            $output->writeln("<error>Dir $plugin_path already exists</error>");
            return self::FAILURE;
        }

        // Add psr-4
        if ($err = $this->addAutoloadToComposerJson($name, $namespace)) {
            $output->writeln("<error>$err</error>");
            return self::FAILURE;
        }

        $this->createConfigFiles($plugin_config_path);

        $this->createVendorFiles($name, $namespace, $plugin_path, $output);

        return self::SUCCESS;
    }

    protected function addAutoloadToComposerJson($name, $namespace)
    {
        if (!is_file($composer_json_file = base_path()."/composer.json")) {
            return "$composer_json_file not exists";
        }
        $composer_json = json_decode($composer_json_str = file_get_contents($composer_json_file), true);
        if (!$composer_json) {
            return "Bad $composer_json_file";
        }
        if(isset($composer_json['autoload']['psr-4'][$namespace."\\"])) {
            return;
        }
        $namespace = str_replace("\\", "\\\\", $namespace);
        $composer_json_str = str_replace('"psr-4": {', '"psr-4": {'."\n      \"$namespace\\\\\" : \"vendor/$name/src\",", $composer_json_str);
        file_put_contents($composer_json_file, $composer_json_str);
    }

    protected function createConfigFiles($plugin_config_path)
    {
        mkdir($plugin_config_path, 0777, true);
        $app_str = <<<EOF
<?php
return [
    'enable' => true,
];
EOF;
        file_put_contents("$plugin_config_path/app.php", $app_str);
    }

    protected function createVendorFiles($name, $namespace, $plugin_path, $output)
    {
        mkdir("$plugin_path/src", 0777, true);
        $this->createComposerJson($name, $namespace, $plugin_path);
        if (is_callable('exec')) {
            exec("composer dumpautoload");
        } else {
            $output->writeln("<info>Please run command 'composer dumpautoload'</info>");
        }
    }

    /**
     * @param $name
     * @param $namespace
     * @param $dest
     * @return void
     */
    protected function createComposerJson($name, $namespace, $dest)
    {
        $namespace = str_replace('\\', '\\\\', $namespace);
        $composer_json_content = <<<EOT
{
  "name": "$name",
  "type": "library",
  "license": "MIT",
  "description": "Webman plugin $name",
  "require": {
  },
  "autoload": {
    "psr-4": {
      "$namespace\\\\": "src"
    }
  }
}
EOT;
        file_put_contents("$dest/composer.json", $composer_json_content);
    }

    /**
     * @param $namespace
     * @param $path_relations
     * @param $dest_dir
     * @return void
     */
    protected function writeInstallFile($namespace, $path_relations, $dest_dir)
    {
        if (!is_dir($dest_dir)) {
           mkdir($dest_dir, 0777, true);
        }
        $relations = [];
        foreach($path_relations as $relation) {
            $relations[$relation] = $relation;
        }
        $relations = var_export($relations, true);
        $install_php_content = <<<EOT
<?php
namespace $namespace;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static \$pathRelation = $relations;

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        static::installByRelation();
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {
        self::uninstallByRelation();
    }

    /**
     * installByRelation
     * @return void
     */
    public static function installByRelation()
    {
        foreach (static::\$pathRelation as \$source => \$dest) {
            if (\$pos = strrpos(\$dest, '/')) {
                \$parent_dir = base_path().'/'.substr(\$dest, 0, \$pos);
                if (!is_dir(\$parent_dir)) {
                    mkdir(\$parent_dir, 0777, true);
                }
            }
            //symlink(__DIR__ . "/\$source", base_path()."/\$dest");
            copy_dir(__DIR__ . "/\$source", base_path()."/\$dest");
        }
    }

    /**
     * uninstallByRelation
     * @return void
     */
    public static function uninstallByRelation()
    {
        foreach (static::\$pathRelation as \$source => \$dest) {
            /*if (is_link(base_path()."/\$dest")) {
                unlink(base_path()."/\$dest");
            }*/
            remove_dir(base_path()."/\$dest");
        }
    }
}
EOT;
        file_put_contents("$dest_dir/Install.php", $install_php_content);
    }
}
