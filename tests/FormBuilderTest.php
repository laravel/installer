<?php

namespace Laravel\Installer\Console\Tests;

use Laravel\Installer\Console\FormBuilder;
use PHPUnit\Framework\TestCase;

class FormBuilderTest extends TestCase
{
    public function test_add_with_condition(): void
    {
        $formBuilder = new FormBuilder;
        $formBuilder->addWithCondition(function () {
            return 'test';
        });
        $this->assertEquals(1, $formBuilder->getStepsCount());
    }
}
