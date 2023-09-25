<?php

namespace Wexample\SymfonyTesting\Tests;

abstract class AbstractRoleTestCase extends AbstractSymfonyTestCase
{
    public static function getRoleTestClassBasePath(): string
    {
        return '\\App\\Tests\\Application\\Role\\';
    }

    abstract protected static function getRole(): string;
}