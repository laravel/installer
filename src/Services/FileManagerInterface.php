<?php

declare(strict_types=1);

namespace Laravel\Installer\Console\Services;

interface FileManagerInterface
{
    /**
     * Check if a file or directory exists.
     */
    public function exists(string $path): bool;

    /**
     * Read the contents of a file.
     */
    public function read(string $path): string;

    /**
     * Write contents to a file.
     */
    public function write(string $path, string $contents): void;

    /**
     * Replace text in a file.
     */
    public function replace(string $path, string|array $search, string|array $replace): void;

    /**
     * Replace text in a file using regex.
     */
    public function pregReplace(string $path, string $pattern, string $replace): void;

    /**
     * Delete a file.
     */
    public function delete(string $path): void;

    /**
     * Copy a file.
     */
    public function copy(string $source, string $destination): void;
}
