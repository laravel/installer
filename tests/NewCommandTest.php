<?php

namespace Laravel\Installer\Console\Tests;

use Laravel\Installer\Console\NewCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class NewCommandTest extends TestCase
{
    protected static $scaffoldDirectoryName = 'tests-output/my-app';

    protected static $scaffoldDirectory = __DIR__.'/../';

    public static function setUpBeforeClass(): void
    {
        self::$scaffoldDirectory = __DIR__.'/../'.self::$scaffoldDirectoryName;
    }

    protected function setUp(): void
    {
        if (file_exists(self::$scaffoldDirectory)) {
            (new Filesystem)->remove(self::$scaffoldDirectory);
        }
    }

    public function test_it_can_scaffold_a_new_auth_laravel_app()
    {
        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute(['name' => self::$scaffoldDirectoryName, '--auth' => null]);

        $this->assertEquals($statusCode, 0);
        $this->assertDirectoryExists(self::$scaffoldDirectory.'/vendor');
        $this->assertFileExists(self::$scaffoldDirectory.'/.env');
        $this->assertFileExists(self::$scaffoldDirectory.'/resources/views/auth/login.blade.php');
    }

    public function test_it_can_scaffold_a_new_dev_laravel_app()
    {
        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute(['name' => self::$scaffoldDirectoryName, '--dev' => null]);

        $this->assertEquals($statusCode, 0);
        $this->assertDirectoryExists(self::$scaffoldDirectory.'/vendor');
        $this->assertFileExists(self::$scaffoldDirectory.'/.env');
        $this->assertFileNotExists(self::$scaffoldDirectory.'/resources/views/auth/login.blade.php');
    }

    public function test_it_can_scaffold_a_new_master_laravel_app()
    {
        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute(['name' => self::$scaffoldDirectoryName]);

        $this->assertEquals($statusCode, 0);
        $this->assertDirectoryExists(self::$scaffoldDirectory.'/vendor');
        $this->assertFileExists(self::$scaffoldDirectory.'/.env');
        $this->assertFileNotExists(self::$scaffoldDirectory.'/resources/views/auth/login.blade.php');
    }

    public function test_it_can_scaffold_a_new_laravel_app_on_no_ansi_option()
    {
        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute(['name' => self::$scaffoldDirectoryName, '--no-ansi' => null]);

        $this->assertEquals($statusCode, 0);
        $this->assertDirectoryExists(self::$scaffoldDirectory.'/vendor');
        $this->assertFileExists(self::$scaffoldDirectory.'/.env');
        $this->assertFileNotExists(self::$scaffoldDirectory.'/resources/views/auth/login.blade.php');
    }

    public function test_it_can_scaffold_a_new_laravel_app_on_quiet_option()
    {
        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute(['name' => self::$scaffoldDirectoryName, '--quiet' => null]);

        $this->assertEquals($statusCode, 0);
        $this->assertDirectoryExists(self::$scaffoldDirectory.'/vendor');
        $this->assertFileExists(self::$scaffoldDirectory.'/.env');
        $this->assertFileNotExists(self::$scaffoldDirectory.'/resources/views/auth/login.blade.php');
    }

    public function test_it_can_throw_runtime_exception_on_existed_laravel_app()
    {
        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $tester->execute(['name' => self::$scaffoldDirectoryName, '--auth' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Application already exists!');

        $tester->execute(['name' => self::$scaffoldDirectoryName, '--auth' => null]);
    }
}
