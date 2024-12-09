<?php

namespace Laravel\Installer\Console\Tests;

use Laravel\Installer\Console\Concerns\InteractsWithHerdOrValet;
use Laravel\Installer\Console\NewCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Illuminate\Filesystem\join_paths;

class NewCommandTest extends TestCase
{
    use InteractsWithHerdOrValet;

    public function test_it_can_scaffold_a_new_laravel_app()
    {
        $scaffoldDirectoryName = 'tests-output/my-app';
        $scaffoldDirectory = __DIR__.'/../'.$scaffoldDirectoryName;

        if (file_exists($scaffoldDirectory)) {
            if (PHP_OS_FAMILY == 'Windows') {
                exec("rd /s /q \"$scaffoldDirectory\"");
            } else {
                exec("rm -rf \"$scaffoldDirectory\"");
            }
        }

        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute(['name' => $scaffoldDirectoryName], ['interactive' => false]);

        $this->assertSame(0, $statusCode);
        $this->assertDirectoryExists($scaffoldDirectory.'/vendor');
        $this->assertFileExists($scaffoldDirectory.'/.env');
    }

    public function test_it_can_chops_trailing_slash_from_name()
    {
        if ($this->runOnValetOrHerd('paths') === false) {
            $this->markTestSkipped('Require `herd` or `valet` to resolve `APP_URL` using hostname instead of "localhost".');
        }

        $scaffoldDirectoryName = 'tests-output/trailing/';
        $scaffoldDirectory = join_paths(__DIR__, '..', $scaffoldDirectoryName);

        if (file_exists($scaffoldDirectory)) {
            if (PHP_OS_FAMILY == 'Windows') {
                exec("rd /s /q \"$scaffoldDirectory\"");
            } else {
                exec("rm -rf \"$scaffoldDirectory\"");
            }
        }

        $app = new Application('Laravel Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $statusCode = $tester->execute(['name' => $scaffoldDirectoryName], ['interactive' => false]);

        $this->assertSame(0, $statusCode);
        $this->assertDirectoryExists(join_paths($scaffoldDirectory, 'vendor'));
        $this->assertFileExists(join_paths($scaffoldDirectory, '.env'));

        if ($this->isParkedOnHerdOrValet($scaffoldDirectory)) {
            $this->assertStringContainsStringIgnoringLineEndings(
                'APP_URL=http://tests-output/trailing.test',
                file_get_contents(join_paths($scaffoldDirectory, '.env'))
            );
        }
    }

    public function test_on_at_least_laravel_11()
    {
        $command = new NewCommand;

        $onLaravel10 = $command->usingLaravelVersionOrNewer(11, __DIR__.'/fixtures/laravel10');
        $onLaravel11 = $command->usingLaravelVersionOrNewer(11, __DIR__.'/fixtures/laravel11');
        $onLaravel12 = $command->usingLaravelVersionOrNewer(11, __DIR__.'/fixtures/laravel12');

        $this->assertFalse($onLaravel10);
        $this->assertTrue($onLaravel11);
        $this->assertTrue($onLaravel12);
    }
}
