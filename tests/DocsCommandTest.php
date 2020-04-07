<?php

namespace Laravel\Installer\Console\Tests;

use Laravel\Installer\Console\DocsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DocsCommandTest extends TestCase
{
    public function test_it_can_open_laravel_docs()
    {
        $app = new Application('Laravel Installer');
        $app->add(new DocsCommand);

        $tester = new CommandTester($app->find('docs'));

        $statusCode = $tester->execute(['version' => 6]);

        $this->assertEquals($statusCode, 0);
        $this->assertStringContainsString('https://laravel.com/docs/6.x', $tester->getDisplay());
    }
}
