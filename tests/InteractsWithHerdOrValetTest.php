<?php

use Laravel\Installer\Console\Concerns\InteractsWithHerdOrValet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class InteractsWithHerdOrValetTest extends TestCase
{
    use InteractsWithHerdOrValet;

    public function test_is_parked_on_herd_or_valet_returns_false_when_output_is_not_json()
    {
        $mockProcess = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockProcess->method('isSuccessful')->willReturn(true);
        $mockProcess->method('getOutput')->willReturn('No paths have been registered.');

        $this->assertFalse($this->isParkedOnHerdOrValet('paths'));
    }
}
