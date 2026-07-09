<?php

namespace Laravel\Installer\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Support\Logger;
use Override;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

use function Laravel\Prompts\task;
use function Laravel\Prompts\text;

class PackageCommand extends Command
{
    use Concerns\ConfiguresPrompts;

    /**
     * The Composer instance.
     */
    protected Composer $composer;

    /**
     * The agent context, encapsulating detection and JSON output behavior.
     */
    protected Agent $agent;

    /**
     * Detect agents, suppress interactive output, and emit a JSON result.
     */
    #[Override]
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->agent = new Agent;

        if (! $this->agent->isActive()) {
            return parent::run($input, $output);
        }

        $input->setInteractive(false);

        $logOutput = $this->agent->openLog();

        Prompt::setOutput($logOutput);

        try {
            $exitCode = parent::run($input, $logOutput);
        } catch (Throwable $e) {
            $this->agent->emitFailure(['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        if ($exitCode === self::SUCCESS) {
            $this->agent->emitSuccess();
        } else {
            $this->agent->emitFailure();
        }

        return $exitCode;
    }

    /**
     * Configure the command options.
     */
    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('package')
            ->setDescription('Create a new Laravel package')
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force install even if the directory already exists')
            ->addOption('config', null, InputOption::VALUE_NONE, 'Include a configuration file')
            ->addOption('routes', null, InputOption::VALUE_NONE, 'Include routes')
            ->addOption('views', null, InputOption::VALUE_NONE, 'Include views')
            ->addOption('translations', null, InputOption::VALUE_NONE, 'Include translations')
            ->addOption('migrations', null, InputOption::VALUE_NONE, 'Include migrations')
            ->addOption('assets', null, InputOption::VALUE_NONE, 'Include assets')
            ->addOption('commands', null, InputOption::VALUE_NONE, 'Include commands')
            ->addOption('facade', null, InputOption::VALUE_NONE, 'Include a facade')
            ->addOption('boost-skill', null, InputOption::VALUE_NONE, 'Include a Laravel Boost skill')
            ->addOption('author-name', null, InputOption::VALUE_REQUIRED, 'Author name')
            ->addOption('author-email', null, InputOption::VALUE_REQUIRED, 'Author email')
            ->addOption('package-name', null, InputOption::VALUE_REQUIRED, 'Package name')
            ->addOption('package-name-human', null, InputOption::VALUE_REQUIRED, 'Package display name')
            ->addOption('package-description', null, InputOption::VALUE_REQUIRED, 'Package description')
            ->addOption('vendor-namespace', null, InputOption::VALUE_REQUIRED, 'Vendor namespace')
            ->addOption('class-name', null, InputOption::VALUE_REQUIRED, 'Main class name');
    }

    /**
     * Interact with the user before validating the input.
     */
    #[Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $this->configurePrompts($input, $output);

        if (! $input->getArgument('name')) {
            $input->setArgument('name', text(
                label: 'What is the name of your package?',
                placeholder: 'E.g. my-package',
                required: 'The package name is required.',
                validate: function ($value) use ($input) {
                    if (preg_match('/[^\pL\pN\-_.]/', $value) !== 0) {
                        return 'The name may only contain letters, numbers, dashes, underscores, and periods.';
                    }

                    if ($input->getOption('force') !== true) {
                        $directory = getcwd().'/'.$value;

                        if ((is_dir($directory) || is_file($directory)) && $directory !== getcwd()) {
                            return 'Directory already exists.';
                        }
                    }
                },
            ));
        }
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (! $name) {
            throw new RuntimeException('Please provide the package name as an argument (e.g. laravel package my-package).');
        }

        $name = rtrim($name, '/\\');

        if (preg_match('/[^\pL\pN\-_.]/', $name) !== 0) {
            throw new RuntimeException('The name may only contain letters, numbers, dashes, underscores, and periods.');
        }

        $directory = $this->getInstallationDirectory($name);

        $this->agent->rememberInstallation($directory);

        $this->composer = new Composer(new Filesystem, $directory);

        if (! $input->getOption('force')) {
            $this->verifyDirectoryDoesntExist($directory);
        }

