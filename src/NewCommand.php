<?php

namespace Laravel\Installer\Console;

use finfo;
use ZipArchive;
use RuntimeException;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NewCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('new')
             ->setDescription('Create a new Laravel application.')
             ->addArgument('name', InputArgument::REQUIRED)
             ->addOption('with-homestead', null, InputOption::VALUE_NONE, 'Create a new Laravel application with Laravel Homestead included.');
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->verifyApplicationDoesntExist(
            $directory = getcwd().'/'.$input->getArgument('name'),
            $output
        );

        $output->writeln('<info>Crafting application...</info>');

        $this->download($zipFile = $this->makeFilename())
             ->extract($zipFile, $directory)
             ->cleanUp($zipFile);

        $composer = $this->findComposer();

        $commands = [
            $composer.' run-script post-root-package-install',
            $composer.' run-script post-install-cmd',
            $composer.' run-script post-create-project-cmd',
        ];

        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        if ($input->getOption('with-homestead')) {
            $this->includeHomestead($directory, $output);
        }

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd().'/laravel_'.md5(time().uniqid()).'.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function download($zipFile)
    {
        $response = (new Client)->get('http://cabinet.laravel.com/latest.zip');

        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo($directory);

        $archive->close();

        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);

        @unlink($zipFile);

        return $this;
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" composer.phar';
        }

        return 'composer';
    }

    /**
     * Include Laravel Homestead in the new application.
     *
     * @param  string  $directory
     * @param  OutputInterface  $output
     * @return void
     */
    protected function includeHomestead($directory, OutputInterface $output)
    {
        $output->writeln('<info>Installing Laravel Homestead...</info>');

        $composer = $this->findComposer();

        $process = new Process($composer.' require laravel/homestead', $directory, null, null, null);

        $process->run();

        $initScriptPath = 'vendor/bin/homestead';

        // Composer can create binary proxy files in the bin directory of the vendor
        // directory. On Windows machines composer creates .bat proxy files.
        if ($this->isPHPScript($directory.'/'.$initScriptPath)) {
            $makeCommand = '"'.PHP_BINARY.'" '.$initScriptPath;
        } elseif ($this->isWindows()) {
            $makeCommand = 'call '.$initScriptPath.'.bat make';
        } else {
            $makeCommand = $initScriptPath.' make';
        }

        $process = new Process($makeCommand, $directory, null, null, null);

        try {
            $process->run();
        } catch (RuntimeException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');
            $output->writeln('<error>Skipping further installation of Laravel Homestead.</error>');
            return;
        }

        $output->writeln('<comment>Laravel Homestead installed successfully.</comment>');
    }

    /**
     * Check if a script is a PHP script.
     *
     * @param  string  $file
     * @return bool
     * @throws RuntimeException
     */
    protected function isPHPScript($file)
    {
        // As of PHP 5.3 the fileinfo extension is enabled by default on unix machines.
        // On Windows the fileinfo extension is disabled by default in php.ini.
        if (!extension_loaded('fileinfo')) {
            throw new RuntimeException('Fileinfo extension must be enabled to install Laravel Homestead.');
        }

        $file_info = new finfo(FILEINFO_MIME);

        $file_info->finfo();

        $mime_type = $file_info->buffer(file_get_contents($file));

        return Str::contains($mime_type, 'php');
    }

    /**
     * Check if the current OS is Windows.
     *
     * @return bool
     */
    protected function isWindows()
    {
        return strpos(strtoupper(PHP_OS), 'WIN') === 0;
    }
}
