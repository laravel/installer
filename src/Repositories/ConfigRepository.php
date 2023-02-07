<?php

namespace Laravel\Installer\Console\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class ConfigRepository
{
    /**
     * The path to the configuration file.
     *
     * @var string
     */
    protected $path;

    /**
     * Creates a new repository instance.
     *
     * @param string $path
     * @return void
     */
    public function __construct()
    {
        $this->path =  $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'];

        $this->path .= '/.laravel-installer/config.json';
    }

    /**
     * Flush the configuration.
     *
     * @return $this
     */
    public function flush(): self
    {
        File::delete($this->path);

        return $this;
    }

    /**
     * Get the given configuration value.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return array|int|string
     */
    public function get(string $key, $default = null)
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * Get all of the configuration items for the application.
     */
    public function all(): array
    {
        if (!is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0755, true);
        }

        if (file_exists($this->path)) {
            return json_decode(file_get_contents($this->path), true);
        }

        return [];
    }

    /**
     * Set a given configuration value.
     *
     * @param string $key
     * @param array|int|string $value
     *
     * @return $this
     */
    public function set(string $key, $value): self
    {
        $config = $this->all();

        Arr::set($config, $key, $value);

        file_put_contents($this->path, json_encode($config, JSON_PRETTY_PRINT));

        return $this;
    }
}