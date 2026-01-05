<?php

namespace Wexample\SymfonyTesting\Tests\Traits;

trait GlobalHandlersSnapshotTrait
{
    private mixed $initialExceptionHandler = null;
    private mixed $initialErrorHandler = null;

    protected function snapshotGlobalHandlers(): void
    {
        $this->initialExceptionHandler = $this->getCurrentExceptionHandler();
        $this->initialErrorHandler = $this->getCurrentErrorHandler();
    }

    protected function restoreGlobalHandlers(int $maxSteps = 50): void
    {
        for ($i = 0; $i < $maxSteps; $i++) {
            $current = $this->getCurrentExceptionHandler();
            if ($current === $this->initialExceptionHandler) {
                break;
            }
            if (! restore_exception_handler()) {
                break;
            }
        }

        for ($i = 0; $i < $maxSteps; $i++) {
            $current = $this->getCurrentErrorHandler();
            if ($current === $this->initialErrorHandler) {
                break;
            }
            if (! restore_error_handler()) {
                break;
            }
        }
    }

    protected function getCurrentExceptionHandler(): mixed
    {
        $temporary = static function (): void {
        };

        $previous = set_exception_handler($temporary);
        restore_exception_handler();

        return $previous;
    }

    protected function getCurrentErrorHandler(): mixed
    {
        $temporary = static function (): void {
        };

        $previous = set_error_handler($temporary);
        restore_error_handler();

        return $previous;
    }
}
