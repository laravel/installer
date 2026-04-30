<?php

namespace Laravel\Installer\Console;

use Laravel\AgentDetector\AgentDetector;
use Laravel\AgentDetector\AgentResult;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class Agent
{
    protected ?AgentResult $result;

    /**
     * The resolved installation name, captured for the JSON output.
     */
    protected ?string $name = null;

    /**
     * The resolved installation directory, captured for the JSON output.
     */
    protected ?string $directory = null;

    /**
     * Path to the subprocess log file, when one is open.
     */
    protected ?string $logPath = null;

    /**
     * Open file handle for the subprocess log, when one is open.
     *
     * @var resource|null
     */
    protected $logHandle = null;

    /**
     * Whether or not the command succeeded.
     */
    protected bool $succeeded = false;

    public function __construct()
    {
        $this->result = AgentDetector::detect();
    }

    /**
     * Determine whether the command is running under a detected agent.
     */
    public function isActive(): bool
    {
        return $this->result?->isAgent ?? false;
    }

    /**
     * The detected agent name, if any.
     */
    public function name(): ?string
    {
        return $this->result?->knownAgent()?->label();
    }

    /**
     * Capture the resolved installation details for the JSON output.
     */
    public function rememberInstallation(string $directory): void
    {
        $this->directory = $directory;
        $this->name = basename($directory === '.' ? (string) getcwd() : $directory);
    }

    /**
     * Open a log destination to capture subprocess output during agent runs.
     */
    public function openLog(): StreamOutput
    {
        [$path, $handle] = $this->resolveLogPathAndHandle();

        $this->logPath = $path;
        $this->logHandle = $handle;

        return new StreamOutput($handle, OutputInterface::VERBOSITY_NORMAL, false);
    }

    /**
     * Emit a successful agent result with optional extra payload.
     */
    public function emitSuccess(array $extra = []): void
    {
        $this->discardLog();
        $this->emit(true, $extra);
    }

    /**
     * Emit a failed agent result with optional extra payload, merged with log details.
     */
    public function emitFailure(array $extra = []): void
    {
        $this->emit(false, $extra + $this->failureDetails());
    }

    /**
     * Read the last N lines of the agent log, with ANSI escapes stripped.
     */
    public function readLogTail(string $path, int $lines = 50): string
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            return '';
        }

        return $this->formatTail($content, $lines);
    }

    /**
     * Close and remove the agent log file (used on the success path).
     */
    protected function discardLog(): void
    {
        if (is_resource($this->logHandle)) {
            @fclose($this->logHandle);
            $this->logHandle = null;
        }

        if ($this->logPath !== null) {
            @unlink($this->logPath);
            $this->logPath = null;
        }
    }

    /**
     * Build the log + tail fields for the agent failure payload.
     */
    protected function failureDetails(): array
    {
        if (! is_resource($this->logHandle)) {
            return [];
        }

        @fflush($this->logHandle);

        $details = [];

        if ($this->logPath !== null && file_exists($this->logPath)) {
            $details['log'] = $this->logPath;
            $details['tail'] = $this->readLogTail($this->logPath);
        } else {
            @rewind($this->logHandle);
            $content = stream_get_contents($this->logHandle);
            $details['tail'] = $this->formatTail($content === false ? '' : $content);
        }

        @fclose($this->logHandle);
        $this->logHandle = null;

        return $details;
    }

    /**
     * Write the minimal JSON result for agent invocations.
     */
    protected function emit(bool $success, array $extra = []): void
    {
        $payload = ['success' => $success];

        if ($this->name !== null) {
            $payload['name'] = $this->name;
        }

        if ($this->directory !== null) {
            $payload['directory'] = $this->directory;
        }

        foreach ($extra as $key => $value) {
            $payload[$key] = $value;
        }

        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /**
     * Attempt to open a temporary file for logging, falling back to an in-memory stream on failure.
     *
     * @return array{string|null, resource}
     */
    protected function resolveLogPathAndHandle(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel-installer-');
        $handle = $path !== false ? @fopen($path, 'w+') : false;

        if ($handle !== false) {
            return [$path, $handle];
        }

        if ($path !== false) {
            @unlink($path);
        }

        $handle = fopen('php://temp', 'w+');

        return [$path, $handle];
    }

    /**
     * Strip ANSI escapes and return the last N lines of the given content.
     */
    protected function formatTail(string $content, int $lines = 50): string
    {
        if ($content === '') {
            return '';
        }

        $stripped = preg_replace('/\x1b\[[0-9;?]*[a-zA-Z]/', '', $content);
        $allLines = preg_split("/\r\n|\n|\r/", rtrim($stripped ?? $content));

        return implode("\n", array_slice($allLines, -$lines));
    }
}
