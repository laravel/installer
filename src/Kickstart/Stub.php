<?php

declare(strict_types=1);

namespace Laravel\Installer\Console\Kickstart;

use InvalidArgumentException;

use function Illuminate\Filesystem\join_paths;

class Stub
{
    /**
     * @var bool
     */
    private $teams;

    /**
     * @var 'blog' | 'podcast' | 'phone-book'
     */
    private $template;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var string
     */
    private $controllerType;

    /**
     * @var array<string, string[]>
     */
    private static array $modelNames = [
        'blog' => ['Post', 'Comment'],
        'podcast' => ['Podcast', 'Episode', 'Genre'],
        'phone-book' => ['Person', 'Business', 'Phone', 'Address'],
    ];

    public function __construct(string $template, string $controllerType, bool $hasTeams)
    {
        $this->teams = $hasTeams;
        $this->template = $template;
        $this->controllerType = $controllerType;
        $this->basePath = join_paths(dirname(__DIR__, 2), 'stubs', 'kickstart', $template);
    }

    /**
     * @return false|string
     *
     * @throws InvalidArgumentException
     */
    public function content()
    {
        return str_replace(
            '{{ controllers }}',
            $this->controllerContent(),
            file_get_contents($this->draftPath()),
        );
    }

    /**
     * @return 'none' | 'empty' | 'api' | 'web'
     *
     * @throws InvalidArgumentException
     */
    public function controllerType()
    {
        throw_unless(
            in_array($this->controllerType, ['none', 'empty', 'api', 'web']),
            InvalidArgumentException::class,
            "[{$this->controllerType}] is not a valid controller type"
        );

        return $this->controllerType;
    }

    public function displayName()
    {
        return str($this->template())->replace('-', ' ')->title()->toString();
    }

    /**
     * @return string
     */
    public function draftPath()
    {
        return $this->teams
            ? join_paths($this->basePath, 'draft-with-teams.yaml.stub')
            : join_paths($this->basePath, 'draft.yaml.stub');
    }

    /**
     * @return string[]
     *
     * @throws InvalidArgumentException
     */
    public function modelNames()
    {
        return self::$modelNames[$this->template()];
    }

    /**
     * @return string
     */
    public function seederPath()
    {
        return join_paths($this->basePath, 'Seeder.php.stub');
    }

    /**
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function template()
    {
        static $validated;

        $template = $this->template;

        if ($validated) {
            return $template;
        }

        throw_unless(
            in_array($template, ['blog', 'podcast', 'phone-book']),
            InvalidArgumentException::class,
            "[{$template}] is not listed as a valid kickstart template"
        );

        $expectedStubFiles = [
            'draft.yaml.stub',
            'draft-with-teams.yaml.stub',
            'Seeder.php.stub',
        ];

        foreach ($expectedStubFiles as $stubFile) {
            $stubPath = join_paths($this->basePath, $stubFile);

            throw_unless(
                file_exists($stubPath),
                InvalidArgumentException::class,
                "The [{$stubFile}] stub file does not exist"
            );
        }

        $validated = true;

        return $template;
    }

    /**
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function controllerContent()
    {
        if ($this->controllerType() === 'empty') {
            return $this->emptyControllersContent();
        }

        if ($this->controllerType() === 'api') {
            return $this->apiControllersContent();
        }

        if ($this->controllerType() === 'web') {
            return $this->webControllersContent();
        }

        return '';
    }

    /**
     * @return string
     */
    private function emptyControllersContent()
    {
        $result = 'controllers:'.PHP_EOL;

        foreach ($this->modelNames() as $model) {
            $result .= "  {$model}:".PHP_EOL;
            $result .= '    resource: none'.PHP_EOL;
        }

        return $result;
    }

    /**
     * @return string
     */
    private function apiControllersContent()
    {
        $result = 'controllers:'.PHP_EOL;
        foreach ($this->modelNames() as $model) {
            $pluralResource = str($model)->lower()->plural();

            $result .= "  {$model}:".PHP_EOL;
            $result .= '    resource: api'.PHP_EOL;
            $result .= '    index:'.PHP_EOL;
            $result .= "        resource: 'paginate:{$pluralResource}'".PHP_EOL;
        }

        return $result;
    }

    /**
     * @return string
     */
    private function webControllersContent()
    {
        $result = 'controllers:'.PHP_EOL;
        foreach ($this->modelNames() as $model) {
            $result .= "  {$model}:".PHP_EOL;
            $result .= '    resource'.PHP_EOL;
        }

        return $result;
    }
}
