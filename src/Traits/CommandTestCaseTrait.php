<?php

namespace Wexample\SymfonyTesting\Traits;

use Symfony\Component\Console\Tester\CommandTester;
use Wexample\SymfonyTesting\Traits\Application\ApplicationTestCaseTrait;

trait CommandTestCaseTrait
{
    use ApplicationTestCaseTrait;

    protected function createCommandTester(string $command): CommandTester
    {
        $application = $this->createApplication();

        if (class_exists($command)) {
            $command = $command::buildDefaultName();
        }

        $command = $application->find($command);

        return new CommandTester($command);
    }
}
