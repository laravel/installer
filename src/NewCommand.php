<?php namespace Laravel\Installer\Console;

use ZipArchive;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NewCommand extends \Symfony\Component\Console\Command\Command {

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
				->addOption('nightly', null, InputOption::VALUE_NONE, 'Download the latest develop branch of Laravel from GitHub');
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
		$this->nightly = $input->getOption('nightly') != null;
		$this->applicationName = $input->getArgument('name');

		$this->verifyApplicationDoesntExist(
			$directory = getcwd().'/'.$this->applicationName,
			$output
		);

		$output->writeln('<info>Crafting application...</info>');

		$this->download($this->getDownloadUrl(), $zipFile = $this->makeFilename())
             ->extract($zipFile, $directory)
             ->cleanUp($zipFile);

		$output->writeln('<comment>Application ready! Build something amazing.</comment>');
	}

	/**
	 * Verify that the application does not already exist.
	 *
	 * @param  string  $directory
	 * @return void
	 */
	protected function verifyApplicationDoesntExist($directory, OutputInterface $output)
	{
		if (is_dir($directory))
		{
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
		return getcwd().'/laravel_'.md5(time().uniqid()).'.zip';
	}

	/**
	 * Generate the correct url based on the nightly flag
	 *
	 * @return string
	 */
	protected function getDownloadUrl()
	{
		if ($this->nightly)
		{
			return 'https://github.com/laravel/laravel/archive/develop.zip';
		}
		return 'http://cabinet.laravel.com/latest.zip';
	}

	/**
	 * Download the temporary Zip to the given file.
	 *
	 * @param  string  $zipFile
	 * @return $this
	 */
	protected function download($downloadUrl, $zipFile)
	{
		$response = \GuzzleHttp\get($downloadUrl)->getBody();

		file_put_contents($zipFile, $response);

		return $this;
	}

	/**
	 * Extract the zip file into the given directory.
	 *
	 * @param  string  $zipFile
	 * @param  string  $directory
	 * @return $this
	 */
	protected function extract($zipFile, $directory)
	{
		$archive = new ZipArchive;

		$archive->open($zipFile);

		if ($this->nightly)
		{
			// GitHub packages the zip archive into a folder.  That folder name is the first item of the archive.
			// We will extract the archive to the current working direcory and then rename the folder to our new application name.
			$originalName = trim($archive->getNameIndex(0), '/');
			$archive->extractTo(getcwd());
			rename($originalName, $this->applicationName);
		}
		else
		{
			$archive->extractTo($directory);
		}

		$archive->close();

		return $this;
	}

	/**
	 * Clean-up the Zip file.
	 *
	 * @param  string  $zipFile
	 * @return $this
	 */
	protected function cleanUp($zipFile)
	{
		@chmod($zipFile, 0777);

		@unlink($zipFile);

		return $this;
	}

}