        if ($input->getOption('force') && $directory === '.') {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        $composer = implode(' ', $this->composer->findComposer());
        $commands = [];

        if ($input->getOption('force')) {
            if (PHP_OS_FAMILY === 'Windows') {
                $commands["Removed existing directory [{$name}]"] = "(if exist \"$directory\" rd /s /q \"$directory\")";
            } else {
                $commands["Removed existing directory [{$name}]"] = "rm -rf \"$directory\"";
            }
        }

        $commands['Package cloned'] = "git clone https://github.com/laravel/package-skeleton.git \"$directory\" --quiet";

        if (PHP_OS_FAMILY === 'Windows') {
            $commands['Git history cleaned'] = "(if exist \"$directory/.git\" rd /s /q \"$directory/.git\")";
        } else {
            $commands['Git history cleaned'] = "rm -rf \"$directory/.git\"";
        }
        $commands['Dependencies installed'] = $composer." install --working-dir=\"$directory\" --no-scripts";

        $process = $this->runCommands(
            $commands,
            $input,
            $output,
            taskLabel: 'Creating Laravel package',
        );

        if (! $process->isSuccessful()) {
            return $process->getExitCode();
        }

        $configureCommand = $this->phpBinary().' configure.php';

        if (! $input->isInteractive()) {
            $configureCommand .= ' --no-interaction';
        }

        $booleanOptions = ['config', 'routes', 'views', 'translations', 'migrations', 'assets', 'commands', 'facade', 'boost-skill'];
        $valueOptions = ['author-name', 'author-email', 'package-name', 'package-name-human', 'package-description', 'vendor-namespace', 'class-name'];

        foreach ($booleanOptions as $option) {
            if ($input->getOption($option)) {
                $configureCommand .= ' --'.$option;
            }
        }

        foreach ($valueOptions as $option) {
            if ($value = $input->getOption($option)) {
                $configureCommand .= ' --'.$option.'='.escapeshellarg($value);
            }
        }

        $configureCommand .= ' --installer-dir='.$directory;

        $configureProcess = $this->runCommands(
            [$configureCommand],
            $input,
            $output,
            $directory,
        );

        return $configureProcess->getExitCode();
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
     * Get the installation directory.
     */
    protected function getInstallationDirectory(string $name)
    {
        if ($name === '.') {
            return '.';
        }

        return str_starts_with($name, DIRECTORY_SEPARATOR) ? $name : getcwd().'/'.$name;
    }

    /**
     * Verify that the directory doesn't already exist.
     */
    protected function verifyDirectoryDoesntExist(string $directory): void
    {
        if ((is_dir($directory) || is_file($directory)) && $directory !== getcwd()) {
            throw new RuntimeException('Package already exists!');
        }
    }

    /**
     * Run the given commands.
     */
    protected function runCommands(
        array $commands,
        InputInterface $input,
        OutputInterface $output,
        ?string $workingPath = null,
        array $env = [],
        ?string $taskLabel = null,
    ): Process {
        $commands = array_map(fn ($value) => (is_array($value)) ? $value : [$value], $commands);

        if (! $output->isDecorated()) {
            $commands = array_map(
                fn ($values) => array_map(function ($value) {
                    if (Str::startsWith($value, ['chmod', 'rm', 'git'])) {
                        return $value;
                    }

                    return $value.' --no-ansi';
                }, $values),
                $commands,
            );
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(
                fn ($values) => array_map(function ($value) {
                    if (Str::startsWith($value, ['chmod', 'rm', 'git'])) {
                        return $value;
                    }

                    return $value.' --quiet';
                }, $values),
                $commands,
            );
        }

        if ($this->shouldRunAsTask($output, $commands)) {
            return $this->runCommandsAsTask($commands, $workingPath, $env, $taskLabel);
        }

        $commandline = implode(' && ', array_map(fn ($values) => implode(' && ', $values), $commands));

        $process = Process::fromShellCommandline($commandline, $workingPath, $env, null, null);

        if (Process::isTtySupported() && $input->isInteractive() && ! $this->agent->isActive()) {
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
     * Determine if the commands should be run as tasks.
     */
    protected function shouldRunAsTask(OutputInterface $output, array $commands): bool
    {
        return function_exists('Laravel\Prompts\task')
            && function_exists('pcntl_fork')
            && ! array_is_list($commands)
            && ! $this->agent->isActive()
            && $this->useConciseOutput($output);
    }

    /**
     * Run the given shell commands within a Laravel Prompts task.
     */
    protected function runCommandsAsTask(
        array $commands,
        ?string $workingPath,
        array $env,
        ?string $taskLabel = null,
    ): Process {
        return task(
            label: $taskLabel ? str($taskLabel)->finish('...') : '',
            keepSummary: true,
            callback: function (Logger $logger) use ($commands, $workingPath, $env, $taskLabel) {
                $process = null;

                foreach ($commands as $label => $subCommands) {
                    foreach ($subCommands as $command) {
                        $logger->subLabel($command);

                        $process = Process::fromShellCommandline($command, $workingPath, $env, null, null);
                        $process->run(function ($type, $line) use ($logger) {
                            $logger->line($line);
                        });

                        if (! $process->isSuccessful()) {
                            $logger->error($label);
                            $logger->error('Command failed: '.$command);
                            $logger->error('Error output: '.trim($process->getErrorOutput()));

                            return $process;
                        }
                    }

                    $logger->success($label);
                }

                if ($taskLabel) {
                    $logger->label($taskLabel);
                }

                return $process;
            },
        );
    }

    /**
     * Determine if concise output should be used.
     */
    protected function useConciseOutput(OutputInterface $output): bool
    {
        return $output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL || $output->isQuiet();
    }
}
