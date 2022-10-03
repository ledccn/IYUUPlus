<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Webman\Console\Util;


class MakeBootstrapCommand extends Command
{
    protected static $defaultName = 'make:bootstrap';
    protected static $defaultDescription = 'Make bootstrap';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Bootstrap name');
        $this->addArgument('enable', InputArgument::OPTIONAL, 'Enable or not');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $enable = in_array($input->getArgument('enable'), ['no', '0', 'false', 'n']) ? false : true;
        $output->writeln("Make bootstrap $name");

        $name = str_replace('\\', '/', $name);
        if (!$bootstrap_str = Util::guessPath(app_path(), 'bootstrap')) {
            $bootstrap_str = Util::guessPath(app_path(), 'controller') === 'Controller' ? 'Bootstrap' : 'bootstrap';
        }
        $upper = $bootstrap_str === 'Bootstrap';
        if (!($pos = strrpos($name, '/'))) {
            $name = ucfirst($name);
            $file = app_path() . "/$bootstrap_str/$name.php";
            $namespace = $upper ? 'App\Bootstrap' : 'app\bootstrap';
        } else {
            if($real_name = Util::guessPath(app_path(), $name)) {
                $name = $real_name;
            }
            if ($upper && !$real_name) {
                $name = preg_replace_callback('/\/([a-z])/', function ($matches) {
                    return '/' . strtoupper($matches[1]);
                }, ucfirst($name));
            }
            $path = "$bootstrap_str/" . substr($upper ? ucfirst($name) : $name, 0, $pos);
            $name = ucfirst(substr($name, $pos + 1));
            $file = app_path() . "/$path/$name.php";
            $namespace = str_replace('/', '\\', ($upper ? 'App/' : 'app/') . $path);
        }

        $this->createBootstrap($name, $namespace, $file);
        if ($enable) {
            $this->addConfig("$namespace\\$name", config_path() . '/bootstrap.php');
        }

        return self::SUCCESS;
    }

    /**
     * @param $name
     * @param $namespace
     * @param $file
     * @return void
     */
    protected function createBootstrap($name, $namespace, $file)
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $bootstrap_content = <<<EOF
<?php

namespace $namespace;

use Webman\Bootstrap;

class $name implements Bootstrap
{
    public static function start(\$worker)
    {
        // Is it console environment ?
        \$is_console = !\$worker;
        if (\$is_console) {
            // If you do not want to execute this in console, just return.
            return;
        }


    }

}

EOF;
        file_put_contents($file, $bootstrap_content);
    }

    public function addConfig($class, $config_file)
    {
        $config = include $config_file;
        if(!in_array($class, $config ?? [])) {
            $config_file_content = file_get_contents($config_file);
            $config_file_content = preg_replace('/\];/', "    $class::class,\n];", $config_file_content);
            file_put_contents($config_file, $config_file_content);
        }
    }
}
