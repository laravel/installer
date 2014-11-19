<?php namespace Laravel\Installer\Console;

use ZipArchive;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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
		$this->verifyApplicationDoesntExist(
			$directory = getcwd().'/'.$input->getArgument('name'),
			$output
		);

		$output->writeln('<info>Crafting application...</info>');

		$this->download($zipFile = $this->makeFilename())
			 ->extract($zipFile, $directory)
			 ->cleanUp($zipFile);

		$this->runPostCreateProjectCommands($directory, $output);

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
		$response = \GuzzleHttp\get('http://cabinet.laravel.com/latest.zip')->getBody();

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

	/**
	 * Run post-create-project-cmd from composer.json file if available.
	 * @param  string          $projectDirectory
	 * @param  OutputInterface $output
	 * @return void
	 */
	protected function runPostCreateProjectCommands($projectDirectory, OutputInterface $output)
	{
		if ( ! file_exists($projectDirectory . '/composer.json'))
		{
			return;
		}
		$composerJson = json_decode(file_get_contents($projectDirectory . '/composer.json'), true);
		if ($composerJson === null || ! isset($composerJson['scripts']['post-create-project-cmd']))
		{
			return;
		}

		$output->writeln('<info>Running post-create-project-cmd...</info>');
		foreach($composerJson['scripts']['post-create-project-cmd'] as $command) {
			$process = new Process($command, $projectDirectory);
			$process->run();

			if ( ! $process->isSuccessful())
			{
				$output->writeln('<error>' . trim($process->getErrorOutput()) . '</error>');
				continue;
			}

			$output->writeln('<info>' . trim($process->getOutput()) . '</info>');
		}
	}

}
