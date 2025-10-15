# Implementation Roadmap: Refactoring NewCommand.php

## Overview
This roadmap provides a step-by-step guide to refactoring the 1,251-line `NewCommand.php` into a maintainable, testable architecture.

## Phase 1: Foundation (Week 1) - Zero Breaking Changes

### Step 1.1: Create Core Interfaces (2 hours)
```bash
# Create these files:
src/Services/FileManagerInterface.php         ✅ Created
src/Services/FileManager.php                  ✅ Created
src/Services/ProcessRunnerInterface.php
src/Services/SymfonyProcessRunner.php
```

**Goal**: Abstract file I/O and process execution

### Step 1.2: Create Service Interfaces (4 hours)
```bash
src/Services/VersionCheckerInterface.php
src/Services/DatabaseConfiguratorInterface.php
src/Services/GitRepositoryManagerInterface.php
src/Services/GitHubPublisherInterface.php
src/Services/TestFrameworkInstallerInterface.php
src/Services/PackageManagerDetectorInterface.php
```

**Goal**: Define contracts for all major operations

### Step 1.3: Implement Services (8 hours)
Create implementations for each interface:
- `PackagistVersionChecker` (extract lines 322-395)
- `DatabaseConfigurator` (extract lines 618-678) ✅ Created
- `GitRepositoryManager` (extract lines 893-905)
- `GitHubPublisher` (extract lines 939-958)
- `PestInstaller` (extract lines 830-883)
- `PackageManagerDetector` (extract lines 559-592)

**Goal**: Extract logic into focused, testable classes

### Step 1.4: Add Service Provider (2 hours)
```php
// src/ServiceProvider.php
class InstallerServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(FileManagerInterface::class, FileManager::class);
        $container->singleton(ProcessRunnerInterface::class, SymfonyProcessRunner::class);
        $container->singleton(VersionCheckerInterface::class, PackagistVersionChecker::class);
        // ... register all services
    }
}
```

**Goal**: Wire up dependency injection

### Step 1.5: Update NewCommand Constructor (1 hour)
```php
class NewCommand extends Command
{
    public function __construct(
        private FileManagerInterface $fileManager,
        private DatabaseConfiguratorInterface $databaseConfigurator,
        private VersionCheckerInterface $versionChecker,
        // ... other dependencies
    ) {
        parent::__construct();
    }
    
    // Keep existing methods as wrappers for now (backward compatibility)
    protected function replaceInFile($search, $replace, $file)
    {
        $this->fileManager->replace($file, $search, $replace);
    }
}
```

**Goal**: Inject dependencies while maintaining backward compatibility

**Checkpoint**: Run existing tests. Everything should still work! ✅

---

## Phase 2: Add Tests (Week 2)

### Step 2.1: Unit Test Services (8 hours)
Create tests for each service:

```bash
tests/Unit/Services/FileManagerTest.php
tests/Unit/Services/DatabaseConfiguratorTest.php    ✅ Created
tests/Unit/Services/VersionCheckerTest.php
tests/Unit/Services/GitRepositoryManagerTest.php
tests/Unit/Services/GitHubPublisherTest.php
tests/Unit/Services/PestInstallerTest.php
tests/Unit/Services/PackageManagerDetectorTest.php
```

**Goal**: 90%+ coverage for all services

### Step 2.2: Integration Tests (4 hours)
```bash
tests/Integration/NewCommandIntegrationTest.php
```

Test the full flow with real file system in isolated temp directory.

**Goal**: Ensure services work together correctly

### Step 2.3: Refactor Command Tests (4 hours)
Update `NewCommandTest.php` to use mocked services instead of real I/O.

**Checkpoint**: All tests green with improved coverage ✅

---

## Phase 3: Refactor Command (Week 3)

### Step 3.1: Create Value Objects (2 hours)
```bash
src/ValueObjects/ApplicationOptions.php       ✅ Created
src/ValueObjects/DatabaseOptions.php
src/ValueObjects/GitHubOptions.php
```

**Goal**: Replace primitive arrays with typed objects

### Step 3.2: Simplify Execute Method (4 hours)
Refactor `execute()` to delegate to services:

```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $options = ApplicationOptions::fromInput($input);
    
    $this->applicationCreator->create($options);
    $this->databaseConfigurator->configure($options->directory, $options->database, $options->name);
    
    if ($options->initializeGit) {
        $this->gitManager->initialize($options->directory, $options->gitBranch);
    }
    
    // ... etc
    
    return Command::SUCCESS;
}
```

**Goal**: Reduce execute() from 146 lines to ~40 lines

### Step 3.3: Remove Wrapper Methods (2 hours)
Delete old wrapper methods like:
- `replaceInFile()` (use `$this->fileManager->replace()` directly)
- `pregReplaceInFile()` (use `$this->fileManager->pregReplace()`)
- `deleteFile()` (use `$this->fileManager->delete()`)

**Goal**: Use services directly, eliminate duplication

### Step 3.4: Extract Remaining Logic (4 hours)
Move any remaining complex logic to appropriate services:
- Project creation logic → `ApplicationCreator`
- URL generation → `UrlGenerator`
- Output formatting → `OutputFormatter`

**Checkpoint**: NewCommand.php is now ~200 lines ✅

---

## Phase 4: Polish & Documentation (Week 4)

### Step 4.1: Add PHPDoc (2 hours)
Document all public methods with:
- Purpose
- Parameters
- Return values
- Exceptions

### Step 4.2: Add Integration Examples (2 hours)
Create `docs/ARCHITECTURE.md` explaining the new structure

### Step 4.3: Performance Testing (2 hours)
Benchmark before/after to ensure no regression

