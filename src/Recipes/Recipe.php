<?php namespace Laravel\Installer\Recipe;

use Laravel\Installer\Console\NewCommand;

abstract class Recipe {

	/**
	 * The console command new.
	 *
	 * @var NewCommand
	 */
	protected $command;

	/**
	 * Create a new recipe.
	 *
	 * @param  NewCommand  $command
	 * @return Recipe
	 */
	public function __construct(NewCommand $command)
	{
		$this->command = $command;
	}

	/**
	 * Run the recipe updating $config.
	 *
	 * @param  &array  $config
	 * @return mixed
	 */
	abstract public function run(&$config);
}
