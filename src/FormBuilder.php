<?php

namespace Laravel\Installer\Console;

use Closure;

class FormBuilder extends \Laravel\Prompts\FormBuilder
{
    public function addWithCondition(Closure $step, ?string $name = null, bool $ignoreWhenReverting = false, bool|Closure $condition = true): self
    {
        $this->steps[] = new \Laravel\Prompts\FormStep($step, $condition, $name, $ignoreWhenReverting);

        return $this;
    }

    public function getStepsCount(): int
    {
        return count($this->steps);
    }
}
