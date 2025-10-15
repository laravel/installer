<?php

declare(strict_types=1);

namespace Laravel\Installer\Console\Services;

/**
 * Handles database configuration for new Laravel applications.
 * 
 * This service is responsible for:
 * - Updating .env and .env.example files with the correct database driver
 * - Commenting/uncommenting database configuration based on the driver
 * - Setting appropriate ports for different database systems
 * - Sanitizing database names
 */
class DatabaseConfigurator
{
    private const DEFAULT_PORTS = [
        'pgsql' => '5432',
        'sqlsrv' => '1433',
    ];

    private const SQLITE_COMMENTED_FIELDS = [
        'DB_HOST=127.0.0.1',
        'DB_PORT=3306',
        'DB_DATABASE=laravel',
        'DB_USERNAME=root',
        'DB_PASSWORD=',
    ];

    public function __construct(
        private FileManagerInterface $fileManager
    ) {}

    /**
     * Configure the default database connection for the application.
     *
     * @param  string  $directory  The application directory
     * @param  string  $database   The database driver (mysql, pgsql, sqlite, etc.)
     * @param  string  $name       The application name
     * @return void
     */
    public function configure(string $directory, string $database, string $name): void
    {
        $this->updateDatabaseConnection($directory, $database);

        if ($database === 'sqlite') {
            $this->configureSqlite($directory);
        } else {
            $this->configureNonSqlite($directory, $database, $name);
        }
    }

    /**
     * Update the DB_CONNECTION in .env and .env.example files.
     */
    private function updateDatabaseConnection(string $directory, string $database): void
    {
        $this->fileManager->pregReplace(
            "{$directory}/.env",
            '/DB_CONNECTION=.*/',
            "DB_CONNECTION={$database}"
        );

        $this->fileManager->pregReplace(
            "{$directory}/.env.example",
            '/DB_CONNECTION=.*/',
            "DB_CONNECTION={$database}"
        );
    }

    /**
     * Configure SQLite-specific settings by commenting out unused fields.
     */
    private function configureSqlite(string $directory): void
    {
        $environment = $this->fileManager->read("{$directory}/.env");

        // If database options aren't commented, comment them for SQLite
        if (! str_contains($environment, '# DB_HOST=127.0.0.1')) {
            $this->commentDatabaseFields($directory);
        }
    }

    /**
     * Configure non-SQLite databases by uncommenting fields and setting ports.
     */
    private function configureNonSqlite(string $directory, string $database, string $name): void
    {
        $this->uncommentDatabaseFields($directory);
        $this->updateDatabasePort($directory, $database);
        $this->updateDatabaseName($directory, $name);
    }

    /**
     * Comment database configuration fields for SQLite.
     */
    private function commentDatabaseFields(string $directory): void
    {
        $commentedFields = array_map(
            fn($field) => "# {$field}",
            self::SQLITE_COMMENTED_FIELDS
        );

        $this->fileManager->replace(
            "{$directory}/.env",
            self::SQLITE_COMMENTED_FIELDS,
            $commentedFields
        );

        $this->fileManager->replace(
            "{$directory}/.env.example",
            self::SQLITE_COMMENTED_FIELDS,
            $commentedFields
        );
    }

    /**
     * Uncomment database configuration fields for non-SQLite databases.
     */
    private function uncommentDatabaseFields(string $directory): void
    {
        $commentedFields = array_map(
            fn($field) => "# {$field}",
            self::SQLITE_COMMENTED_FIELDS
        );

        $this->fileManager->replace(
            "{$directory}/.env",
            $commentedFields,
            self::SQLITE_COMMENTED_FIELDS
        );

        $this->fileManager->replace(
            "{$directory}/.env.example",
            $commentedFields,
            self::SQLITE_COMMENTED_FIELDS
        );
    }

    /**
     * Update the database port for databases that use non-default ports.
     */
    private function updateDatabasePort(string $directory, string $database): void
    {
        if (! isset(self::DEFAULT_PORTS[$database])) {
            return;
        }

        $port = self::DEFAULT_PORTS[$database];

        $this->fileManager->replace(
            "{$directory}/.env",
            'DB_PORT=3306',
            "DB_PORT={$port}"
        );

        $this->fileManager->replace(
            "{$directory}/.env.example",
            'DB_PORT=3306',
            "DB_PORT={$port}"
        );
    }

    /**
     * Update the database name based on the application name.
     * Converts dashes to underscores and lowercases the name.
     */
    private function updateDatabaseName(string $directory, string $name): void
    {
        $sanitizedName = $this->sanitizeDatabaseName($name);

        $this->fileManager->replace(
            "{$directory}/.env",
            'DB_DATABASE=laravel',
            "DB_DATABASE={$sanitizedName}"
        );

        $this->fileManager->replace(
            "{$directory}/.env.example",
            'DB_DATABASE=laravel',
            "DB_DATABASE={$sanitizedName}"
        );
    }

    /**
     * Sanitize the application name for use as a database name.
     */
    private function sanitizeDatabaseName(string $name): string
    {
        return str_replace('-', '_', strtolower($name));
    }
}

