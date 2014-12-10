<?php
namespace Laravel\Installer\Recipe;

use Laravel\Installer\Console\NewCommand;

abstract class Recipe {

	/**
	 * @var NewCommand $command
	 */
	protected $command;

	/**
	 * Create a new recipe.
	 *
	 * @param NewCommand $command
	 */
	public function __construct(NewCommand $command)
	{
		$this->command = $command;
	}

	/**
	 * Run the recipe updating $config.
	 *
	 * @param $config
	 * @return mixed
	 */
	abstract public function run(&$config);
}
