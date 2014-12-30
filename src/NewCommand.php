<?php namespace Laravel\Installer\Console;

use ZipArchive;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NewCommand extends \Symfony\Component\Console\Command\Command {


	protected $input;

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
				->addOption(
               		'dev',
               		null,
               		InputOption::VALUE_NONE,
               		'If set, the installer will install Laravel 5 instead of 4.'
           		);
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
		$this->input = $input;

		$this->verifyApplicationDoesntExist(
			$directory = getcwd().'/'.$input->getArgument('name'),
			$output
		);

		$output->writeln('<info>Crafting application...</info>');

		$this->download($zipFile = $this->makeFilename())
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
	 * Download the temporary Zip to the given file.
	 *
	 * @param  string  $zipFile
	 * @return $this
	 */
	protected function download($zipFile)
	{

		$response = \GuzzleHttp\get($this->determineDownload())->getBody();

		file_put_contents($zipFile, $response);

		return $this;
	}

	/**
	 * Determine which file to download, 4 or 5 
	 * 
	 * @return string
	 */

	protected function determineDownload()
	{
		if ($this->input->getOption('dev'))
			return 'http://rweas.github.io/installer/laravel-develop.zip';
		return 'http://cabinet.laravel.com/latest.zip';
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

		$archive->extractTo($directory);

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