<?php

namespace Wexample\SymfonyTesting\Tests\Fixtures\App;

use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;

/**
 * A small application with three members, an administrator and notes: what
 * the scenario engine is tried on.
 */
class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getConfigFiles(): array
    {
        return [__DIR__ . '/config/config.yaml'];
    }

    protected function getRoutesControllersDir(): ?string
    {
        return __DIR__ . '/Controller';
    }

    protected function configuresItsOwnSecurity(): bool
    {
        return true;
    }
}
