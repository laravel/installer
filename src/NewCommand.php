<?php namespace Laravel\Installer\Console;

use GuzzleHttp\Event\ProgressEvent;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use ZipArchive;

class NewCommand extends \Symfony\Component\Console\Command\Command {

    private $input;
    private $output;
    private $directory;
    private $zipFile;
    private $progress = 0;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('new')
             ->setDescription('Create a new Laravel application.')
             ->addArgument('name', InputArgument::REQUIRED)
             ->addOption('slim', null, InputOption::VALUE_NONE)
             ->addOption('force', null, InputOption::VALUE_NONE);
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input     = $input;
        $this->output    = $output;
        $this->directory = getcwd() . '/' . $input->getArgument('name');
        $this->zipFile   = $this->makeFilename();

        $output->writeln('<info>Crafting NukaCode application...</info>');


        $this->verifyApplicationDoesntExist();

        $this->download();

        $this->extract();

        $this->runComposerCommands();

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     *
     * @return void
     */
    protected function verifyApplicationDoesntExist()
    {
        $this->output->writeln('<info>Checking application path for existing site...</info>');

        if (is_dir($this->directory)) {
            $this->output->writeln('<error>Application already exists!</error>');

            exit(1);
        }

        $this->output->writeln('<info>Check complete...</info>');
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @return $this
     */
    protected function download()
    {
        $buildUrl = $this->getBuildFileLocation();

        if ($this->input->getOption('force')) {
            $this->output->writeln('<info>--force command given. Deleting old build files...</info>');

            $this->cleanUp();

            $this->output->writeln('<info>complete...</info>');
        }

        if ($this->checkIfServerHasNewerBuild()) {
            $this->cleanUp();
            $this->downloadFileWithProgressBar($buildUrl);
        }

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     *
     * @return $this
     */
    protected function extract()
    {
        $this->output->writeln('<info>Extracting files...</info>');

        $archive = new ZipArchive;

        $archive->open($this->zipFile);

        $archive->extractTo($this->directory);

        $archive->close();

        $this->output->writeln('<info>Extracting complete...</info>');

        return $this;
    }

    /**
     * Run post install composer commands
     *
     * @return void
     */
    protected function runComposerCommands()
    {
        $this->output->writeln('<info>Running post install scripts...</info>');

        $composer = $this->findComposer();

        $commands = [
            $composer . ' run-script post-install-cmd',
            $composer . ' run-script post-create-project-cmd',
        ];

        $process = new Process(implode(' && ', $commands), $this->directory, null, null, null);

        $process->run(function ($type, $line) {
            $this->output->write($line);
        });

        $this->output->writeln('<info>Scripts complete...</info>');
    }

    /**
     * Clean-up the Zip file.
     *
     * @return $this
     */
    protected function cleanUp()
    {
        @chmod($this->zipFile, 0777);
        @unlink($this->zipFile);

        return $this;
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
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        if ($this->input->getOption('slim')) {
            return getcwd() . '/laravel_slim.zip';
        }

        return getcwd() . '/laravel_full.zip';
    }

    /**
     * Get the build file location based on the flags passed in.
     *
     * @return string
     */
    protected function getBuildFileLocation()
    {
        if ($this->input->getOption('slim')) {
            return 'http://builds.nukacode.com/slim/latest.zip';
        }

        return 'http://builds.nukacode.com/full/latest.zip';
    }

    /**
     * Download the nukacode build files and display progress bar.
     *
     * @param $buildUrl
     */
    protected function downloadFileWithProgressBar($buildUrl)
    {
        $this->output->writeln('<info>Begin file download...</info>');

        $progressBar = new ProgressBar($this->output, 100);
        $progressBar->start();

        $client  = new \GuzzleHttp\Client();
        $request = $client->createRequest('GET', $buildUrl);
        $request->getEmitter()->on('progress', function (ProgressEvent $e) use ($progressBar) {
            if ($e->downloaded > 0) {
                $localProgress = floor(($e->downloaded / $e->downloadSize * 100));

                if ($localProgress != $this->progress) {
                    $this->progress = (integer) $localProgress;
                    $progressBar->advance();
                }
            }
        });

        $response = $client->send($request);

        $progressBar->finish();

        file_put_contents($this->zipFile, $response->getBody());

        $this->output->writeln("\n<info>File download complete...</info>");
    }

    /**
     * Check if the server has a newer version of the nukacode build.
     *
     * @return bool
     */
    protected function checkIfServerHasNewerBuild()
    {
        if (file_exists($this->zipFile)) {
            $client  = new \GuzzleHttp\Client();
            $response = $client->get('http://builds.nukacode.com/files.php');

            // The downloaded copy is the same as the one on the server.
            if (in_array(md5_file($this->zipFile), $response->json())) {
                return false;
            }
        }

        return true;
    }
}
