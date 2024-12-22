<?php

declare(strict_types=1);

namespace Laravel\Installer\Console\Kickstart;

use InvalidArgumentException;

use function Illuminate\Filesystem\join_paths;

class Project
{
    /**
     * @var bool
     */
    protected $hasTeams;

    /**
     * @var string
     */
    protected $basePath;

    public function __construct(string $basePath, bool $hasTeams)
    {
        $this->basePath = $basePath;
        $this->hasTeams = $hasTeams;
    }

    /**
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function basePath()
    {
        throw_unless(
            is_dir($this->basePath),
            InvalidArgumentException::class,
            "The path [{$this->basePath}] does not exist"
        );

        return $this->basePath;
    }

    /**
     * @return string
     */
    public function draftPath()
    {
        return join_paths($this->basePath, 'draft.yaml');
    }

    /**
     * @return bool
     */
    public function hasTeams()
    {
        return $this->hasTeams;
    }

    /**
     * @return string
     */
    public function migrationsPath()
    {
        return join_paths($this->basePath, 'database', 'migrations');
    }

    /**
     * @return string
     */
    public function seederPath()
    {
        return join_paths($this->basePath, 'database', 'seeders', 'KickstartSeeder.php');
    }
}
