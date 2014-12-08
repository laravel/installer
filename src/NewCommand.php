<?php namespace Laravel\Installer\Console;

use Illuminate\Console\Command;
use Laravel\Installer\Receipe\Receipe;
use Laravel\Installer\Receipe\TestFrameworkReceipe;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;

class NewCommand extends Command
{

    /**
     * @var string $name The application name
     */
    protected $appName;

    /**
     * @var string $directory The application absolute path
     */
    protected $directory;

    /**
     * @var string $laravelVersion The laravel version chose
     */
    protected $laravelVersion;

    /**
     * @var array $composer The future content of composer.json
     */
    protected $composer;

    /**
     * @var Receipe[] $receipes The receipes to be used
     */
    protected $receipes;

    /**
     * @return mixed
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('new')
            ->setDescription('Create a new Laravel application.')
            ->addArgument('name', InputArgument::REQUIRED);
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->appName = $input->getArgument('name');

        $this->verifyApplicationDoesntExist(
            $this->directory = getcwd() . '/' . $this->appName,
            $output
        );

        $this->laravelVersion = $this->choice('What version of laravel do you use?', ['master', 'develop']);

        $output->writeln('<info>Crafting application...</info>');

        $this->download($zipFile = $this->makeFilename())
            ->extract($zipFile)
            ->cleanUp($zipFile);

        $output->writeln('<comment>Configuration...</comment>');

        // Here will go all the recipes to make a custom laravel application
        if ($this->confirm('Do you want to configure the application yourself? [yes/no]')) {
            $this->removeDefaultConfiguration();
            $this->initializeReceipes();
            $this->runReceipes();
            $this->loadConfiguration();
        }

        $output->writeln('<comment>Run `composer create-project` in ' . $this->directory . ', and enjoy building something amazing!</comment>');
    }

    /**
     * Remove the composer.json file in the application directory
     */
    protected function removeDefaultConfiguration()
    {
        $this->composer = \GuzzleHttp\json_decode(file_get_contents($this->directory . '/composer.json'), true);

        unlink($this->directory . '/composer.json');
    }

    /**
     * Run every receipe in $this->receipes
     */
    protected function runReceipes()
    {
        foreach ($this->receipes as $receipe) {
            $receipe->run($this->composer);
        }
    }

    /**
     * Load the configuration into the application's composer.json
     */
    protected function loadConfiguration()
    {
        file_put_contents($this->directory . '/composer.json', $this->formatComposer());
    }

    /**
     * Format the composer.json file
     *
     * @return string
     */
    protected function formatComposer()
    {
        return json_encode($this->composer, JSON_PRETTY_PRINT);
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param string $directory
     * @param OutputInterface $output
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
            $output->writeln('<error>Application already exists!</error>');

            exit(1);
        }
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd() . '/laravel_' . md5(time() . uniqid()) . '.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param string $zipFile
     * @return $this
     */
    protected function download($zipFile)
    {
        $response = \GuzzleHttp\get("https://github.com/laravel/laravel/archive/$this->laravelVersion.zip")->getBody();

        file_put_contents($zipFile, $response);

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param string $zipFile
     * @return $this
     */
    protected function extract($zipFile)
    {
        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo(getcwd());

        $archive->close();

        @rename('laravel-' . $this->laravelVersion, $this->appName);

        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param string $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);

        @unlink($zipFile);

        return $this;
    }

    /**
     * Set up the receipes to be used
     */
    protected function initializeReceipes()
    {
        $this->receipes[] = new TestFrameworkReceipe($this);
    }

}
