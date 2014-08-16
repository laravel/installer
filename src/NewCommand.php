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
				->addArgument('name', InputArgument::REQUIRED);
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
		try{
			$this->verifyApplicationDoesntExist(
				$directory = getcwd().'/'.$input->getArgument('name')
			);
		} catch (\Exception $ex) {
			$output->writeln($ex->getMessage());
			exit(1);
		}

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
	protected function verifyApplicationDoesntExist($directory)
	{
		if (is_dir($directory))
		{
			throw new \Exception('<error>Application already exists!</error>');
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
		$response = \GuzzleHttp\get('http://192.241.224.13/laravel-craft.zip')->getBody();

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