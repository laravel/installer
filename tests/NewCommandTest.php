<?php

namespace Laravel\Installer\Console\Tests;

use PHPUnit\Framework\TestCase;
use Laravel\Installer\Console\NewCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Tester\CommandTester;

class NewCommandTest extends TestCase
{
    public function test_it_can_scaffold_a_new_laravel_app()
    {
        $scaffoldDirectoryName = 'tests-output/my-app';
        $scaffoldDirectory = __DIR__ . '/../' . $scaffoldDirectoryName;

        if (file_exists($scaffoldDirectory)) {
            (new Filesystem)->remove($scaffoldDirectory);
        }

        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute(['name' => $scaffoldDirectoryName, '--auth' => null]);

        $this->assertEquals($statusCode, 0);
        $this->assertDirectoryExists($scaffoldDirectory . '/vendor');
        $this->assertFileExists($scaffoldDirectory . '/.env');
        $this->assertFileExists($scaffoldDirectory . '/resources/views/auth/login.blade.php');
    }

    public function test_it_can_scaffold_a_new_laravel_app_with_packages()
    {
        $scaffoldDirectoryName = 'tests-output/my-app';
        $scaffoldDirectory = __DIR__ . '/../' . $scaffoldDirectoryName;

        if (file_exists($scaffoldDirectory)) {
            (new Filesystem)->remove($scaffoldDirectory);
        }

        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute(['name' => $scaffoldDirectoryName, '--auth' => null, '--with' => 'telescope,horizon']);

        $this->assertEquals($statusCode, 0);
        $this->assertFileExists($scaffoldDirectory . '/config/telescope.php');
        $this->assertFileExists($scaffoldDirectory . '/config/horizon.php');
    }
}
