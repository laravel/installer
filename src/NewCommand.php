<?php namespace Laravel\Installer\Console;

use Illuminate\Console\Command;
use Laravel\Installer\Recipe\Recipe;
use Laravel\Installer\Recipe\TestFrameworkRecipe;
use Symfony\Component\Console\Input\InputArgument;
use ZipArchive;

class NewCommand extends Command {

	/**
	 * The application name
	 *
	 * @var string
	 */
	protected $appName;

	/**
	 * The application absolute path
	 *
	 * @var string
	 */
	protected $directory;

	/**
	 * The laravel version chose
	 *
	 * @var string
	 */
	protected $laravelVersion;

	/**
	 * The future content of composer.json
	 *
	 * @var array
	 */
	protected $composer;

	/**
	 * The recipes to be used
	 *
	 * @var Recipe[] $recipes
	 */
	protected $recipes;

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
	 * @return void
	 */
	public function fire()
	{
		$this->appName = $this->argument('name');

		$this->verifyApplicationDoesntExist(
			   $this->directory = getcwd() . '/' . $this->appName
		);

		$this->laravelVersion = $this->choice('What version of laravel do you use?', ['master', 'develop']);

		$this->info('Crafting application...');

		$this->download($zipFile = $this->makeFilename())
			   ->extract($zipFile)
			   ->cleanUp($zipFile);

		$this->comment('Configuration...');

		if ($this->confirm('Do you want to configure the application yourself? [yes/no]'))
		{
			$this->removeDefaultConfiguration();
			$this->initializeRecipes();
			$this->runRecipes();
			$this->loadConfiguration();
		}

		$this->comment("Run `composer create-project` in $this->directory, and enjoy building something amazing!");
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
	 * Run every recipe in $this->recipes
	 */
	protected function runRecipes()
	{
		foreach ($this->recipes as $recipe)
		{
			$recipe->run($this->composer);
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
	 * @return void
	 */
	protected function verifyApplicationDoesntExist($directory)
	{
		if (is_dir($directory))
		{
			$this->error('Application already exists!');

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
	 * Set up the recipes to be used
	 */
	protected function initializeRecipes()
	{
		$this->recipes[] = new TestFrameworkRecipe($this);
	}

}
