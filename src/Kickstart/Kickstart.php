<?php

declare(strict_types=1);

namespace Laravel\Installer\Console\Kickstart;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Pluralizer;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Throwable;

use function str;

class Kickstart
{
    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var Stub
     */
    protected $stub;

    /**
     * @var Project
     */
    protected $project;

    public function __construct(
        string $projectPath,
        string $controllerType,
        string $template,
        bool $hasTeams,
        Filesystem $fs = new Filesystem()
    ) {
        $this->fs = $fs;
        $this->project = new Project($projectPath, $hasTeams);
        $this->stub = new Stub($template, $controllerType, $hasTeams);
    }

    /**
     * @return bool|int
     *
     * @throws InvalidArgumentException
     */
    public function copyDraftToProject()
    {
        return $this->fs->put(
            $this->project()->draftPath(),
            $this->stub()->content()
        );
    }

    /**
     * @return void
     *
     * @throws FileNotFoundException
     */
    public function copySeederToProject()
    {
        if (! $this->fs->exists($this->project()->draftPath())) {
            throw new RuntimeException('The draft file does not exist in project');
        }

        if (! $this->fs->exists($this->stub()->seederPath())) {
            throw new FileNotFoundException('The seeder stub does not exist');
        }

        $content = $this->fs->get($this->stub()->seederPath());

        if (! $this->fs->put($this->project()->seederPath(), $content)) {
            throw new RuntimeException('The seeder file could not be created');
        }
    }

    /**
     * @return void
     */
    public function deleteGenericSeeders()
    {
        $finder = (new Finder())
            ->files()
            ->in(dirname($this->project()->seederPath()))
            ->notName([
                'KickstartSeeder.php',
                'DatabaseSeeder.php',
            ]);

        foreach ($finder as $genericSeederFile) {
            if (false !== $path = $genericSeederFile->getRealPath()) {
                $this->fs->delete($path);
            }
        }
    }

    /**
     * @return string|null
     *
     * @throws Throwable
     */
    public function missingRequiredMigrationsMessage()
    {
        $migrationsDir = $this->project()->migrationsPath();

        [$hasUserMigration, $hasTeamMigration] = [false, ! $this->project()->hasTeams()];
        foreach (scandir($migrationsDir) ?: [] as $fileName) {
            if (str($fileName)->is('*create_users_table.php')) {
                $hasUserMigration = true;
            }

            if (str($fileName)->is('*create_teams_table.php')) {
                $hasTeamMigration = true;
            }
        }

        if ($hasUserMigration && $hasTeamMigration) {
            return null;
        }

        $missingMigrations = collect(['user' => ! $hasUserMigration, 'team' => ! $hasTeamMigration])
            ->filter()
            ->keys();

        return sprintf('%s seeder bypassed: the %s %s %s missing',
            $this->stub()->template(),
            $missingMigrations->join(' and '),
            Pluralizer::plural('migration', $missingMigrations),
            $missingMigrations->count() > 1 ? 'are' : 'is'
        );
    }

    /**
     * @return Project
     */
    public function project(): Project
    {
        return $this->project;
    }

    /**
     * @return Stub
     */
    public function stub(): Stub
    {
        return $this->stub;
    }
}
