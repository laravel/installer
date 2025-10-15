# NewCommand.php Refactoring Suggestions

## Executive Summary
The current `NewCommand.php` is a 1,251-line God Class that violates SOLID principles and is difficult to test. This document outlines a comprehensive refactoring strategy.

## Current Problems

### 1. Single Responsibility Principle Violations
The class handles:
- Command configuration & user interaction
- Version checking & updates
- Database configuration
- Git repository management
- GitHub integration
- Package manager detection
- Pest installation
- File system operations
- Process execution

### 2. Testability Issues
- **Direct file system calls**: `file_get_contents()`, `file_put_contents()`, `touch()`, `unlink()`
- **Static method calls**: Hard to mock curl operations, process execution
- **Global functions**: `getcwd()`, `sys_get_temp_dir()`, etc.
- **No dependency injection**: Services are created inline
- **Protected methods**: Hard to test in isolation

### 3. Code Smells
- God Class (1,251 lines)
- Feature Envy (manipulating data from other objects)
- Long methods (some 50+ lines)
- Primitive Obsession (passing arrays/strings instead of objects)

## Proposed Architecture

### Extract Services

#### 1. **VersionChecker** Service
```php
interface VersionCheckerInterface
{
    public function getLatestVersion(string $package): ?string;
    public function shouldUpdate(string $current, string $latest): bool;
}

class PackagistVersionChecker implements VersionCheckerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache
    ) {}
    
    public function getLatestVersion(string $package): ?string
    {
        // Extract lines 322-395 here
    }
}
```

**Benefits**: 
- Testable with mock HTTP client
- Cache can be injected/mocked
- Single responsibility

#### 2. **DatabaseConfigurator** Service
```php
interface DatabaseConfiguratorInterface
{
    public function configure(string $directory, string $driver, string $name): void;
    public function promptForOptions(InputInterface $input): DatabaseOptions;
}

class DatabaseConfigurator implements DatabaseConfiguratorInterface
{
    public function __construct(
        private FileManagerInterface $fileManager
    ) {}
    
    public function configure(string $directory, string $driver, string $name): void
    {
        // Extract lines 618-678
    }
}
```

**Benefits**:
- File operations are mockable through FileManagerInterface
- Easy to test different database configurations
- Reusable in other contexts

#### 3. **GitRepositoryManager** Service
```php
interface GitRepositoryManagerInterface
{
    public function initialize(string $directory, string $branch): void;
    public function commit(string $message, string $directory): void;
}

class GitRepositoryManager implements GitRepositoryManagerInterface
{
    public function __construct(
        private ProcessRunnerInterface $processRunner
    ) {}
    
    public function initialize(string $directory, string $branch): void
    {
        // Extract lines 893-905
    }
}
```

#### 4. **GitHubPublisher** Service
```php
interface GitHubPublisherInterface
{
    public function publish(string $name, string $directory, array $options): void;
    public function isAuthenticated(): bool;
}

class GitHubPublisher implements GitHubPublisherInterface
{
    // Extract lines 939-958
}
```

#### 5. **PestInstaller** Service
```php
interface TestFrameworkInstallerInterface
{
    public function install(string $directory): void;
}

class PestInstaller implements TestFrameworkInstallerInterface
{
    public function __construct(
        private ComposerInterface $composer,
        private FileManagerInterface $fileManager
    ) {}
    
    // Extract lines 830-883
}
```

#### 6. **PackageManagerDetector** Service
```php
interface PackageManagerDetectorInterface
{
    public function detect(string $directory, InputInterface $input): DetectionResult;
}

class PackageManagerDetector implements PackageManagerDetectorInterface
{
    // Extract lines 559-592
}
```

#### 7. **FileManager** Service (Abstraction Layer)
```php
interface FileManagerInterface
{
    public function exists(string $path): bool;
    public function read(string $path): string;
    public function write(string $path, string $contents): void;
    public function replace(string $path, string|array $search, string|array $replace): void;
    public function delete(string $path): void;
}

class FileManager implements FileManagerInterface
{
    // Wraps all file operations for easy mocking
}
```

