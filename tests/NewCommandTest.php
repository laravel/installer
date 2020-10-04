<?php

namespace Laravel\Installer\Console\Tests;

use Laravel\Installer\Console\NewCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NewCommandTest extends TestCase
{
    public function test_it_can_scaffold_a_new_laravel_app()
    {
        $scaffoldDirectoryName = 'tests-output'.DIRECTORY_SEPARATOR.'my-app';
        $scaffoldDirectory = $this->prepareScaffoldDirectory($scaffoldDirectoryName);

        $this->assertApplicationIsScaffolded($scaffoldDirectory, ['name' => $scaffoldDirectoryName]);
    }

    public function test_it_can_scaffold_a_new_laravel_app_in_the_current_directory()
    {
        $scaffoldDirectoryName = 'tests-output'.DIRECTORY_SEPARATOR.'my-app';
        $scaffoldDirectory = $this->prepareScaffoldDirectory($scaffoldDirectoryName);

        // Create directory and change into it.
        if (PHP_OS_FAMILY == 'Windows') {
            exec("mkdir \"$scaffoldDirectory\"");
        } else {
            mkdir($scaffoldDirectory);
        }

        chdir($scaffoldDirectory);

        $this->assertApplicationIsScaffolded($scaffoldDirectory, []);
    }

    /**
     * Removes the scaffold test directory if existing and returns the absolute scaffold directory path.
     *
     * @param $scaffoldDirectoryName
     *
     * @return string
     */
    protected function prepareScaffoldDirectory($scaffoldDirectoryName)
    {
        $scaffoldDirectory = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.$scaffoldDirectoryName;

        if (file_exists($scaffoldDirectory)) {
            if (PHP_OS_FAMILY == 'Windows') {
                exec("rd /s /q \"$scaffoldDirectory\"");
            } else {
                exec("rm -rf \"$scaffoldDirectory\"");
            }
        }

        return $scaffoldDirectory;
    }

    /**
     * Initiates the application scaffolding in the given directory and with the specified parameters and asserts it succeeded.
     *
     * @param string $scaffoldDirectory
     * @param array  $parameters
     */
    protected function assertApplicationIsScaffolded(string $scaffoldDirectory, array $parameters): void
    {
        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute($parameters);

        $this->assertSame(0, $statusCode);
        $this->assertDirectoryExists($scaffoldDirectory.DIRECTORY_SEPARATOR.'vendor');
        $this->assertFileExists($scaffoldDirectory.DIRECTORY_SEPARATOR.'.env');
    }
}
