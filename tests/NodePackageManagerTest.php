<?php

namespace Laravel\Installer\Console\Tests;

use Laravel\Installer\Console\Enums\NodePackageManager;
use PHPUnit\Framework\TestCase;

class NodePackageManagerTest extends TestCase
{
    public function test_isAvailable_returns_boolean()
    {
        foreach (NodePackageManager::cases() as $pm) {
            $this->assertIsBool($pm->isAvailable());
        }
    }

    public function test_detect_returns_valid_package_manager()
    {
        $detected = NodePackageManager::detect();
        $this->assertContains($detected, NodePackageManager::cases());
    }

    public function test_detect_prefers_faster_package_managers()
    {
        $detected = NodePackageManager::detect();

        if (NodePackageManager::BUN->isAvailable()) {
            $this->assertSame(NodePackageManager::BUN, $detected);
        } elseif (NodePackageManager::PNPM->isAvailable()) {
            $this->assertSame(NodePackageManager::PNPM, $detected);
        } elseif (NodePackageManager::YARN->isAvailable()) {
            $this->assertSame(NodePackageManager::YARN, $detected);
        } else {
            $this->assertSame(NodePackageManager::NPM, $detected);
        }
    }

    public function test_npm_is_typically_available()
    {
        if (! NodePackageManager::NPM->isAvailable()) {
            $this->markTestSkipped('npm is not installed.');
        }

        $this->assertTrue(NodePackageManager::NPM->isAvailable());
    }
}
