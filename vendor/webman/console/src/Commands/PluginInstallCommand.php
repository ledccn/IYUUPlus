<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Webman\Console\Util;


class PluginInstallCommand extends Command
{
    protected static $defaultName = 'plugin:install';
    protected static $defaultDescription = 'Execute plugin installation script';

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
        $output->writeln("Execute installation for plugin $name");
        $namespace = Util::nameToNamespace($name);
        $install_function = "\\{$namespace}\\Install::install";
        $plugin_const = "\\{$namespace}\\Install::WEBMAN_PLUGIN";
        if (defined($plugin_const) && is_callable($install_function)) {
            $install_function();
        }
        return self::SUCCESS;
    }

}
