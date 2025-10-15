<?php

namespace Laravel\Installer\Console\Tests\Unit\Services;

use Laravel\Installer\Console\Services\DatabaseConfigurator;
use Laravel\Installer\Console\Services\FileManagerInterface;
use PHPUnit\Framework\TestCase;

class DatabaseConfiguratorTest extends TestCase
{
    public function test_configures_sqlite_database()
    {
        // Arrange
        $fileManager = $this->createMock(FileManagerInterface::class);
        
        $fileManager->expects($this->exactly(2))
            ->method('pregReplace')
            ->willReturnCallback(function ($path, $pattern, $replacement) {
                $this->assertContains($path, ['/test-app/.env', '/test-app/.env.example']);
                $this->assertEquals('/DB_CONNECTION=.*/', $pattern);
                $this->assertEquals('DB_CONNECTION=sqlite', $replacement);
            });

        $fileManager->expects($this->once())
            ->method('read')
            ->with('/test-app/.env')
            ->willReturn("DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\n");

        $fileManager->expects($this->exactly(2))
            ->method('replace')
            ->willReturnCallback(function ($path, $search, $replace) {
                $this->assertContains($path, ['/test-app/.env', '/test-app/.env.example']);
                $this->assertIsArray($search);
                $this->assertIsArray($replace);
            });

        // Act
        $configurator = new DatabaseConfigurator($fileManager);
        $configurator->configure('/test-app', 'sqlite', 'test-app');

        // Assert - expectations are verified automatically
    }

    public function test_configures_postgresql_with_custom_port()
    {
        // Arrange
        $fileManager = $this->createMock(FileManagerInterface::class);
        
        // Should update DB_CONNECTION in .env and .env.example
        $fileManager->expects($this->exactly(2))
            ->method('pregReplace');

        // Should be called for uncommenting fields, updating port, and database name
        $fileManager->expects($this->atLeastOnce())
            ->method('replace');

        // Act
        $configurator = new DatabaseConfigurator($fileManager);
        $configurator->configure('/test-app', 'pgsql', 'test-app');
        
        // Assert - expectations verified by mock
        $this->assertTrue(true);
    }

    public function test_updates_database_name_with_sanitized_app_name()
    {
        // Arrange
        $fileManager = $this->createMock(FileManagerInterface::class);
        
        // Update connection string
        $fileManager->expects($this->exactly(2))
            ->method('pregReplace');

        $replaceCalled = false;
        $fileManager->expects($this->atLeastOnce())
            ->method('replace')
            ->willReturnCallback(function ($path, $search, $replace) use (&$replaceCalled) {
                if ($search === 'DB_DATABASE=laravel' && $replace === 'DB_DATABASE=my_app_name') {
                    $replaceCalled = true;
                }
            });

        // Act
        $configurator = new DatabaseConfigurator($fileManager);
        $configurator->configure('/test-app', 'mysql', 'my-app-name');
        
        // Assert
        $this->assertTrue($replaceCalled, 'Database name should be sanitized (dashes to underscores)');
    }
}

