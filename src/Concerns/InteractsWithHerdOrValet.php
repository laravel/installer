<?php

namespace Laravel\Installer\Console\Concerns;

use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

trait InteractsWithHerdOrValet
{
    /**
     * Determine if the given directory is parked using Herd or Valet.
     *
     * @param  string  $directory
     * @return bool
     */
    public function isParkedOnHerdOrValet(string $directory): bool
    {
        $output = $this->runOnValetOrHerd('paths');

        $decodedOutput = json_decode($output);

        return $decodedOutput !== null && in_array(dirname($directory), $decodedOutput);
    }

    /**
     * Runs the given command on the "herd" or "valet" CLI.
     *
     * @param  string  $command
     * @return string|false
     */
    protected function runOnValetOrHerd(string $command): false|string
    {
        foreach (['herd', 'valet'] as $tool) {
            $process = new Process([$tool, $command, '-v']);

            try {
                $process->run();

                if ($process->isSuccessful()) {
                    return trim($process->getOutput());
                }
            } catch (ProcessStartFailedException) {
            }
        }

        return false;
    }
}
