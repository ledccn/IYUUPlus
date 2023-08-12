<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webman\Console\Util;

class AppPluginInstallCommand extends Command
{
    protected static $defaultName = 'app-plugin:install';
    protected static $defaultDescription = 'App Plugin Install';

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
        $output->writeln("Install App Plugin $name");
        $class = "\\plugin\\$name\\api\\Install";
        if (!method_exists($class, 'install')) {
            throw new \RuntimeException("Method $class::install not exists");
        }
        call_user_func([$class, 'install'], config("plugin.$name.app.version"));
        return self::SUCCESS;
    }

}
