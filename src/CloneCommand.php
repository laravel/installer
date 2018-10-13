<?php

namespace Laravel\Installer\Console;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class CloneCommand extends Command
{
    protected $repo;

    protected $dir;

    protected $branch;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('clone')
            ->setDescription('Clone any laravel repository, Install composer and generate key')
            ->addArgument('repository', InputArgument::REQUIRED, 'The repository url you want to clone')
            ->addOption('branch', null, InputOption::VALUE_OPTIONAL, 'Specify branch you want to clone, default is master')
            ->addOption('dir', null, InputOption::VALUE_OPTIONAL, 'Specify directory name to which you want to clone the repository');
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setAttributes($input);

        $this->cloneRepo($output);

        $this->installComposer($output);

        if (strtolower($this->readType()) == 'project') {
            $this->keyGenerate($output);
        }
    }

    /**
     * Set Arguments and Options.
     *
     * @param $input
     */
    public function setAttributes($input)
    {
        $this->repo = $input->getArgument('repository');
        $this->branch = $input->getOption('branch');
        $dir = $input->getOption('dir') ? $input->getOption('dir') : $this->getRepoName($this->repo);
        $this->dir = getcwd() . '/' . $dir;
    }

    /**
     * Cloning repository using git command.
     *
     * @param $output
     */
    public function cloneRepo($output)
    {
        $command = "git clone {$this->repo}";

        if ($this->branch) {
            $output->writeln("<info>Using branch {$this->branch}</info>");
            $command = "git clone -b {$this->branch} {$this->repo}";
        }
        $command = "{$command} {$this->dir}";

        $process = new Process($command);

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });
    }

    /**
     * Installing composer into repository.
     *
     * @param $output
     */
    public function installComposer($output)
    {
        $composer = $this->findComposer();
        $command = $composer . ' install';

        $process = new Process($command, $this->dir);

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });
    }

    /**
     * If repository is a project then generate key using php artisan key:generate.
     *
     * @param $output
     */
    public function keyGenerate($output)
    {
        chdir("{$this->dir}");
        exec('cp .env.example .env');
        $output->write(shell_exec('php artisan key:generate'));
    }

    /**
     * Extracting the repository name from url.
     *
     * @return mixed
     */
    public function getRepoName()
    {
        preg_match('/\/(.+)(?=.git)/', $this->repo, $match);
        return $match[1];
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd() . '/composer.phar')) {
            return '"' . PHP_BINARY . '" composer.phar';
        }

        return 'composer';
    }

    /**
     * Read the type of repository from composer.json.
     *
     * @return mixed
     */
    public function readType()
    {
        $content = $this->getComposerFile();
        if(isset($content->type)){
            return $content->type;
        }
        return ;
    }

    /**
     * Getting the contents of composer.json file.
     *
     * @return mixed
     */
    protected function getComposerFile()
    {
        $path = $this->dir . '/composer.json';
        return json_decode(file_get_contents($path));
    }
}