#### 8. **ProcessRunner** Service
```php
interface ProcessRunnerInterface
{
    public function run(array $commands, ?string $workingPath = null, array $env = []): ProcessResult;
}

class SymfonyProcessRunner implements ProcessRunnerInterface
{
    // Extract lines 1152-1189
}
```

### Value Objects

```php
class DatabaseOptions
{
    public function __construct(
        public readonly string $driver,
        public readonly bool $migrate
    ) {}
}

class DetectionResult
{
    public function __construct(
        public readonly NodePackageManager $packageManager,
        public readonly bool $shouldRun
    ) {}
}

class ApplicationOptions
{
    public function __construct(
        public readonly string $name,
        public readonly string $directory,
        public readonly ?string $starterKit,
        public readonly ?string $version,
        // ... other options
    ) {}
    
    public static function fromInput(InputInterface $input): self
    {
        // Factory method
    }
}
```

## Refactored Command Structure

```php
class NewCommand extends Command
{
    use Concerns\ConfiguresPrompts;
    use Concerns\InteractsWithHerdOrValet;

    public function __construct(
        private VersionCheckerInterface $versionChecker,
        private DatabaseConfiguratorInterface $databaseConfigurator,
        private GitRepositoryManagerInterface $gitManager,
        private GitHubPublisherInterface $githubPublisher,
        private TestFrameworkInstallerInterface $pestInstaller,
        private PackageManagerDetectorInterface $packageManagerDetector,
        private FileManagerInterface $fileManager,
        private ProcessRunnerInterface $processRunner,
        private Composer $composer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = ApplicationOptions::fromInput($input);
        
        // Validation
        $this->validateOptions($options);
        
        // Create project
        $this->createProject($options, $output);
        
        // Configure database
        $this->databaseConfigurator->configure(
            $options->directory,
            $options->database,
            $options->name
        );
        
        // Initialize Git
        if ($options->initializeGit) {
            $this->gitManager->initialize($options->directory, $options->branch);
        }
        
        // Install testing framework
        if ($options->usePest) {
            $this->pestInstaller->install($options->directory);
        }
        
        // GitHub integration
        if ($options->publishToGitHub) {
            $this->githubPublisher->publish(
                $options->name,
                $options->directory,
                $options->githubOptions
            );
        }
        
        // Package manager
        $detection = $this->packageManagerDetector->detect(
            $options->directory,
            $input
        );
        
        // ... rest of orchestration
        
        return Command::SUCCESS;
    }
}
```

## Testing Strategy

### Unit Tests (with mocks)

```php
class NewCommandTest extends TestCase
{
    public function test_creates_laravel_application()
    {
        $versionChecker = $this->createMock(VersionCheckerInterface::class);
        $fileManager = $this->createMock(FileManagerInterface::class);
        // ... other mocks
        
        $command = new NewCommand(
            $versionChecker,
            $databaseConfigurator,
            // ... inject mocks
        );
        
        $input = new ArrayInput(['name' => 'test-app']);
        $output = new BufferedOutput();
        
        $exitCode = $command->run($input, $output);
        
        $this->assertEquals(Command::SUCCESS, $exitCode);
    }
}

class DatabaseConfiguratorTest extends TestCase
{
    public function test_configures_sqlite_database()
    {
        $fileManager = $this->createMock(FileManagerInterface::class);
        $fileManager->expects($this->once())
            ->method('replace')
            ->with(
                $this->stringContains('.env'),
                'DB_CONNECTION=.*',
                'DB_CONNECTION=sqlite'
            );
        
        $configurator = new DatabaseConfigurator($fileManager);
        $configurator->configure('/test/dir', 'sqlite', 'test-app');
    }
}

class VersionCheckerTest extends TestCase
{
    public function test_checks_for_updates_using_cache()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('get'); // Cache hit
        
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn('{"version": "5.0.0"}');
        $cache->method('isFresh')->willReturn(true);
        
        $checker = new PackagistVersionChecker($httpClient, $cache);
        
        $version = $checker->getLatestVersion('laravel/installer');
        
        $this->assertEquals('5.0.0', $version);
    }
}
```

