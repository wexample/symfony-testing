<?php

namespace Wexample\SymfonyTesting\Tests\Traits;

use Wexample\SymfonyHelpers\Helper\RoleHelper;

trait AbstractAnonymousTestCaseTrait
{
    protected static function getRole(): string
    {
        return RoleHelper::ROLE_ANONYMOUS;
    }
}
