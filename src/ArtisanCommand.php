<?php

namespace Laravel\Installer\Console;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ArtisanCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->ignoreValidationErrors();
        $this
            ->setName('artisan')
            ->setDescription('Execute current project artisan commands.');
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
        $artisanCommand = substr(
            $input->__toString(),
            strlen($input->getFirstArgument())
        );

        if (! $directory = $this->findArtisanDir()) {
            $output->writeln("<error>Not in project directory</error>");
            return;
        }

         $process = new Process('./artisan ' . $artisanCommand, $directory, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });
    }

    /**
     * Get the artisan directory for the environment.
     *
     * @return string|null
     */
    protected function findArtisanDir()
    {
        $path = getCwd();

        while ($path) {
            if (file_exists($path . '/artisan')) {
                return $path;
            }

            $path = dirname($path);

            if ($path === dirname($path)) {
                return;
            }
        };
    }
}
