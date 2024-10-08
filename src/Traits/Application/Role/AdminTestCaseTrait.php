<?php

namespace Wexample\SymfonyTesting\Traits\Application\Role;

use Wexample\SymfonyHelpers\Helper\RoleHelper;

trait AdminTestCaseTrait
{
    protected static function getRole(): string
    {
        return RoleHelper::ROLE_ADMIN;
    }
}
