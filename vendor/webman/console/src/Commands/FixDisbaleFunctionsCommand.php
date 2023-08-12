<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class FixDisbaleFunctionsCommand extends Command
{
    protected static $defaultName = 'fix-disable-functions';
    protected static $defaultDescription = 'Fix disbale_functions in php.ini';

    /**
     * @return void
     */
    protected function configure()
    {

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $php_ini_file = php_ini_loaded_file();
        if (!$php_ini_file) {
            $output->writeln('<error>Can not find php.ini</error>');
            return self::FAILURE;
        }
        $output->writeln("Location $php_ini_file");
        $disable_functions_str = ini_get("disable_functions");
        if (!$disable_functions_str) {
            $output->writeln('<success>Ok</success>');
        }

        $functions_required = [
            "stream_socket_server",
            "stream_socket_accept",
            "stream_socket_client",
            "pcntl_signal_dispatch",
            "pcntl_signal",
            "pcntl_alarm",
            "pcntl_fork",
            "posix_getuid",
            "posix_getpwuid",
            "posix_kill",
            "posix_setsid",
            "posix_getpid",
            "posix_getpwnam",
            "posix_getgrnam",
            "posix_getgid",
            "posix_setgid",
            "posix_initgroups",
            "posix_setuid",
            "posix_isatty",
            "proc_open",
            "proc_get_status",
            "proc_close",
            "shell_exec",
            "exec",
        ];

        $has_disbaled_functions = false;
        foreach ($functions_required as $func) {
            if (strpos($disable_functions_str, $func) !== false) {
                $has_disbaled_functions = true;
                break;
            }
        }

        $disable_functions = explode(",", $disable_functions_str);
        $disable_functions_removed = [];
        foreach ($disable_functions as $index => $func) {
            $func = trim($func);
            foreach ($functions_required as $func_prefix) {
                if (strpos($func, $func_prefix) === 0) {
                    $disable_functions_removed[$func] = $func;
                    unset($disable_functions[$index]);
                }
            }
        }

        $php_ini_content = file_get_contents($php_ini_file);
        if (!$php_ini_content) {
            $output->writeln("<error>$php_ini_file content empty</error>");
            return self::FAILURE;
        }

        $new_disable_functions_str = implode(",", $disable_functions);
        $php_ini_content = preg_replace("/\ndisable_functions *?=[^\n]+/", "\ndisable_functions = $new_disable_functions_str", $php_ini_content);

        file_put_contents($php_ini_file, $php_ini_content);

        foreach ($disable_functions_removed as $func) {
            $output->write(str_pad($func, 30));
            $output->writeln('<info>enabled</info>');
        }

        $output->writeln('<info>Succes</info>');
        return self::SUCCESS;
    }

}
