<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Webman\Console\Util;


class MakeCommandCommand extends Command
{
    protected static $defaultName = 'make:command';
    protected static $defaultDescription = 'Make command';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Command name');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $name = trim($input->getArgument('name'));
        $output->writeln("Make command $name");

        // make:command 不支持子目录
        $name = str_replace(['\\', '/'], '', $name);
        if (!$command_str = Util::guessPath(app_path(), 'command')) {
            $command_str = Util::guessPath(app_path(), 'controller') === 'Controller' ? 'Command' : 'command';
        }
        $items= explode(':', $name);
        $name='';
        foreach ($items as $item) {
            $name.=ucfirst($item);
        }
        $file = app_path() . "/$command_str/$name.php";
        $upper = $command_str === 'Command';
        $namespace = $upper ? 'App\Command' : 'app\command';
        $this->createCommand($name, $namespace, $file, $command);

        return self::SUCCESS;
    }

    protected function getClassName($name)
    {
        return preg_replace_callback('/:([a-zA-Z])/', function ($matches) {
            return strtoupper($matches[1]);
        }, ucfirst($name)) . 'Command';
    }

    /**
     * @param $name
     * @param $namespace
     * @param $path
     * @return void
     */
    protected function createCommand($name, $namespace, $file, $command)
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $desc = str_replace(':', ' ', $command);
        $command_content = <<<EOF
<?php

namespace $namespace;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;


class $name extends Command
{
    protected static \$defaultName = '$command';
    protected static \$defaultDescription = '$desc';

    /**
     * @return void
     */
    protected function configure()
    {
        \$this->addArgument('name', InputArgument::OPTIONAL, 'Name description');
    }

    /**
     * @param InputInterface \$input
     * @param OutputInterface \$output
     * @return int
     */
    protected function execute(InputInterface \$input, OutputInterface \$output)
    {
        \$name = \$input->getArgument('name');
        \$output->writeln('Hello $command');
        return self::SUCCESS;
    }

}

EOF;
        file_put_contents($file, $command_content);
    }

}
