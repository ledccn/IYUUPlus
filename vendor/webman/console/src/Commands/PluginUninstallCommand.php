<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Webman\Console\Util;


class PluginUninstallCommand extends Command
{
    protected static $defaultName = 'plugin:uninstall';
    protected static $defaultDescription = 'Execute plugin uninstall script';

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
        $output->writeln("Execute uninstall for plugin $name");
        if (!strpos($name, '/')) {
            $output->writeln('<error>Bad name, name must contain character \'/\' , for example foo/MyAdmin</error>');
            return self::FAILURE;
        }
        $namespace = Util::nameToNamespace($name);
        $uninstall_function = "\\{$namespace}\\Install::uninstall";
        $plugin_const = "\\{$namespace}\\Install::WEBMAN_PLUGIN";
        if (defined($plugin_const) && is_callable($uninstall_function)) {
            $uninstall_function();
        }
        return self::SUCCESS;
    }

}
