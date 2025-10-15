<?php

declare(strict_types=1);

namespace Laravel\Installer\Console\ValueObjects;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Value object representing all options for creating a new Laravel application.
 *
 * This immutable object encapsulates all configuration needed to create
 * a new Laravel app, making it easier to pass around and test.
 */
final class ApplicationOptions
{
    public function __construct(
        public readonly string $name,
        public readonly string $directory,
        public readonly bool $force,
        public readonly ?string $version,
        public readonly ?string $starterKit,
        public readonly ?string $database,
        public readonly bool $initializeGit,
        public readonly string $gitBranch,
        public readonly bool $publishToGitHub,
        public readonly ?string $githubFlags,
        public readonly ?string $githubOrganization,
        public readonly bool $usePest,
        public readonly bool $usePhpunit,
        public readonly bool $installDependencies,
        public readonly ?string $packageManager,
    ) {
        
    }

    /**
     * Create ApplicationOptions from Symfony Console input.
     */
    public static function fromInput(InputInterface $input): self
    {
        $name = rtrim($input->getArgument('name'), '/\\');

        return new self(
            name: $name,
            directory: self::resolveDirectory($name),
            force: $input->getOption('force') ?? false,
            version: self::resolveVersion($input),
            starterKit: self::resolveStarterKit($input),
            database: $input->getOption('database'),
            initializeGit: $input->getOption('git') || $input->getOption('github') !== false,
            gitBranch: $input->getOption('branch') ?? 'main',
            publishToGitHub: $input->getOption('github') !== false,
            githubFlags: $input->getOption('github') ?: '--private',
            githubOrganization: $input->getOption('organization'),
            usePest: $input->getOption('pest') ?? false,
            usePhpunit: $input->getOption('phpunit') ?? false,
            installDependencies: self::shouldInstallDependencies($input),
            packageManager: self::resolvePackageManager($input),
        );
    }

    /**
     * Resolve the installation directory.
     */
    private static function resolveDirectory(string $name): string
    {
        return $name !== '.' ? getcwd().'/'.$name : '.';
    }

    /**
     * Resolve the Laravel version to install.
     */
    private static function resolveVersion(InputInterface $input): ?string
    {
        return $input->getOption('dev') ? 'dev-master' : null;
    }

    /**
     * Resolve the starter kit to use.
     */
    private static function resolveStarterKit(InputInterface $input): ?string
    {
        if ($input->getOption('using')) {
            return $input->getOption('using');
        }

        $isNoAuth = $input->getOption('no-authentication');
        $prefix = $isNoAuth ? 'laravel/blank-' : 'laravel/';
        $suffix = '-starter-kit';

        return match (true) {
            $input->getOption('react') => $prefix.'react'.$suffix,
            $input->getOption('vue') => $prefix.'vue'.$suffix,
            $input->getOption('livewire') => $prefix.'livewire'.$suffix,
            default => null,
        };
    }

    /**
     * Determine if dependencies should be installed.
     */
    private static function shouldInstallDependencies(InputInterface $input): bool
    {
        return $input->getOption('npm')
            || $input->getOption('pnpm')
            || $input->getOption('bun')
            || $input->getOption('yarn');
    }

    /**
     * Resolve which package manager to use.
     */
    private static function resolvePackageManager(InputInterface $input): ?string
    {
        return match (true) {
            $input->getOption('pnpm') => 'pnpm',
            $input->getOption('bun') => 'bun',
            $input->getOption('yarn') => 'yarn',
            $input->getOption('npm') => 'npm',
            default => null,
        };
    }

    /**
     * Check if using any starter kit.
     */
    public function isUsingStarterKit(): bool
    {
        return $this->starterKit !== null;
    }

    /**
     * Check if using a Laravel first-party starter kit.
     */
    public function isUsingLaravelStarterKit(): bool
    {
        return $this->starterKit && str_starts_with($this->starterKit, 'laravel/');
    }

    /**
     * Get the full name including organization if specified.
     */
    public function getFullName(): string
    {
        return $this->githubOrganization
            ? $this->githubOrganization.'/'.$this->name
            : $this->name;
    }
}
