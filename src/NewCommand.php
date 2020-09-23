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
     * Laravel/UI Version
     * @var string
     */
    protected $auth_version;

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
            ->addArgument('version', InputArgument::OPTIONAL)
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
            ->addOption('jet', null, InputOption::VALUE_NONE, 'Installs the Laravel Jetstream scaffolding')
            ->addOption('stack', null, InputOption::VALUE_OPTIONAL, 'The Jetstream stack that should be installed')
            ->addOption('teams', null, InputOption::VALUE_NONE, 'Indicates whether Jetstream should be scaffolded with team support')
            ->addOption('auth', null, InputOption::VALUE_NONE, 'Installs the Laravel authentication scaffolding')
            ->addOption('preset', null, InputOption::VALUE_OPTIONAL, 'The Laravel/UI preset that should be installed')
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
        if ($input->getOption('jet') && $input->getOption('auth')) {
            throw new RuntimeException('It is not possible to install Jetstream and Laravel/UI at the same time!');
        }

        if($input->getOption('auth')){
            $this->checkAuthCompatibility($input->getArgument('version'));
            $output->write(PHP_EOL."<fg=yellow>
|                              |        .   .|
|    ,---.,---.,---..    ,,---.|        |   ||
|    ,---||    ,---| \  / |---'|        |   ||
`---'`---^`    `---^  `'  `---'`---'    `---'`

</>".PHP_EOL.PHP_EOL);
            $preset = $this->authPreset($input, $output);
        }

        if ($input->getOption('jet')) {
            $this->checkJetstreamCompatibility($input->getArgument('version'));
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

        $directory = $name && $name !== '.' ? getcwd().'/'.$name : '.';

        $version = $input->getArgument('version') ?? $this->getVersion($input);

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
            if ($name && $name !== '.') {
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
            }

            if ($input->getOption('jet')) {
                $this->installJetstream($directory, $stack, $teams, $input, $output);
            }

            if ($input->getOption('auth')) {
                $this->installAuth($directory, $preset, $input, $output);
            }

            $output->writeln(PHP_EOL.'<comment>Application ready! Build something amazing.</comment>');
        }

        return $process->getExitCode();
    }

    /**
     * Check compatibility between Laravel version and Laravel/UI
     *
     * @param string $laravel_version Laravel version
     * @return void
     */
    protected function checkAuthCompatibility($laravel_version)
    {
        if(!$laravel_version){
            return;
        }

        $version = explode('.', $laravel_version);
        $major = $version[0];
        $minor = $version[1] ?? '0';

        if($major && ($major <= '5' && ($minor !== '*' && $minor < '8'))){
            throw new RuntimeException('It is not possible to install Laravel/UI on Laravel 5.7 or lower!');
        }

        if($major <= '6'){
            $this->auth_version = '^1';
        }

        if($major === '7'){
            $this->auth_version = '^2';
        }

        if($major >= '8'){
            $this->auth_version = '^3';
        }
    }

    /**
     * Check compatibility between Laravel version and Jetstream
     *
     * @param string $version Laravel version
     * @return void
     */
    protected function checkJetstreamCompatibility($version)
    {
        $version = explode('.', $version)[0];
        if($version && $version <= '7'){
            throw new RuntimeException('It is not possible to install Jetstream on Laravel 7 or lower!');
        }
    }

    /**
     * Install Laravel UI into the application.
     *
     * @param string $directory
     * @param string $preset
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function installAuth(string $directory, string $preset, InputInterface $input, OutputInterface $output)
    {
        chdir($directory);

        $ui_command = $this->auth_version ? ' require laravel/ui "'.$this->auth_version.'"' : ' require laravel/ui';
        $commands = array_filter([
            $this->findComposer().$ui_command,
            PHP_BINARY.' artisan ui '.$preset.' --auth',
            'npm install && npm run dev',
        ]);

        $this->runCommands($commands, $input, $output);
    }

    /**
     * Determine the preset for Laravel/UI.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return string
     */
    protected function authPreset(InputInterface $input, OutputInterface $output)
    {
        $presets = [
            'bootstrap',
            'vue',
            'react',
        ];

        if ($input->getOption('preset') && in_array($input->getOption('preset'), $presets)) {
            return $input->getOption('preset');
        }

        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion('Which UI preset do you prefer?', $presets);

        $output->write(PHP_EOL);

        return $helper->ask($input, new SymfonyStyle($input, $output), $question);
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
            return 'dev-develop';
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
     * @return Process
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
     * @return string
     */
    protected function replaceInFile(string $search, string $replace, string $file)
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }
}
