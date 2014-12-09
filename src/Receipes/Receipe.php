<?php
namespace Laravel\Installer\Receipe;

use Laravel\Installer\Console\NewCommand;

abstract class Receipe {

	/**
	 * @var NewCommand $command
	 */
	protected $command;

	/**
	 * @param NewCommand $command
	 */
	public function __construct(NewCommand $command)
	{
		$this->command = $command;
	}

	/**
	 * Run the receipe updating $config
	 *
	 * @param $config
	 * @return mixed
	 */
	abstract public function run(&$config);
}
