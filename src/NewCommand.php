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
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch that should be created for a new repository', $this->defaultBranch())
            ->addOption('github', null, InputOption::VALUE_OPTIONAL, 'Create a new repository on GitHub', false)
            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'The GitHub organization to create the new repository for')
            ->addOption('breeze', null, InputOption::VALUE_NONE, 'Installs the Laravel Breeze scaffolding')
            ->addOption('dark', null, InputOption::VALUE_NONE, 'Indicate whether Breeze or Jetstream should be scaffolded with dark mode support')
            ->addOption('ssr', null, InputOption::VALUE_NONE, 'Indicate whether Breeze should be scaffolded with Inertia SSR support')
            ->addOption('jet', null, InputOption::VALUE_NONE, 'Installs the Laravel Jetstream scaffolding')
            ->addOption('stack', null, InputOption::VALUE_OPTIONAL, 'The Breeze / Jetstream stack that should be installed')
            ->addOption('teams', null, InputOption::VALUE_NONE, 'Indicates whether Jetstream should be scaffolded with team support')
            ->addOption('pest', null, InputOption::VALUE_NONE, 'Installs the Pest testing framework')
            ->addOption('phpunit', null, InputOption::VALUE_NONE, 'Installs the PHPUnit testing framework')
            ->addOption('prompt-breeze', null, InputOption::VALUE_NONE, 'Issues a prompt to determine if Breeze should be installed')
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
        $installBreeze = $input->getOption('breeze') ||
                            ($input->getOption('prompt-breeze') && (new SymfonyStyle($input, $output))->confirm('Would you like to install the Laravel Breeze application scaffolding?', false));

        $installJetstream = $input->getOption('jet') ||
                            ($input->getOption('prompt-jetstream') && (new SymfonyStyle($input, $output))->confirm('Would you like to install the Laravel Jetstream application scaffolding?', false));

        if ($installBreeze) {
            $output->write("<fg=blue>  ____
 | __ ) _ __ ___  ___ _______
 |  _ \| '__/ _ \/ _ \_  / _ \
 | |_) | | |  __/  __// /  __/
 |____/|_|  \___|\___/___\___|</>".PHP_EOL.PHP_EOL);

            $stack = $this->breezeStack($input, $output);
            $testingFramework = $this->testingFramework($input, $output);

            $dark = false;

            if (in_array($stack, ['blade', 'vue', 'react'])) {
                $dark = $input->getOption('dark') === true
                    ? (bool) $input->getOption('dark')
                    : (new SymfonyStyle($input, $output))->confirm('Would you like to install dark mode support?', false);
            }

            $ssr = false;

            if (in_array($stack, ['vue', 'react'])) {
                $ssr = $input->getOption('ssr') === true
                    ? (bool) $input->getOption('ssr')
                    : (new SymfonyStyle($input, $output))->confirm('Would you like to install Inertia SSR support?', false);
            }
        } elseif ($installJetstream) {
            $output->write(PHP_EOL."  <fg=magenta>
      |     |         |
      |,---.|--- ,---.|--- ,---.,---.,---.,-.-.
      ||---'|    `---.|    |    |---',---|| | |
  `---'`---'`---'`---'`---'`    `---'`---^` ' '</>".PHP_EOL.PHP_EOL);

            $stack = $this->jetstreamStack($input, $output);
            $testingFramework = $this->testingFramework($input, $output);

            $teams = $input->getOption('teams') === true
                    ? (bool) $input->getOption('teams')
                    : (new SymfonyStyle($input, $output))->confirm('Will your application use teams?', false);

            $dark = $input->getOption('dark') === true
                ? (bool) $input->getOption('dark')
                : (new SymfonyStyle($input, $output))->confirm('Would you like to install dark mode support?', false);
        } else {
            $output->write(PHP_EOL.'  <fg=red> _                               _
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
                array_unshift($commands, "(if exist \"$directory\" rd /s /q \"$directory\")");
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
                    'APP_URL='.$this->generateAppUrl($name),
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

            if ($installBreeze) {
                $this->installBreeze($directory, $stack, $testingFramework, $dark, $ssr, $input, $output);
            } elseif ($installJetstream) {
                $this->installJetstream($directory, $stack, $testingFramework, $teams, $dark, $input, $output);
            } elseif ($input->getOption('pest')) {
                $this->installPest($directory, $input, $output);
            }

            if ($input->getOption('github') !== false) {
                $this->pushToGitHub($name, $directory, $input, $output);
                $output->writeln('');
            }

            $output->writeln('  <bg=blue;fg=white> INFO </> Application ready! <options=bold>Build something amazing.</>'.PHP_EOL);
        }

        return $process->getExitCode();
    }

    /**
     * Return the local machine's default Git branch if set or default to `main`.
     *
     * @return string
     */
    protected function defaultBranch()
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'main';
    }

    /**
     * Install Laravel Breeze into the application.
     *
     * @param  string  $directory
     * @param  string  $stack
     * @param  string  $testingFramework
     * @param  bool  $dark
     * @param  bool  $ssr
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function installBreeze(string $directory, string $stack, string $testingFramework, bool $dark, bool $ssr, InputInterface $input, OutputInterface $output)
    {
        chdir($directory);

        $commands = array_filter([
            $this->findComposer().' require laravel/breeze',
            trim(sprintf(
                PHP_BINARY.' artisan breeze:install %s %s %s %s',
                $stack,
                $testingFramework == 'pest' ? '--pest' : '',
                $dark ? '--dark' : '',
                $ssr ? '--ssr' : '',
            )),
        ]);

        $this->runCommands($commands, $input, $output);

        $this->commitChanges('Install Breeze', $directory, $input, $output);
    }

    /**
     * Install Laravel Jetstream into the application.
     *
     * @param  string  $directory
     * @param  string  $stack
     * @param  string  $testingFramework
     * @param  bool  $teams
     * @param  bool  $dark
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function installJetstream(string $directory, string $stack, string $testingFramework, bool $teams, bool $dark, InputInterface $input, OutputInterface $output)
    {
        chdir($directory);

        $commands = array_filter([
            $this->findComposer().' require laravel/jetstream',
            trim(sprintf(
                PHP_BINARY.' artisan jetstream:install %s %s %s %s',
                $stack,
                $teams ? '--teams' : '',
                $dark ? '--dark' : '',
                $testingFramework == 'pest' ? '--pest' : '',
            )),
        ]);

        $this->runCommands($commands, $input, $output);

        $this->commitChanges('Install Jetstream', $directory, $input, $output);
    }

    /**
     * Determine the stack for Breeze.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return string
     */
    protected function breezeStack(InputInterface $input, OutputInterface $output)
    {
        $stacks = [
            'blade',
            'react',
            'vue',
            'api',
        ];

        if ($input->getOption('stack') && in_array($input->getOption('stack'), $stacks)) {
            return $input->getOption('stack');
        }

        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion('Which Breeze stack do you prefer?', $stacks);

        $output->write(PHP_EOL);

        return $helper->ask($input, new SymfonyStyle($input, $output), $question);
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
     * Determine the testing framework for Jetstream.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return string
     */
    protected function testingFramework(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('pest')) {
            return 'pest';
        }

        if ($input->getOption('phpunit')) {
            return 'phpunit';
        }

        $testingFrameworks = [
            'pest',
            'phpunit',
        ];

        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion('Which testing framework do you prefer?', $testingFrameworks);

        $output->write(PHP_EOL);

        return $helper->ask($input, new SymfonyStyle($input, $output), $question);
    }

    /**
     * Install Pest into the application.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function installPest(string $directory, InputInterface $input, OutputInterface $output)
    {
        chdir($directory);

        $commands = array_filter([
            $this->findComposer().' remove phpunit/phpunit --dev',
            $this->findComposer().' require pestphp/pest:^2.0 pestphp/pest-plugin-laravel:^2.0 --dev',
            PHP_BINARY.' ./vendor/bin/pest --init',
        ]);

        $this->runCommands($commands, $input, $output, [
            'PEST_NO_SUPPORT' => 'true',
        ]);

        $this->replaceFile(
            'pest/Feature.php',
            $directory.'/tests/Feature/ExampleTest.php',
        );

        $this->replaceFile(
            'pest/Unit.php',
            $directory.'/tests/Unit/ExampleTest.php',
        );

        $this->commitChanges('Install Pest', $directory, $input, $output);
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

        $branch = $input->getOption('branch') ?: $this->defaultBranch();

        $commands = [
            'git init -q',
            'git add .',
            'git commit -q -m "Set up a fresh Laravel app"',
            "git branch -M {$branch}",
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
            $output->writeln('  <bg=yellow;fg=black> WARN </> Make sure the "gh" CLI tool is installed and that you\'re authenticated to GitHub. Skipping...'.PHP_EOL);

            return;
        }

        chdir($directory);

        $name = $input->getOption('organization') ? $input->getOption('organization')."/$name" : $name;
        $flags = $input->getOption('github') ?: '--private';

        $commands = [
            "gh repo create {$name} --source=. --push {$flags}",
        ];

        $this->runCommands($commands, $input, $output, ['GIT_TERMINAL_PROMPT' => 0]);
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
     * Generate a valid APP_URL for the given application name.
     *
     * @param  string  $name
     * @return string
     */
    protected function generateAppUrl($name)
    {
        $hostname = mb_strtolower($name).'.test';

        return $this->canResolveHostname($hostname) ? 'http://'.$hostname : 'http://localhost';
    }

    /**
     * Determine whether the given hostname is resolvable.
     *
     * @param  string  $hostname
     * @return bool
     */
    protected function canResolveHostname($hostname)
    {
        return gethostbyname($hostname.'.') !== $hostname.'.';
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
     * @param  array  $env
     * @return \Symfony\Component\Process\Process
     */
    protected function runCommands($commands, InputInterface $input, OutputInterface $output, array $env = [])
    {
        if (! $output->isDecorated()) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                if (substr($value, 0, 3) === 'git') {
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

                if (substr($value, 0, 3) === 'git') {
                    return $value;
                }

                return $value.' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), null, $env, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write('    '.$line);
        });

        return $process;
    }

    /**
     * Replace the given file.
     *
     * @param  string  $replace
     * @param  string  $file
     * @return void
     */
    protected function replaceFile(string $replace, string $file)
    {
        $stubs = dirname(__DIR__).'/stubs';

        file_put_contents(
            $file,
            file_get_contents("$stubs/$replace"),
        );
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
