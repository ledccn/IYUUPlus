<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;


class PluginEnableCommand extends Command
{
    protected static $defaultName = 'plugin:enable';
    protected static $defaultDescription = 'Enable plugin by name';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Plugin name, for example foo/my-admin');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $output->writeln("Enable plugin $name");
        if (!strpos($name, '/')) {
            $output->writeln('<error>Bad name, name must contain character \'/\' , for example foo/MyAdmin</error>');
            return self::FAILURE;
        }
        $config_file = config_path() . "/plugin/$name/app.php";
        if (!is_file($config_file)) {
            $output->writeln("<error>$config_file not found</error>");
            return self::FAILURE;
        }
        $config = include $config_file;
        if (!isset($config['enable'])) {
            $output->writeln("<error>Config key 'enable' not found</error>");
            return self::FAILURE;
        }
        if ($config['enable']) {
            return self::SUCCESS;
        }
        $config_content = file_get_contents($config_file);
        $config_content = preg_replace('/(\'enable\' *?=> *?)(false)/', '$1true', $config_content);
        file_put_contents($config_file, $config_content);
        return self::SUCCESS;
    }

}
