<?php

namespace Laravel\Installer\Console\Tests;

use Laravel\Installer\Console\Concerns\InteractsWithHerdOrValet;
use Laravel\Installer\Console\Enums\NodePackageManager;
use Laravel\Installer\Console\NewCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NewCommandTest extends TestCase
{
    use InteractsWithHerdOrValet;

    private const TESTS_WORKFLOW = <<<'YAML'
name: Tests
jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup Node
        uses: actions/setup-node@v4
      - name: Install Node Dependencies
        run: npm i
      - name: Install Dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader
      - name: Build Assets
        run: npm run build
YAML;

    private const LINT_WORKFLOW = <<<'YAML'
name: Lint
jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Install Dependencies
        run: |
          composer install --no-interaction --prefer-dist
          npm install
      - name: Format Frontend
        run: npm run format
      - name: Lint Frontend
        run: npm run lint
YAML;

    /**
     * @var array<int, string>
     */
    private $workflowDirectories = [];

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

    public function test_npm_keeps_workflow_files_unchanged()
    {
        $directory = $this->makeWorkflowDirectory();

        $testsContents = file_get_contents($this->testsWorkflowPath($directory));
        $lintContents = file_get_contents($this->lintWorkflowPath($directory));

        $this->workflowCommand()->configureWorkflowPackageManagerPublic($directory, NodePackageManager::NPM);

        $this->assertSame($testsContents, file_get_contents($this->testsWorkflowPath($directory)));
        $this->assertSame($lintContents, file_get_contents($this->lintWorkflowPath($directory)));
    }

    public function test_it_rewrites_workflow_commands_for_supported_package_managers()
    {
        foreach ([
            [NodePackageManager::PNPM, 'pnpm install', 'pnpm build', 'pnpm format', 'pnpm lint'],
            [NodePackageManager::YARN, 'yarn install', 'yarn build', 'yarn format', 'yarn lint'],
            [NodePackageManager::BUN, 'bun install', 'bun run build', 'bun run format', 'bun run lint'],
        ] as [$packageManager, $install, $build, $format, $lint]) {
            $directory = $this->makeWorkflowDirectory();

            $this->workflowCommand()->configureWorkflowPackageManagerPublic($directory, $packageManager);

            $testsContents = file_get_contents($this->testsWorkflowPath($directory));
            $lintContents = file_get_contents($this->lintWorkflowPath($directory));

            $this->assertStringContainsString("run: {$install}", $testsContents);
            $this->assertStringContainsString("run: {$build}", $testsContents);
            $this->assertStringContainsString($install, $lintContents);
            $this->assertStringContainsString("run: {$format}", $lintContents);
            $this->assertStringContainsString("run: {$lint}", $lintContents);
        }
    }

    public function test_it_adds_required_workflow_setup_steps()
    {
        $pnpmDirectory = $this->makeWorkflowDirectory();
        $this->workflowCommand()->configureWorkflowPackageManagerPublic($pnpmDirectory, NodePackageManager::PNPM);

        $this->assertStringContainsString('- name: Setup PNPM', file_get_contents($this->testsWorkflowPath($pnpmDirectory)));
        $this->assertStringContainsString('- name: Setup PNPM', file_get_contents($this->lintWorkflowPath($pnpmDirectory)));
        $this->assertStringContainsString('corepack enable', file_get_contents($this->testsWorkflowPath($pnpmDirectory)));

        $bunDirectory = $this->makeWorkflowDirectory();
        $this->workflowCommand()->configureWorkflowPackageManagerPublic($bunDirectory, NodePackageManager::BUN);

        $this->assertStringContainsString('uses: oven-sh/setup-bun@v2', file_get_contents($this->testsWorkflowPath($bunDirectory)));
        $this->assertStringContainsString('uses: oven-sh/setup-bun@v2', file_get_contents($this->lintWorkflowPath($bunDirectory)));
    }

    public function test_it_does_not_duplicate_setup_steps_when_run_twice()
    {
        foreach ([NodePackageManager::PNPM, NodePackageManager::BUN] as $packageManager) {
            $directory = $this->makeWorkflowDirectory();

            $command = $this->workflowCommand();
            $command->configureWorkflowPackageManagerPublic($directory, $packageManager);
            $command->configureWorkflowPackageManagerPublic($directory, $packageManager);

            $testsContents = file_get_contents($this->testsWorkflowPath($directory));
            $lintContents = file_get_contents($this->lintWorkflowPath($directory));
            $stepName = $packageManager === NodePackageManager::PNPM ? 'Setup PNPM' : 'Setup Bun';

            $this->assertSame(1, substr_count($testsContents, $stepName));
            $this->assertSame(1, substr_count($lintContents, $stepName));
        }
    }

    public function test_it_ignores_missing_workflow_files()
    {
        $directory = $this->makeWorkflowDirectory(null, null);

        $this->workflowCommand()->configureWorkflowPackageManagerPublic($directory, NodePackageManager::PNPM);

        $this->assertFileDoesNotExist($this->testsWorkflowPath($directory));
        $this->assertFileDoesNotExist($this->lintWorkflowPath($directory));
    }

    protected function tearDown(): void
    {
        foreach ($this->workflowDirectories as $directory) {
            $this->deleteDirectory($directory);
        }

        $this->workflowDirectories = [];

        parent::tearDown();
    }

    private function workflowCommand()
    {
        return new class extends NewCommand
        {
            public function configureWorkflowPackageManagerPublic(string $directory, NodePackageManager $packageManager): void
            {
                $this->configureWorkflowPackageManager($directory, $packageManager);
            }
        };
    }

    private function makeWorkflowDirectory(?string $testsWorkflow = self::TESTS_WORKFLOW, ?string $lintWorkflow = self::LINT_WORKFLOW): string
    {
        $directory = __DIR__.'/../tests-output/workflow-package-manager-'.uniqid('', true);
        $this->workflowDirectories[] = $directory;

        mkdir($directory, 0777, true);

        if ($testsWorkflow !== null || $lintWorkflow !== null) {
            mkdir($directory.'/.github/workflows', 0777, true);
        }

        if ($testsWorkflow !== null) {
            file_put_contents($this->testsWorkflowPath($directory), $testsWorkflow);
        }

        if ($lintWorkflow !== null) {
            file_put_contents($this->lintWorkflowPath($directory), $lintWorkflow);
        }

        return $directory;
    }

    private function testsWorkflowPath(string $directory): string
    {
        return $directory.'/.github/workflows/tests.yml';
    }

    private function lintWorkflowPath(string $directory): string
    {
        return $directory.'/.github/workflows/lint.yml';
    }

    private function deleteDirectory(string $directory): void
    {
        if (! file_exists($directory)) {
            return;
        }

        if (PHP_OS_FAMILY == 'Windows') {
            exec("rd /s /q \"$directory\"");
        } else {
            exec("rm -rf \"$directory\"");
        }
    }
}
