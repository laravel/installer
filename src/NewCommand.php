<?php

namespace Laravel\Installer\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class NewCommand extends Command
{
    use Concerns\ConfiguresPrompts;
    use Concerns\InteractsWithHerdOrValet;

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

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
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Install the latest "development" release')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git repository')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch that should be created for a new repository', $this->defaultBranch())
            ->addOption('github', null, InputOption::VALUE_OPTIONAL, 'Create a new repository on GitHub', false)
            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'The GitHub organization to create the new repository for')
            ->addOption('database', null, InputOption::VALUE_REQUIRED, 'The database driver your application will use')
            ->addOption('react', null, InputOption::VALUE_NONE, 'Install the React Starter Kit')
            ->addOption('vue', null, InputOption::VALUE_NONE, 'Install the Vue Starter Kit')
            ->addOption('livewire', null, InputOption::VALUE_NONE, 'Install the Livewire Starter Kit')
            ->addOption('livewire-class-components', null, InputOption::VALUE_NONE, 'Generate stand-alone Livewire class components')
            ->addOption('workos', null, InputOption::VALUE_NONE, 'Use WorkOS for authentication')
            ->addOption('pest', null, InputOption::VALUE_NONE, 'Install the Pest testing framework')
            ->addOption('phpunit', null, InputOption::VALUE_NONE, 'Install the PHPUnit testing framework')
            ->addOption('npm', null, InputOption::VALUE_NONE, 'Install and build NPM dependencies')
            ->addOption('using', null, InputOption::VALUE_OPTIONAL, 'Install a custom starter kit from a community maintained package')
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
  | |    / _` |  __/ _` \ \ / / _ \ |
  | |___| (_| | | | (_| |\ V /  __/ |
  |______\__,_|_|  \__,_| \_/ \___|_|</>'.PHP_EOL.PHP_EOL);

        $this->ensureExtensionsAreAvailable($input, $output);

        if (! $input->getArgument('name')) {
            $input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. example-app',
                required: 'The project name is required.',
                validate: function ($value) use ($input) {
                    if (preg_match('/[^\pL\pN\-_.]/', $value) !== 0) {
                        return 'The name may only contain letters, numbers, dashes, underscores, and periods.';
                    }

                    if ($input->getOption('force') !== true) {
                        try {
                            $this->verifyApplicationDoesntExist($this->getInstallationDirectory($value));
                        } catch (RuntimeException $e) {
                            return 'Application already exists.';
                        }
                    }
                },
            ));
        }

        if ($input->getOption('force') !== true) {
            $this->verifyApplicationDoesntExist(
                $this->getInstallationDirectory($input->getArgument('name'))
            );
        }

        if (! $this->usingStarterKit($input)) {
            match (select(
                label: 'Which starter kit would you like to install?',
                options: [
                    'none' => 'None',
                    'react' => 'React',
                    'vue' => 'Vue',
                    'livewire' => 'Livewire',
                ],
                default: 'none',
            )) {
                'react' => $input->setOption('react', true),
                'vue' => $input->setOption('vue', true),
                'livewire' => $input->setOption('livewire', true),
                default => null,
            };

            if ($this->usingLaravelStarterKit($input)) {
                match (select(
                    label: 'Which authentication provider do you prefer?',
                    options: [
                        'laravel' => "Laravel's built-in authentication",
                        'workos' => 'WorkOS (Requires WorkOS account)',
                    ],
                    default: 'laravel',
                )) {
                    'laravel' => $input->setOption('workos', false),
                    'workos' => $input->setOption('workos', true),
                    default => null,
                };
            }

            if ($input->getOption('livewire') && ! $input->getOption('workos')) {
                $input->setOption('livewire-class-components', ! confirm(
                    label: 'Would you like to use Laravel Volt?',
                    default: true,
                ));
            }
        }

        if (! $input->getOption('phpunit') && ! $input->getOption('pest')) {
            $input->setOption('pest', select(
                label: 'Which testing framework do you prefer?',
                options: ['Pest', 'PHPUnit'],
                default: 'Pest',
            ) === 'Pest');
        }
    }

    /**
     * Ensure that the required PHP extensions are installed.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function ensureExtensionsAreAvailable(InputInterface $input, OutputInterface $output): void
    {
        $availableExtensions = get_loaded_extensions();

        $missingExtensions = collect([
            'ctype',
            'filter',
            'hash',
            'mbstring',
            'openssl',
            'session',
            'tokenizer',
        ])->reject(fn ($extension) => in_array($extension, $availableExtensions));

        if ($missingExtensions->isEmpty()) {
            return;
        }

        throw new \RuntimeException(
            sprintf('The following PHP extensions are required but are not installed: %s', $missingExtensions->join(', ', ', and '))
        );
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->validateDatabaseOption($input);

        $name = rtrim($input->getArgument('name'), '/\\');

        $directory = $this->getInstallationDirectory($name);

        $this->composer = new Composer(new Filesystem(), $directory);

        $version = $this->getVersion($input);

        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        if ($input->getOption('force') && $directory === '.') {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        $composer = $this->findComposer();
        $phpBinary = $this->phpBinary();

        $createProjectCommand = $composer." create-project laravel/laravel \"$directory\" $version --remove-vcs --prefer-dist --no-scripts";

        $starterKit = $this->getStarterKit($input);

        if ($starterKit) {
            $createProjectCommand = $composer." create-project {$starterKit} \"{$directory}\" --stability=dev";

            if ($this->usingLaravelStarterKit($input) && $input->getOption('livewire-class-components')) {
                $createProjectCommand = str_replace(" {$starterKit} ", " {$starterKit}:dev-components ", $createProjectCommand);
            }

            if ($this->usingLaravelStarterKit($input) && $input->getOption('workos')) {
                $createProjectCommand = str_replace(" {$starterKit} ", " {$starterKit}:dev-workos ", $createProjectCommand);
            }
        }

        $commands = [
            $createProjectCommand,
            $composer." run post-root-package-install -d \"$directory\"",
            $phpBinary." \"$directory/artisan\" key:generate --ansi",
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
                    'APP_URL='.$this->generateAppUrl($name, $directory),
                    $directory.'/.env'
                );

                [$database, $migrate] = $this->promptForDatabaseOptions($directory, $input);

                $this->configureDefaultDatabaseConnection($directory, $database, $name);

                if ($migrate) {
                    if ($database === 'sqlite') {
                        touch($directory.'/database/database.sqlite');
                    }

                    $commands = [
                        trim(sprintf(
                            $this->phpBinary().' artisan migrate %s',
                            ! $input->isInteractive() ? '--no-interaction' : '',
                        )),
                    ];

                    $this->runCommands($commands, $input, $output, workingPath: $directory);
                }
            }

            if ($input->getOption('git') || $input->getOption('github') !== false) {
                $this->createRepository($directory, $input, $output);
            }

            if ($input->getOption('pest')) {
                $this->installPest($directory, $input, $output);
            }

            if ($input->getOption('github') !== false) {
                $this->pushToGitHub($name, $directory, $input, $output);
                $output->writeln('');
            }

            $this->configureComposerDevScript($directory);

            if ($input->getOption('pest')) {
                $output->writeln('');
            }

            $runNpm = $input->getOption('npm');

            if (! $input->getOption('npm') && $input->isInteractive()) {
                $runNpm = confirm(
                    label: 'Would you like to run <options=bold>npm install</> and <options=bold>npm run build</>?'
                );
            }

            if ($runNpm) {
                $this->runCommands(['npm install', 'npm run build'], $input, $output, workingPath: $directory);
            }

            $output->writeln("  <bg=blue;fg=white> INFO </> Application ready in <options=bold>[{$name}]</>. You can start your local development using:".PHP_EOL);
            $output->writeln('<fg=gray>➜</> <options=bold>cd '.$name.'</>');

            if (! $runNpm) {
                $output->writeln('<fg=gray>➜</> <options=bold>npm install && npm run build</>');
            }

            if ($this->isParkedOnHerdOrValet($directory)) {
                $url = $this->generateAppUrl($name, $directory);
                $output->writeln('<fg=gray>➜</> Open: <options=bold;href='.$url.'>'.$url.'</>');
            } else {
                $output->writeln('<fg=gray>➜</> <options=bold>composer run dev</>');
            }

            $output->writeln('');
            $output->writeln('  New to Laravel? Check out our <href=https://laravel.com/docs/installation#next-steps>documentation</>. <options=bold>Build something amazing!</>');
            $output->writeln('');
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
     * Configure the default database connection.
     *
     * @param  string  $directory
     * @param  string  $database
     * @param  string  $name
     * @return void
     */
    protected function configureDefaultDatabaseConnection(string $directory, string $database, string $name)
    {
        $this->pregReplaceInFile(
            '/DB_CONNECTION=.*/',
            'DB_CONNECTION='.$database,
            $directory.'/.env'
        );

        $this->pregReplaceInFile(
            '/DB_CONNECTION=.*/',
            'DB_CONNECTION='.$database,
            $directory.'/.env.example'
        );

        if ($database === 'sqlite') {
            $environment = file_get_contents($directory.'/.env');

            // If database options aren't commented, comment them for SQLite...
            if (! str_contains($environment, '# DB_HOST=127.0.0.1')) {
                $this->commentDatabaseConfigurationForSqlite($directory);

                return;
            }

            return;
        }

        // Any commented database configuration options should be uncommented when not on SQLite...
        $this->uncommentDatabaseConfiguration($directory);

        $defaultPorts = [
            'pgsql' => '5432',
            'sqlsrv' => '1433',
        ];

        if (isset($defaultPorts[$database])) {
            $this->replaceInFile(
                'DB_PORT=3306',
                'DB_PORT='.$defaultPorts[$database],
                $directory.'/.env'
            );

            $this->replaceInFile(
                'DB_PORT=3306',
                'DB_PORT='.$defaultPorts[$database],
                $directory.'/.env.example'
            );
        }

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

    /**
     * Determine if the application is using Laravel 11 or newer.
     *
     * @param  string  $directory
     * @return bool
     */
    public function usingLaravelVersionOrNewer(int $usingVersion, string $directory): bool
    {
        $version = json_decode(file_get_contents($directory.'/composer.json'), true)['require']['laravel/framework'];
        $version = str_replace('^', '', $version);
        $version = explode('.', $version)[0];

        return $version >= $usingVersion;
    }

    /**
     * Comment the irrelevant database configuration entries for SQLite applications.
     *
     * @param  string  $directory
     * @return void
     */
    protected function commentDatabaseConfigurationForSqlite(string $directory): void
    {
        $defaults = [
            'DB_HOST=127.0.0.1',
            'DB_PORT=3306',
            'DB_DATABASE=laravel',
            'DB_USERNAME=root',
            'DB_PASSWORD=',
        ];

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => "# {$default}")->all(),
            $directory.'/.env'
        );

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => "# {$default}")->all(),
            $directory.'/.env.example'
        );
    }

    /**
     * Uncomment the relevant database configuration entries for non SQLite applications.
     *
     * @param  string  $directory
     * @return void
     */
    protected function uncommentDatabaseConfiguration(string $directory)
    {
        $defaults = [
            '# DB_HOST=127.0.0.1',
            '# DB_PORT=3306',
            '# DB_DATABASE=laravel',
            '# DB_USERNAME=root',
            '# DB_PASSWORD=',
        ];

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => substr($default, 2))->all(),
            $directory.'/.env'
        );

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => substr($default, 2))->all(),
            $directory.'/.env.example'
        );
    }

    /**
     * Determine the default database connection.
     *
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return array
     */
    protected function promptForDatabaseOptions(string $directory, InputInterface $input)
    {
        $defaultDatabase = collect(
            $databaseOptions = $this->databaseOptions()
        )->keys()->first();

        if (! $input->getOption('database') && $this->usingStarterKit($input)) {
            // Starter kits will already be migrated in post composer create-project command...
            $migrate = false;

            $input->setOption('database', 'sqlite');
        }

        if (! $input->getOption('database') && $input->isInteractive()) {
            $input->setOption('database', select(
                label: 'Which database will your application use?',
                options: $databaseOptions,
                default: $defaultDatabase,
            ));

            if ($input->getOption('database') !== 'sqlite') {
                $migrate = confirm(
                    label: 'Default database updated. Would you like to run the default database migrations?'
                );
            } else {
                $migrate = true;
            }
        }

        return [$input->getOption('database') ?? $defaultDatabase, $migrate ?? $input->hasOption('database')];
    }

    /**
     * Get the available database options.
     *
     * @return array
     */
    protected function databaseOptions(): array
    {
        return collect([
            'sqlite' => ['SQLite', extension_loaded('pdo_sqlite')],
            'mysql' => ['MySQL', extension_loaded('pdo_mysql')],
            'mariadb' => ['MariaDB', extension_loaded('pdo_mysql')],
            'pgsql' => ['PostgreSQL', extension_loaded('pdo_pgsql')],
            'sqlsrv' => ['SQL Server', extension_loaded('pdo_sqlsrv')],
        ])
            ->sortBy(fn ($database) => $database[1] ? 0 : 1)
            ->map(fn ($database) => $database[0].($database[1] ? '' : ' (Missing PDO extension)'))
            ->all();
    }

    /**
     * Validate the database driver input.
     *
     * @param  \Symfony\Components\Console\Input\InputInterface  $input
     */
    protected function validateDatabaseOption(InputInterface $input)
    {
        if ($input->getOption('database') && ! in_array($input->getOption('database'), $drivers = ['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv'])) {
            throw new \InvalidArgumentException("Invalid database driver [{$input->getOption('database')}]. Valid options are: ".implode(', ', $drivers).'.');
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
        $composerBinary = $this->findComposer();

        $commands = [
            $composerBinary.' remove phpunit/phpunit --dev --no-update',
            $composerBinary.' require pestphp/pest pestphp/pest-plugin-laravel --no-update --dev',
            $composerBinary.' update',
            $this->phpBinary().' ./vendor/bin/pest --init',
        ];

        $commands[] = $composerBinary.' require pestphp/pest-plugin-drift --dev';
        $commands[] = $this->phpBinary().' ./vendor/bin/pest --drift';
        $commands[] = $composerBinary.' remove pestphp/pest-plugin-drift --dev';

        $this->runCommands($commands, $input, $output, workingPath: $directory, env: [
            'PEST_NO_SUPPORT' => 'true',
        ]);

        if ($this->usingStarterKit($input)) {
            $this->replaceInFile(
                './vendor/bin/phpunit',
                './vendor/bin/pest',
                $directory.'/.github/workflows/tests.yml',
            );

            $contents = file_get_contents("$directory/tests/Pest.php");

            $contents = str_replace(
                " // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)",
                "    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)",
                $contents,
            );

            file_put_contents("$directory/tests/Pest.php", $contents);

            $directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$directory/tests"));

            foreach ($directoryIterator as $testFile) {
                if ($testFile->isDir()) {
                    continue;
                }

                $contents = file_get_contents($testFile);

                file_put_contents(
                    $testFile,
                    str_replace("\n\nuses(\Illuminate\Foundation\Testing\RefreshDatabase::class);", '', $contents),
                );
            }
        }

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
        $branch = $input->getOption('branch') ?: $this->defaultBranch();

        $commands = [
            'git init -q',
            'git add .',
            'git commit -q -m "Set up a fresh Laravel app"',
            "git branch -M {$branch}",
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);
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

        $commands = [
            'git add .',
            "git commit -q -m \"$message\"",
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);
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

        $name = $input->getOption('organization') ? $input->getOption('organization')."/$name" : $name;
        $flags = $input->getOption('github') ?: '--private';

        $commands = [
            "gh repo create {$name} --source=. --push {$flags}",
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory, env: ['GIT_TERMINAL_PROMPT' => 0]);
    }

    /**
     * Configure the Composer "dev" script.
     *
     * @param  string  $directory
     * @return void
     */
    protected function configureComposerDevScript(string $directory): void
    {
        $this->composer->modify(function (array $content) {
            if (windows_os()) {
                $content['scripts']['dev'] = [
                    'Composer\\Config::disableProcessTimeout',
                    "npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"npm run dev\" --names='server,queue,vite'",
                ];
            }

            return $content;
        });
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
     * @param  string  $directory
     * @return string
     */
    protected function generateAppUrl($name, $directory)
    {
        if (! $this->isParkedOnHerdOrValet($directory)) {
            return 'http://localhost:8000';
        }

        $hostname = mb_strtolower($name).'.'.$this->getTld();

        return $this->canResolveHostname($hostname) ? 'http://'.$hostname : 'http://localhost';
    }

    /**
     * Get the starter kit repository, if any.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return string|null
     */
    protected function getStarterKit(InputInterface $input): ?string
    {
        return match (true) {
            $input->getOption('react') => 'laravel/react-starter-kit',
            $input->getOption('vue') => 'laravel/vue-starter-kit',
            $input->getOption('livewire') => 'laravel/livewire-starter-kit',
            default => $input->getOption('using'),
        };
    }

    /**
     * Determine if a Laravel first-party starter kit has been chosen.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return bool
     */
    protected function usingLaravelStarterKit(InputInterface $input): bool
    {
        return $this->usingStarterKit($input) &&
               str_starts_with($this->getStarterKit($input), 'laravel/');
    }

    /**
     * Determine if a starter kit is being used.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return bool
     */
    protected function usingStarterKit(InputInterface $input)
    {
        return $input->getOption('react') || $input->getOption('vue') || $input->getOption('livewire') || $input->getOption('using');
    }

    /**
     * Get the TLD for the application.
     *
     * @return string
     */
    protected function getTld()
    {
        return $this->runOnValetOrHerd('tld') ?: 'test';
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
     * Get the installation directory.
     *
     * @param  string  $name
     * @return string
     */
    protected function getInstallationDirectory(string $name)
    {
        return $name !== '.' ? getcwd().'/'.$name : '.';
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
        return implode(' ', $this->composer->findComposer());
    }

    /**
     * Get the path to the appropriate PHP binary.
     *
     * @return string
     */
    protected function phpBinary()
    {
        $phpBinary = function_exists('Illuminate\Support\php_binary')
            ? \Illuminate\Support\php_binary()
            : (new PhpExecutableFinder)->find(false);

        return $phpBinary !== false
            ? ProcessUtils::escapeArgument($phpBinary)
            : 'php';
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string|null  $workingPath
     * @param  array  $env
     * @return \Symfony\Component\Process\Process
     */
    protected function runCommands($commands, InputInterface $input, OutputInterface $output, ?string $workingPath = null, array $env = [])
    {
        if (! $output->isDecorated()) {
            $commands = array_map(function ($value) {
                if (Str::startsWith($value, ['chmod', 'git', $this->phpBinary().' ./vendor/bin/pest'])) {
                    return $value;
                }

                return $value.' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (Str::startsWith($value, ['chmod', 'git', $this->phpBinary().' ./vendor/bin/pest'])) {
                    return $value;
                }

                return $value.' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, $env, null, null);

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
     * @param  string|array  $search
     * @param  string|array  $replace
     * @param  string  $file
     * @return void
     */
    protected function replaceInFile(string|array $search, string|array $replace, string $file)
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }

    /**
     * Replace the given string in the given file using regular expressions.
     *
     * @param  string|array  $search
     * @param  string|array  $replace
     * @param  string  $file
     * @return void
     */
    protected function pregReplaceInFile(string $pattern, string $replace, string $file)
    {
        file_put_contents(
            $file,
            preg_replace($pattern, $replace, file_get_contents($file))
        );
    }

    /**
     * Delete the given file.
     *
     * @param  string  $file
     * @return void
     */
    protected function deleteFile(string $file)
    {
        unlink($file);
    }
}
