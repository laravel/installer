<?php

use Laravel\Installer\Console\Concerns\InteractsWithHerdOrValet;
use PHPUnit\Framework\TestCase;

class InteractsWithHerOrValetTest extends TestCase
{
    use InteractsWithHerdOrValet;
    public function test_isParkedOnHerdOrValet_returns_false_when_output_is_not_json()
    {
        $mockProcess = $this->getMockBuilder(\Symfony\Component\Process\Process::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockProcess->method('isSuccessful')->willReturn(true);
        $mockProcess->method('getOutput')->willReturn('No paths have been registered.');

        $this->assertFalse($this->isParkedOnHerdOrValet('paths'));
    }
}