### Integration Tests

```php
class NewCommandIntegrationTest extends TestCase
{
    use CreatesApplication;
    
    public function test_creates_real_laravel_application()
    {
        $tempDir = sys_get_temp_dir() . '/laravel-test-' . uniqid();
        
        $tester = new CommandTester($this->app->make(NewCommand::class));
        $tester->execute([
            'name' => $tempDir,
            '--no-interaction' => true,
        ]);
        
        $this->assertDirectoryExists($tempDir);
        $this->assertFileExists($tempDir . '/artisan');
        
        // Cleanup
        (new Filesystem)->deleteDirectory($tempDir);
    }
}
```

## Implementation Steps

### Phase 1: Extract Services (Low Risk)
1. Create interfaces and implementations for each service
2. Keep old methods as wrappers that delegate to new services
3. Add tests for new services
4. No breaking changes to command

### Phase 2: Inject Dependencies
1. Add constructor injection
2. Create service provider for wiring
3. Maintain backward compatibility

### Phase 3: Refactor Command
1. Move logic from command to services
2. Command becomes thin orchestrator
3. Remove deprecated wrapper methods

### Phase 4: Add Value Objects
1. Replace primitive arrays with typed objects
2. Add validation in constructors
3. Improve type safety

## Benefits of Refactoring

### Testability
- ✅ Each service has < 200 lines
- ✅ All dependencies are mockable
- ✅ Unit tests run in milliseconds (no I/O)
- ✅ Integration tests cover real workflows
- ✅ 90%+ code coverage achievable

### Maintainability
- ✅ Single Responsibility Principle
- ✅ Open/Closed Principle (extend via new services)
- ✅ Dependency Inversion (depend on interfaces)
- ✅ Easy to locate and fix bugs
- ✅ New developers onboard faster

### Extensibility
- ✅ Add new database drivers: implement interface
- ✅ Add new VCS: implement GitInterface
- ✅ Add new test frameworks: implement TestFrameworkInstallerInterface
- ✅ Replace implementations without changing command

## Quick Wins (Start Here)

### 1. Extract FileManager (2 hours)
```php
// Before
file_put_contents($file, str_replace($search, $replace, file_get_contents($file)));

// After
$this->fileManager->replace($file, $search, $replace);
```

### 2. Extract ProcessRunner (2 hours)
```php
// Before
$process = Process::fromShellCommandline(...);
$process->run();

// After
$result = $this->processRunner->run($commands, $workingPath, $env);
```

### 3. Extract VersionChecker (4 hours)
- Eliminate 70+ lines of curl code from command
- Easy to test with mock HTTP client
- Add proper error handling

### 4. Extract DatabaseConfigurator (4 hours)
- Remove 150+ lines from command
- Isolate database logic
- Test all drivers independently

## Metrics

### Before Refactoring
- **Lines of Code**: 1,251
- **Cyclomatic Complexity**: ~45
- **Dependencies**: 15+ (mixed)
- **Testability**: Low (direct I/O)
- **Test Coverage**: ~30% (estimated)

### After Refactoring
- **Lines of Code**: ~200 (command) + 800 (services)
- **Cyclomatic Complexity**: ~8 (command), ~5-10 (services)
- **Dependencies**: 9 injected interfaces
- **Testability**: High (all mockable)
- **Test Coverage**: 90%+ achievable

## Conclusion

The current `NewCommand.php` works but is a maintenance nightmare. The proposed refactoring:
- Makes the code testable through dependency injection
- Improves maintainability by separating concerns
- Enables extensibility through interfaces
- Reduces cognitive load by breaking into focused services

**Recommendation**: Start with "Quick Wins" to see immediate benefits, then proceed with full refactoring in phases to minimize risk.

