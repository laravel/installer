<?php

declare(strict_types=1);

namespace Laravel\Installer\Console\Services;

class FileManager implements FileManagerInterface
{
    /**
     * Check if a file or directory exists.
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Read the contents of a file.
     */
    public function read(string $path): string
    {
        return file_get_contents($path);
    }

    /**
     * Write contents to a file.
     */
    public function write(string $path, string $contents): void
    {
        file_put_contents($path, $contents);
    }

    /**
     * Replace text in a file.
     */
    public function replace(string $path, string|array $search, string|array $replace): void
    {
        $contents = $this->read($path);
        $newContents = str_replace($search, $replace, $contents);
        $this->write($path, $newContents);
    }

    /**
     * Replace text in a file using regex.
     */
    public function pregReplace(string $path, string $pattern, string $replace): void
    {
        $contents = $this->read($path);
        $newContents = preg_replace($pattern, $replace, $contents);
        $this->write($path, $newContents);
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): void
    {
        unlink($path);
    }

    /**
     * Copy a file.
     */
    public function copy(string $source, string $destination): void
    {
        copy($source, $destination);
    }
}
