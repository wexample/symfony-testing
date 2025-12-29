<?php

namespace Wexample\SymfonyTesting\Tests\Traits;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

trait RunInSeparateProcessTestTrait
{
    #[Test]
    #[RunInSeparateProcess]
    final public function runInSeparateProcessTest(): void
    {
        $this->runIsolatedTest();
    }

    abstract protected function runIsolatedTest(): void;
}

