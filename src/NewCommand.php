<?php

namespace Laravel\Installer\Console;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

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
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git repository')
            ->addOption('github', null, InputOption::VALUE_OPTIONAL, 'Create a new repository on GitHub', false)
            ->addOption('jet', null, InputOption::VALUE_NONE, 'Installs the Laravel Jetstream scaffolding')
            ->addOption('stack', null, InputOption::VALUE_OPTIONAL, 'The Jetstream stack that should be installed')
            ->addOption('teams', null, InputOption::VALUE_NONE, 'Indicates whether Jetstream should be scaffolded with team support')
            ->addOption('prompt-jetstream', null, InputOption::VALUE_NONE, 'Issues a prompt to determine if Jetstream should be installed')
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
        $installJetstream = $input->getOption('jet') ||
                            ($input->getOption('prompt-jetstream') && (new SymfonyStyle($input, $output))->confirm('Would you like to install the Laravel Jetstream application scaffolding?', false));

        if ($installJetstream) {
            $output->write(PHP_EOL."<fg=magenta>
    |     |         |
    |,---.|--- ,---.|--- ,---.,---.,---.,-.-.
    ||---'|    `---.|    |    |---',---|| | |
`---'`---'`---'`---'`---'`    `---'`---^` ' '</>".PHP_EOL.PHP_EOL);

            $stack = $this->jetstreamStack($input, $output);

            $teams = $input->getOption('teams') === true
                    ? (bool) $input->getOption('teams')
                    : (new SymfonyStyle($input, $output))->confirm('Will your application use teams?', false);
        } else {
            $output->write(PHP_EOL.'<fg=red> _                               _
| |                             | |
| |     __ _ _ __ __ ___   _____| |
| |    / _` | \'__/ _` \ \ / / _ \ |
| |___| (_| | | | (_| |\ V /  __/ |
|______\__,_|_|  \__,_| \_/ \___|_|</>'.PHP_EOL.PHP_EOL);
        }

        sleep(1);

        $name = $input->getArgument('name');

        $directory = $name !== '.' ? getcwd().'/'.$name : '.';

        $version = $this->getVersion($input);

        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        if ($input->getOption('force') && $directory === '.') {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        $composer = $this->findComposer();

        $commands = [
            $composer." create-project laravel/laravel \"$directory\" $version --remove-vcs --prefer-dist",
        ];

        if ($directory != '.' && $input->getOption('force')) {
            if (PHP_OS_FAMILY == 'Windows') {
                array_unshift($commands, "rd /s /q \"$directory\"");
            } else {
                array_unshift($commands, "rm -rf \"$directory\"");
            }
        }

        if (PHP_OS_FAMILY != 'Windows') {
            $commands[] = "chmod 755 \"$directory/artisan\"";
        }

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            if ($name !== '.') {
                $this->replaceInFile(
                    'APP_URL=http://localhost',
                    'APP_URL=http://'.$name.'.test',
                    $directory.'/.env'
                );

                $this->replaceInFile(
                    'DB_DATABASE=laravel',
                    'DB_DATABASE='.str_replace('-', '_', strtolower($name)),
                    $directory.'/.env'
                );

                $this->replaceInFile(
                    'DB_DATABASE=laravel',
                    'DB_DATABASE='.str_replace('-', '_', strtolower($name)),
                    $directory.'/.env.example'
                );
            }

            if ($input->getOption('git') || $input->getOption('github') !== false) {
                $this->createRepository($directory, $input, $output);
            }

            if ($installJetstream) {
                $this->installJetstream($directory, $stack, $teams, $input, $output);
            }

            if ($input->getOption('github') !== false) {
                $this->pushToGitHub($name, $directory, $input, $output);
            }

            $output->writeln(PHP_EOL.'<comment>Application ready! Build something amazing.</comment>');
        }

        return $process->getExitCode();
    }

    /**
     * Install Laravel Jetstream into the application.
     *
     * @param  string  $directory
     * @param  string  $stack
     * @param  bool  $teams
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function installJetstream(string $directory, string $stack, bool $teams, InputInterface $input, OutputInterface $output)
    {
        chdir($directory);

        $commands = array_filter([
            $this->findComposer().' require laravel/jetstream',
            trim(sprintf(PHP_BINARY.' artisan jetstream:install %s %s', $stack, $teams ? '--teams' : '')),
            $stack === 'inertia' ? 'npm install && npm run dev' : null,
            PHP_BINARY.' artisan storage:link',
        ]);

        $this->runCommands($commands, $input, $output);

        $this->commitChanges('Install Jetstream', $directory, $input, $output);
    }

    /**
     * Determine the stack for Jetstream.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return string
     */
    protected function jetstreamStack(InputInterface $input, OutputInterface $output)
    {
        $stacks = [
            'livewire',
            'inertia',
        ];

        if ($input->getOption('stack') && in_array($input->getOption('stack'), $stacks)) {
            return $input->getOption('stack');
        }

        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion('Which Jetstream stack do you prefer?', $stacks);

        $output->write(PHP_EOL);

        return $helper->ask($input, new SymfonyStyle($input, $output), $question);
    }

    /**
     * Create a Git repository and commit the base Laravel skeleton.
     *
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function createRepository(string $directory, InputInterface $input, OutputInterface $output)
    {
        chdir($directory);

        $commands = [
            'git init -q -b main .',
            'git add .',
            'git commit -q -m "Set up a fresh Laravel app"',
        ];

        $this->runCommands($commands, $input, $output);
    }

    /**
     * Commit any changes in the current working directory.
     *
     * @param  string  $message
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function commitChanges(string $message, string $directory, InputInterface $input, OutputInterface $output)
    {
        if (! $input->getOption('git') && $input->getOption('github') === false) {
            return;
        }

        chdir($directory);

        $commands = [
            'git add .',
            "git commit -q -m \"$message\"",
        ];

        $this->runCommands($commands, $input, $output);
    }

    /**
     * Create a GitHub repository and push the git log to it.
     *
     * @param  string  $name
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function pushToGitHub(string $name, string $directory, InputInterface $input, OutputInterface $output)
    {
        $process = new Process(['gh', 'auth', 'status']);
        $process->run();

        if (! $process->isSuccessful()) {
            $output->writeln('Warning: make sure the "gh" CLI tool is installed and that you\'re authenticated to GitHub. Skipping...');

            return;
        }

        chdir($directory);

        $flags = $input->getOption('github') ?: '--private';

        $commands = [
            "gh repo create $name -y $flags",
            'git push -q -u origin main',
        ];

        $this->runCommands($commands, $input, $output);
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

    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return string
     */
    protected function getVersion(InputInterface $input)
    {
        if ($input->getOption('dev')) {
            return 'dev-master';
        }

        return '';
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

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return \Symfony\Component\Process\Process
     */
    protected function runCommands($commands, InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('no-ansi')) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                return $value.' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                return $value.' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('Warning: '.$e->getMessage());
            }
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write('    '.$line);
        });

        return $process;
    }

    /**
     * Replace the given string in the given file.
     *
     * @param  string  $search
     * @param  string  $replace
     * @param  string  $file
     * @return void
     */
    protected function replaceInFile(string $search, string $replace, string $file)
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }
}
