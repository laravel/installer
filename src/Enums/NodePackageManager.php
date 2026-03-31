<?php

namespace Laravel\Installer\Console\Enums;

enum NodePackageManager: string
{
    case NPM = 'npm';
    case YARN = 'yarn';
    case PNPM = 'pnpm';
    case BUN = 'bun';

    public function installCommand(): string
    {
        return match ($this) {
            self::NPM => 'npm install --ignore-scripts',
            self::YARN => 'yarn install --ignore-scripts',
            self::PNPM => 'pnpm install --ignore-scripts',
            self::BUN => 'bun install --ignore-scripts',
        };
    }

    public function runCommand(): string
    {
        return match ($this) {
            self::NPM => 'npm run',
            self::YARN => 'yarn',
            self::PNPM => 'pnpm',
            self::BUN => 'bun run',
        };
    }

    public function buildCommand(): string
    {
        return $this->runCommand().' build';
    }

    public function runLocalOrRemoteCommand(): string
    {
        return match ($this) {
            self::NPM => 'npx',
            self::YARN => 'npx',
            self::PNPM => 'pnpm dlx',
            self::BUN => 'bunx',
        };
    }

    public static function allLockFiles(): array
    {
        return ['package-lock.json', 'yarn.lock', 'pnpm-lock.yaml', 'bun.lock', 'bun.lockb'];
    }

    public function lockFiles(): array
    {
        return match ($this) {
            self::NPM => ['package-lock.json'],
            self::YARN => ['yarn.lock'],
            self::PNPM => ['pnpm-lock.yaml'],
            self::BUN => ['bun.lock', 'bun.lockb'],
        };
    }
}
