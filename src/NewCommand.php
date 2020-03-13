<?php

namespace Laravel\Installer\Console;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use ZipArchive;

class NewCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Laravel application')
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addArgument('setup', InputArgument::OPTIONAL, 'Use setup file')
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
            ->addOption('auth', null, InputOption::VALUE_NONE, 'Installs the Laravel authentication scaffolding')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! extension_loaded('zip')) {
            throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
        }

        $name = $input->getArgument('name');

        $directory = $name && $name !== '.' ? getcwd().'/'.$name : getcwd();

        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        $output->writeln('<info>Crafting application...</info>');

        $this->download($zipFile = $this->makeFilename(), $this->getVersion($input))
             ->extract($zipFile, $directory)
             ->prepareWritableDirectories($directory, $output)
             ->cleanUp($zipFile);

        $composer = $this->findComposer();

        $setupFile = $input->getArgument('setup');

        $commands = [
            $composer.' install --no-scripts',
            $composer.' run-script post-root-package-install',
            $composer.' run-script post-create-project-cmd',
            $composer.' run-script post-autoload-dump',
        ];

        if (isset($setupFile)) {
            $commands = $this->runSetupComposer($setupFile, $commands, $output);
        }

        if ($input->getOption('no-ansi')) {
            $commands = array_map(function ($value) {
                return $value.' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                return $value.' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $directory, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('Warning: '.$e->getMessage());
            }
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        if (isset($setupFile)) {
            $this->runSetupEnv($setupFile, $directory, $output);
        }

        if (isset($setupFile)) {
            $this->runSetupFiles($setupFile, $directory, $output);
        }

        if ($process->isSuccessful()) {
            $output->writeln('<comment>Application ready! Build something amazing.</comment>');
        }

        return 0;
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    protected function runSetupComposer(string $fileName, array $commands, OutputInterface $output):array
    {
        $setup = json_decode(file_get_contents($fileName), true);
        $composer = $this->findComposer();

        if (!array_key_exists('composer', $setup)) {
            return $commands;
        }

        if (array_key_exists('require', $setup['composer'])) {
            foreach ($setup['composer']['require'] as $package => $version) {
                $output->writeln("<info>Adding $package:$version as a composer dependency</info>");
                $commands[] = $composer . " require $package:$version";
            }
        }

        if (array_key_exists('require-dev', $setup['composer'])) {
            foreach ($setup['composer']['require-dev'] as $package => $version) {
                $output->writeln("<info>Adding $package:$version as a composer dev dependency</info>");
                $commands[] = $composer . " require --dev $package:$version";
            }
        }

        return $commands;
    }

    protected function runSetupEnv(string $setupFile, string $directory, OutputInterface $output)
    {
        $setup = json_decode(file_get_contents($setupFile), true);

        if (!array_key_exists('env', $setup)) {
            return;
        }

        $envFile = $directory . '/.env';
        $tempEnvFileName = $directory . '/.env-temp';
        $tempEnvFile = fopen($tempEnvFileName, "a");

        $lines = file($envFile);
        foreach ($lines as $line) {
            if (trim($line) == '') {
                fwrite($tempEnvFile, PHP_EOL);
                continue;
            }

            [$name, $value] = explode('=', $line);
            if (array_key_exists($name, $setup['env'])) {
                if ($setup['env'][$name] === null) {
                    $output->writeln("<warning>DELETED " . $name . " from $envFile</warning>");
                    continue;
                } else {
                    $output->writeln("<info>Replaced " . $name . " with " . $setup['env'][$name] . "</info>");
                    fwrite($tempEnvFile, "$name=" . $setup['env'][$name] . PHP_EOL);
                    continue;
                }
            }
            fwrite($tempEnvFile, "$name=$value");
        }

        rename($tempEnvFileName, $envFile);
    }

    protected function runSetupFiles(string $setupFile, string $directory, OutputInterface $output)
    {
        $setup = json_decode(file_get_contents($setupFile), true);

        if (!array_key_exists('files', $setup)) {
            return;
        }

        if (array_key_exists('create', $setup['files'])) {
            foreach ($setup['files']['create'] as $file) {
                $fullFileName = $directory . '/' . $file;
                touch($fullFileName);
                $output->writeln("<info>Created {$fullFileName}</info>");
            }
        }

        if (array_key_exists('copy', $setup['files'])) {
            foreach ($setup['files']['copy'] as $source => $dest) {
                $fullDest = $directory . '/' . $dest;
                $success = copy($source, $fullDest);
                if ($success){
                    $output->writeln("<info>Copied {$source} to {$fullDest}</info>");
                }
            }
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
     * @param  string  $version
     * @return $this
     */
    protected function download($zipFile, $version = 'master')
    {
        switch ($version) {
            case 'develop':
                $filename = 'latest-develop.zip';
                break;
            case 'auth':
                $filename = 'latest-auth.zip';
                break;
            case 'master':
                $filename = 'latest.zip';
                break;
        }

        $response = (new Client)->get('http://cabinet.laravel.com/'.$filename);

        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the Zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;

        $response = $archive->open($zipFile, ZipArchive::CHECKCONS);

        if ($response === ZipArchive::ER_NOZIP) {
            throw new RuntimeException('The zip file could not download. Verify that you are able to access: http://cabinet.laravel.com/latest.zip');
        }

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
     * Make sure the storage and bootstrap cache directories are writable.
     *
     * @param  string  $appDirectory
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return $this
     */
    protected function prepareWritableDirectories($appDirectory, OutputInterface $output)
    {
        $filesystem = new Filesystem;

        try {
            $filesystem->chmod($appDirectory.DIRECTORY_SEPARATOR.'bootstrap/cache', 0755, 0000, true);
            $filesystem->chmod($appDirectory.DIRECTORY_SEPARATOR.'storage', 0755, 0000, true);
        } catch (IOExceptionInterface $e) {
            $output->writeln('<comment>You should verify that the "storage" and "bootstrap/cache" directories are writable.</comment>');
        }

        return $this;
    }

    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return string
     */
    protected function getVersion(InputInterface $input)
    {
        if ($input->getOption('dev')) {
            return 'develop';
        }

        if ($input->getOption('auth')) {
            return 'auth';
        }

        return 'master';
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPath = getcwd().'/composer.phar';

        if (file_exists($composerPath)) {
            return '"'.PHP_BINARY.'" '.$composerPath;
        }

        return 'composer';
    }
}
