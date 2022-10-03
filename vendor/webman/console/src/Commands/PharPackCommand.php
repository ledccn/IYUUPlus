<?php

namespace Webman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phar;
use RuntimeException;

class PharPackCommand extends Command
{
    protected static $defaultName = 'phar:pack';
    protected static $defaultDescription = 'Can be easily packaged a project into phar files. Easy to distribute and use.';

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checkEnv();

        $phar_file_output_dir = config('plugin.webman.console.app.phar_file_output_dir');
        if (empty($phar_file_output_dir)) {
            throw new RuntimeException('Please set the phar file output directory.');
        }
        if (!file_exists($phar_file_output_dir) && !is_dir($phar_file_output_dir)) {
            if (!mkdir($phar_file_output_dir,0777,true)) {
                throw new RuntimeException("Failed to create phar file output directory. Please check the permission.");
            }
        }

        $phar_filename = config('plugin.webman.console.app.phar_filename');
        if (empty($phar_filename)) {
            throw new RuntimeException('Please set the phar filename.');
        }

        $phar_file = rtrim($phar_file_output_dir,DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $phar_filename;
        if (file_exists($phar_file)) {
            unlink($phar_file);
        }

        $exclude_pattern = config('plugin.webman.console.app.exclude_pattern');
        $phar = new Phar($phar_file,0,'webman');

        $phar->startBuffering();

        $signature_algorithm = config('plugin.webman.console.app.signature_algorithm');
        if (!in_array($signature_algorithm,[Phar::MD5, Phar::SHA1, Phar::SHA256, Phar::SHA512,Phar::OPENSSL])) {
            throw new RuntimeException('The signature algorithm must be one of Phar::MD5, Phar::SHA1, Phar::SHA256, Phar::SHA512, or Phar::OPENSSL.');
        }
        if ($signature_algorithm === Phar::OPENSSL) {
            $private_key_file = config('plugin.webman.console.app.private_key_file');
            if (!file_exists($private_key_file)) {
                throw new RuntimeException("If the value of the signature algorithm is 'Phar::OPENSSL', you must set the private key file.");
            }
            $private = openssl_get_privatekey(file_get_contents($private_key_file));
            $pkey = '';
            openssl_pkey_export($private, $pkey);
            $phar->setSignatureAlgorithm($signature_algorithm, $pkey);
        } else {
            $phar->setSignatureAlgorithm($signature_algorithm);
        }

        $phar->buildFromDirectory(BASE_PATH,$exclude_pattern);

        $exclude_files = config('plugin.webman.console.app.exclude_files');

        foreach ($exclude_files as $file) {
            if($phar->offsetExists($file)){
                $phar->delete($file);
            }
        }

        $output->writeln('Files collect complete, begin add file to Phar.');

        $phar->setStub("#!/usr/bin/env php
<?php
define('IN_PHAR', true);
Phar::mapPhar('webman');
require 'phar://webman/webman';
__HALT_COMPILER();
");

        $output->writeln('Write requests to the Phar archive, save changes to disk.');

        $phar->stopBuffering();
        unset($phar);
        return self::SUCCESS;
    }

    /**
     * @throws RuntimeException
     */
    private function checkEnv(): void
    {
        if (!class_exists(Phar::class, false)) {
            throw new RuntimeException("The 'phar' extension is required for build phar package");
        }

        if (ini_get('phar.readonly')) {
            throw new RuntimeException(
                "The 'phar.readonly' is 'On', build phar must setting it 'Off' or exec with 'php -d phar.readonly=0 ./webman phar:pack'"
            );
        }
    }
}
