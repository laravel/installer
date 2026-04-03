<?php

namespace Laravel\Installer\Console\Tests;

use Laravel\Installer\Console\Concerns\InteractsWithHerdOrValet;
use Laravel\Installer\Console\NewCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

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

    public function test_it_can_chop_trailing_slash_from_name()
    {
        if ($this->runOnValetOrHerd('paths') === false) {
            $this->markTestSkipped('Require `herd` or `valet` to resolve `APP_URL` using hostname instead of "localhost".');
        }

        $scaffoldDirectoryName = 'tests-output/trailing/';
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

        if ($this->isParkedOnHerdOrValet($scaffoldDirectory)) {
            $this->assertStringContainsStringIgnoringLineEndings(
                'APP_URL=http://tests-output/trailing.test',
                file_get_contents($scaffoldDirectory.'/.env')
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

    public function test_it_handles_absolute_paths_correctly()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix/Linux systems only.');
        }

        $command = new class extends NewCommand
        {
            public function getInstallationDirectoryPublic(string $name)
            {
                return $this->getInstallationDirectory($name);
            }
        };

        $absolutePath = '/tmp/my-app';
        $this->assertSame($absolutePath, $command->getInstallationDirectoryPublic($absolutePath));

        $relativePath = 'my-app';
        $this->assertSame(getcwd().'/'.$relativePath, $command->getInstallationDirectoryPublic($relativePath));

        $this->assertSame('.', $command->getInstallationDirectoryPublic('.'));
    }

    public function test_php_version_mismatch_returns_empty_when_latest_supported()
    {
        $command = new class extends NewCommand
        {
            public function handlePhpVersionMismatchPublic($input, $output, $phpVersion)
            {
                return $this->handlePhpVersionMismatch($input, $output, $phpVersion);
            }
        };

        $input = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input->method('getOption')->with('dev')->willReturn(false);

        $output = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);

        $result = $command->handlePhpVersionMismatchPublic($input, $output, '8.3.0');

        $this->assertSame('', $result);
    }

    public function test_php_version_mismatch_returns_dev_master_when_dev_flag_passed()
    {
        $command = new class extends NewCommand
        {
            public function handlePhpVersionMismatchPublic($input, $output, $phpVersion)
            {
                return $this->handlePhpVersionMismatch($input, $output, $phpVersion);
            }
        };

        $input = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input->method('getOption')->with('dev')->willReturn(true);

        $output = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);

        $result = $command->handlePhpVersionMismatchPublic($input, $output, '8.2.0');

        // It should return dev-master regardless of mismatch if --dev is provided.
        $this->assertSame('dev-master', $result);
    }

    public function test_php_version_mismatch_returns_version_when_not_interactive_and_unsupported()
    {
        $command = new class extends NewCommand
        {
            public function handlePhpVersionMismatchPublic($input, $output, $phpVersion)
            {
                return $this->handlePhpVersionMismatch($input, $output, $phpVersion);
            }
        };

        $input = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input->method('getOption')->with('dev')->willReturn(false);
        $input->method('isInteractive')->willReturn(false);

        $output = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);

        // Under non-interactive run, falling back should automatically happen if a version is supported
        $result = $command->handlePhpVersionMismatchPublic($input, $output, '8.2.0');
        
        $this->assertSame('"12.*"', $result);
    }

    public function test_php_version_mismatch_throws_exception_when_no_version_supported()
    {
        $command = new class extends NewCommand
        {
            public function handlePhpVersionMismatchPublic($input, $output, $phpVersion)
            {
                return $this->handlePhpVersionMismatch($input, $output, $phpVersion);
            }
        };

        $input = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input->method('getOption')->with('dev')->willReturn(false);
        $input->method('isInteractive')->willReturn(false);

        $output = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Installation aborted because PHP version requirements are not met.');

        // PHP 8.0 is less than 10.* requirement of 8.1
        $command->handlePhpVersionMismatchPublic($input, $output, '8.0.0');
    }
}
