<?php

namespace Laravel\Installer\Console;

use Illuminate\Support\Traits\Conditionable;
use Laravel\Prompts\FormBuilder as BaseFormBuilder;

class FormBuilder extends BaseFormBuilder
{
    use Conditionable;

    public static function make(): self
    {
        return new self();
    }
}
