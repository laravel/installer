<?php namespace Laravel\Installer\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;

class NewCommand extends Command
{

    protected $appName;

    protected $laravelVersion;

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
            $directory = getcwd() . '/' . $this->appName,
            $output
        );

        $this->laravelVersion = $this->choice('What version of laravel do you use?', ['master', 'develop']);

        $output->writeln('<info>Crafting application...</info>');

        $this->download($zipFile = $this->makeFilename())
            ->extract($zipFile)
            ->cleanUp($zipFile);

        $output->writeln('<comment>Configuration...</comment>');

        // Here will go all the recipes to make a custom laravel application

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
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

}
