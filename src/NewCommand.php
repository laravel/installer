<?php

namespace Laravel\Installer\Console;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class NewCommand extends Command
{
    use Concerns\ConfiguresPrompts;

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
            ->addOption('prompt-breeze', null, InputOption::VALUE_NONE, 'Issues a prompt to determine if Breeze should be installed (Deprecated)')
            ->addOption('prompt-jetstream', null, InputOption::VALUE_NONE, 'Issues a prompt to determine if Jetstream should be installed (Deprecated)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
    }

    /**
     * Interact with the user before validating the input.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);

        $this->configurePrompts($input, $output);

        $output->write(PHP_EOL.'  <fg=red> _                               _
  | |                             | |
  | |     __ _ _ __ __ ___   _____| |
  | |    / _` | \'__/ _` \ \ / / _ \ |
  | |___| (_| | | | (_| |\ V /  __/ |
  |______\__,_|_|  \__,_| \_/ \___|_|</>'.PHP_EOL.PHP_EOL);

        if (! $input->getArgument('name')) {
            $input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. example-app',
                required: 'The project name is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The name may only contain letters, numbers, dashes, underscores, and periods.'
                    : null,
            ));
        }

        if (! $input->getOption('breeze') && ! $input->getOption('jet')) {
            match (select(
                label: 'Would you like to install a starter kit?',
                options: [
                    'none' => 'No starter kit',
                    'breeze' => 'Laravel Breeze',
                    'jetstream' => 'Laravel Jetstream',
                ],
            )) {
                'breeze' => $input->setOption('breeze', true),
                'jetstream' => $input->setOption('jet', true),
                default => null,
            };
        }

        if ($input->getOption('breeze')) {
            $this->promptForBreezeOptions($input);
        } elseif ($input->getOption('jet')) {
            $this->promptForJetstreamOptions($input);
        }

        if (! $input->getOption('phpunit') && ! $input->getOption('pest')) {
            $input->setOption('pest', select(
                label: 'Which testing framework do you prefer?',
                options: ['PHPUnit', 'Pest'],
            ) === 'Pest');
        }

        if (! $input->getOption('git') && $input->getOption('github') === false && Process::fromShellCommandline('git --version')->run() === 0) {
            $input->setOption('git', confirm(label: 'Would you like to initialize a Git repository?', default: false));
        }
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
        $this->validateStackOption($input);

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

            if ($input->getOption('breeze')) {
                $this->installBreeze($directory, $input, $output);
            } elseif ($input->getOption('jet')) {
                $this->installJetstream($directory, $input, $output);
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
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function installBreeze(string $directory, InputInterface $input, OutputInterface $output)
    {
        chdir($directory);

        $commands = array_filter([
            $this->findComposer().' require laravel/breeze',
            trim(sprintf(
                '"'.PHP_BINARY.'" artisan breeze:install %s %s %s %s',
                $input->getOption('stack'),
                $input->getOption('pest') ? '--pest' : '',
                $input->getOption('dark') ? '--dark' : '',
                $input->getOption('ssr') ? '--ssr' : '',
            )),
        ]);

        $this->runCommands($commands, $input, $output);

        $this->commitChanges('Install Breeze', $directory, $input, $output);
    }

    /**
     * Install Laravel Jetstream into the application.
     *
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function installJetstream(string $directory, InputInterface $input, OutputInterface $output)
    {
        chdir($directory);

        $commands = array_filter([
            $this->findComposer().' require laravel/jetstream',
            trim(sprintf(
                '"'.PHP_BINARY.'" artisan jetstream:install %s %s %s %s',
                $input->getOption('stack'),
                $input->getOption('teams') ? '--teams' : '',
                $input->getOption('dark') ? '--dark' : '',
                $input->getOption('pest') ? '--pest' : '',
            )),
        ]);

        $this->runCommands($commands, $input, $output);

        $this->commitChanges('Install Jetstream', $directory, $input, $output);
    }

    /**
     * Determine the stack for Breeze.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return void
     */
    protected function promptForBreezeOptions(InputInterface $input)
    {
        if (! $input->getOption('stack')) {
            $input->setOption('stack', select(
                label: 'Which Breeze stack would you like to install?',
                options: [
                    'blade' => 'Blade',
                    'react' => 'React with Inertia',
                    'vue' => 'Vue with Inertia',
                    'api' => 'API only',
                ]
            ));
        }

        if (in_array($input->getOption('stack'), ['react', 'vue']) && (! $input->getOption('dark') || ! $input->getOption('ssr'))) {
            collect(multiselect(
                label: 'Would you like any optional features?',
                options: [
                    'dark' => 'Dark mode',
                    'ssr' => 'Inertia SSR',
                ],
                default: array_filter([
                    $input->getOption('dark') ? 'dark' : null,
                    $input->getOption('ssr') ? 'ssr' : null,
                ]),
            ))->each(fn ($option) => $input->setOption($option, true));
        } elseif ($input->getOption('stack') === 'blade' && ! $input->getOption('dark')) {
            $input->setOption('dark', confirm(
                label: 'Would you like dark mode support?',
                default: false,
            ));
        }
    }

    /**
     * Determine the stack for Jetstream.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return void
     */
    protected function promptForJetstreamOptions(InputInterface $input)
    {
        if (! $input->getOption('stack')) {
            $input->setOption('stack', select(
                label: 'Which Jetstream stack would you like to install?',
                options: [
                    'inertia' => 'Vue with Inertia',
                    'livewire' => 'Livewire',
                ]
            ));
        }

        collect(multiselect(
            label: 'Would you like any optional features?',
            options: collect([
                'teams' => 'Team support',
                'dark' => 'Dark mode',
            ])->when(
                $input->getOption('stack') === 'inertia',
                fn ($options) => $options->put('ssr', 'Inertia SSR')
            )->all(),
            default: array_filter([
                $input->getOption('teams') ? 'teams' : null,
                $input->getOption('dark') ? 'dark' : null,
                $input->getOption('stack') === 'inertia' && $input->getOption('ssr') ? 'ssr' : null,
            ]),
        ))->each(fn ($option) => $input->setOption($option, true));
    }

    protected function validateStackOption(InputInterface $input)
    {
        if ($input->getOption('breeze')) {
            if (! in_array($input->getOption('stack'), $stacks = ['blade', 'react', 'vue', 'api'])) {
                throw new \InvalidArgumentException("Invalid Breeze stack [{$input->getOption('stack')}]. Valid options are: ".implode(', ', $stacks).'.');
            }

            return;
        }

        if ($input->getOption('jet')) {
            if (! in_array($input->getOption('stack'), $stacks = ['inertia', 'livewire'])) {
                throw new \InvalidArgumentException("Invalid Jetstream stack [{$input->getOption('stack')}]. Valid options are: ".implode(', ', $stacks).'.');
            }

            return;
        }
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
            '"'.PHP_BINARY.'" ./vendor/bin/pest --init',
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
