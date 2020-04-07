<?php

namespace Laravel\Installer\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DocsCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('docs')
            ->setDescription('Open the Laravel docs')
            ->addArgument('version', InputArgument::OPTIONAL);
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
        $uri = 'https://laravel.com/docs/';

        if ($version = $input->getArgument('version')) {
            switch ($version) {
                case '4':
                    $uri .= '4.2';
                    break;

                case '5':
                    $uri .= '5.8';
                    break;

                case '6':
                    $uri .= '6.x';
                    break;

                case '7':
                    $uri .= '7.x';
                    break;

                default:
                    $uri .= $version;
                    break;
            }
        }

        $output->writeln('<info>Opening Laravel Docs: '.$uri.'</info>');

        $process = Process::fromShellCommandline('open '.$uri);
        $process->run();

        return 0;
    }
}
