<?php

namespace Wexample\SymfonyTesting\Tests;

abstract class AbstractSymfonyKernelTestCase extends AbstractSymfonyTestCase
{
    private $initialErrorHandler;
    private $initialExceptionHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialErrorHandler = set_error_handler(static fn() => null);
        restore_error_handler();
        $this->initialExceptionHandler = set_exception_handler(static fn() => null);
        restore_exception_handler();
    }

    protected function tearDown(): void
    {
        if ($this->initialErrorHandler !== get_error_handler()) {
            set_error_handler($this->initialErrorHandler);
        }
        if ($this->initialExceptionHandler !== get_exception_handler()) {
            set_exception_handler($this->initialExceptionHandler);
        }
        parent::tearDown();
    }
}
