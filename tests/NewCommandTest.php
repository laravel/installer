<?php

namespace Laravel\Installer\Console\Tests;

use Laravel\Installer\Console\Agent;
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

        $app = $this->createApplication();

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

        $app = $this->createApplication();

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

    public function test_read_log_tail_strips_ansi_and_returns_last_lines()
    {
        $path = tempnam(sys_get_temp_dir(), 'installer-tail-test-');
        file_put_contents(
            $path,
            "line one\n\e[31mline two\e[0m\nline three\nline four\n"
        );

        $tail = (new Agent)->readLogTail($path, 2);

        @unlink($path);

        $this->assertSame("line three\nline four", $tail);
    }

    public function test_read_log_tail_returns_empty_string_for_missing_file()
    {
        $this->assertSame('', (new Agent)->readLogTail('/nonexistent/path/'.uniqid()));
    }

    public function test_agent_mode_emits_single_json_line_with_failure_details()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Subprocess test is for Unix/Linux systems only.');
        }

        $bin = realpath(__DIR__.'/../bin/laravel');
        $name = 'tests-output/agent-fail-'.bin2hex(random_bytes(4));
        $dir = __DIR__.'/../'.$name;

        $cmd = sprintf(
            '%s new %s --no-boost --database=sqlite --using=does-not-exist/totally-bogus-package',
            escapeshellarg($bin),
            escapeshellarg($name)
        );

        $env = ['CLAUDECODE' => '1', 'PATH' => getenv('PATH'), 'HOME' => getenv('HOME')];

        $process = proc_open(
            $cmd,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            __DIR__.'/..',
            $env
        );

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if (file_exists($dir)) {
            exec('rm -rf '.escapeshellarg($dir));
        }

        $lines = preg_split('/\r?\n/', trim($stdout));
        $this->assertCount(1, $lines, "Expected one JSON line on stdout, got:\n{$stdout}\n---stderr---\n{$stderr}");

        $payload = json_decode($lines[0], true);
        $this->assertIsArray($payload, "Stdout was not valid JSON: {$lines[0]}");
        $this->assertFalse($payload['success']);
        $this->assertSame(basename($name), $payload['name']);
        $this->assertArrayHasKey('log', $payload);
        $this->assertArrayHasKey('tail', $payload);
        $this->assertStringContainsString('totally-bogus-package', $payload['tail']);
        $this->assertNotSame(0, $exit);

        if (isset($payload['log']) && file_exists($payload['log'])) {
            @unlink($payload['log']);
        }
    }

    private function createApplication(): Application
    {
        $app = new Application('Laravel Installer');

        if (method_exists($app, 'addCommand')) {
            $app->addCommand(new NewCommand);
        } else {
            $app->add(new NewCommand);
        }

        return $app;
    }
}