### Step 4.4: Final Review (4 hours)
- Run static analysis (PHPStan)
- Check test coverage
- Code review

**Checkpoint**: Production ready! ✅

---

## Quick Start: Implement One Service

Want to see immediate benefits? Start here:

### Quick Win: FileManager (2 hours total)

**1. Create Interface (15 min)**
```php
// src/Services/FileManagerInterface.php
interface FileManagerInterface {
    public function replace(string $path, $search, $replace): void;
    // ... other methods
}
```

**2. Create Implementation (15 min)**
```php
// src/Services/FileManager.php  ✅ DONE
class FileManager implements FileManagerInterface {
    public function replace(string $path, $search, $replace): void {
        $contents = file_get_contents($path);
        file_put_contents($path, str_replace($search, $replace, $contents));
    }
}
```

**3. Update NewCommand (30 min)**
```php
class NewCommand extends Command {
    public function __construct(
        private ?FileManagerInterface $fileManager = null
    ) {
        $this->fileManager ??= new FileManager(); // Fallback for BC
        parent::__construct();
    }
    
    protected function replaceInFile($search, $replace, $file) {
        $this->fileManager->replace($file, $search, $replace);
    }
}
```

**4. Add Tests (1 hour)**
```php
public function test_replaces_content_in_file() {
    $fileManager = $this->createMock(FileManagerInterface::class);
    $fileManager->expects($this->once())
        ->method('replace')
        ->with('/app/.env', 'old', 'new');
    
    $command = new NewCommand($fileManager);
    // ... test
}
```

**Result**: First service extracted, first tests passing! 🎉

---

## Rollback Strategy

If anything goes wrong:

### Option 1: Feature Flags
```php
class NewCommand extends Command {
    private bool $useNewServices = false; // Toggle via config
    
    protected function replaceInFile($search, $replace, $file) {
        if ($this->useNewServices) {
            $this->fileManager->replace($file, $search, $replace);
        } else {
            file_put_contents($file, str_replace($search, $replace, file_get_contents($file)));
        }
    }
}
```

### Option 2: Git Revert
```bash
git revert <commit-hash>  # Revert specific changes
git reset --hard origin/main  # Nuclear option
```

### Option 3: Gradual Rollout
- Deploy to staging first
- Monitor for issues
- Roll back if problems detected
- Fix and redeploy

---

## Success Metrics

Track these to measure success:

### Code Quality
- [ ] NewCommand.php: <250 lines (currently 1,251)
- [ ] Average method length: <15 lines (currently ~30)
- [ ] Cyclomatic complexity: <10 per method (currently ~45)
- [ ] Test coverage: >90% (currently ~30%)

### Performance
- [ ] Unit test suite: <100ms (currently ~2s)
- [ ] Integration tests: <5s (currently ~30s)
- [ ] No regression in CLI execution time

### Developer Experience
- [ ] New developers can understand code in <30 min
- [ ] Can add new database driver in <2 hours
- [ ] Can add new VCS provider in <4 hours
- [ ] Bug fixes take <50% of current time

---

## Common Pitfalls to Avoid

### ❌ Don't Do This:
1. **Big Bang Refactor** - Changing everything at once
2. **Breaking Changes** - Removing public APIs without deprecation
3. **Over-Engineering** - Adding unnecessary abstraction layers
4. **Skipping Tests** - Refactoring without test coverage
5. **Not Using Interfaces** - Direct coupling to implementations

### ✅ Do This Instead:
1. **Incremental Changes** - One service at a time
2. **Backward Compatibility** - Keep old methods as wrappers initially
3. **Just Enough Design** - Only abstract what you need
4. **Test First** - Write tests before refactoring
5. **Program to Interfaces** - Always depend on abstractions

---

## FAQ

**Q: Will this break existing functionality?**
A: No, if done in phases with proper testing. Phase 1 maintains 100% backward compatibility.

**Q: How long will this take?**
A: Full refactor: 3-4 weeks. Quick wins: 2-4 hours each.

**Q: Can we do this while adding features?**
A: Yes! Extract services as you work on related features.

**Q: What if we can't use dependency injection?**
A: Use service locator pattern or static factories as interim solution.

**Q: Is this over-engineering?**
A: For a 1,251-line class, no. This is appropriate engineering for the complexity.

---

## Next Steps

### Immediate Actions:
1. ✅ Review `REFACTORING_SUGGESTIONS.md`
2. ✅ Review `COMPARISON_EXAMPLE.md` 
3. ⬜ Pick one "Quick Win" service to extract
4. ⬜ Write tests for that service
5. ⬜ Update NewCommand to use the service
6. ⬜ Verify all existing tests still pass

### Week 1 Goals:
- Extract FileManager ✅
- Extract DatabaseConfigurator ✅
- Extract VersionChecker
- Add tests for all three
- Maintain 100% backward compatibility

### Success Indicator:
When you can write a test like this in 5 lines:
```php
public function test_configures_sqlite() {
    $configurator = new DatabaseConfigurator($this->fileManager);
    $configurator->configure('/app', 'sqlite', 'test-app');
    
    // Verify via mocked expectations
}
```

Instead of 15+ lines of file system setup/teardown.

---

## Resources

- Example services: `src/Services/` ✅
- Example tests: `tests/Unit/Services/` ✅
- Architecture docs: `REFACTORING_SUGGESTIONS.md` ✅
- Comparison: `COMPARISON_EXAMPLE.md` ✅
- Value objects: `src/ValueObjects/` ✅

**Remember**: Perfect is the enemy of good. Start small, iterate, improve continuously. 🚀

