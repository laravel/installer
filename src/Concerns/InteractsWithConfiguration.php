<?php

namespace Laravel\Installer\Console\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Laravel\Installer\Console\Repositories\ConfigRepository;

trait InteractsWithConfiguration
{
    private $allowedDefault = [
        'git',
        'branch',
        'organization',
        'jet',
        'stack',
        'teams',
        'pest',
        'force',
    ];

    /**
     * Save the default options to the laravel installer configuration file.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return void
     */
    protected function saveDefaults(InputInterface $input)
    {
        foreach ($this->allowedDefault as $key) {
            $this->config()->set($key, $input->getOption($key));
        }
    }

    /**
     * Returns an instance of the configuration repository, or a specific configuration value, if a key is passed
     *
     * @param  string|null  $key
     * @return array|int|string|\Laravel\Installer\Console\Repositories\ConfigRepository
     */
    protected function config(string $key = null)
    {
        $config = new ConfigRepository();

        return $key ? $config->get($key) : $config;
    }
}