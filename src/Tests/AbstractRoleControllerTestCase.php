<?php

namespace Wexample\SymfonyTesting\Tests;

use Wexample\SymfonyTesting\Helper\TestControllerHelper;
use Wexample\SymfonyTesting\Traits\ControllerTestCaseTrait;
use Wexample\SymfonyTesting\Traits\RoleTestCaseTrait;

abstract class AbstractRoleControllerTestCase extends AbstractSymfonyTestCase
{
    use RoleTestCaseTrait;
    use ControllerTestCaseTrait;

    public const APPLICATION_ROLE_TEST_CLASS_PATH = '\\App\\Tests\\Application\\Role\\';

    public static function getControllerClass(): string
    {
        return TestControllerHelper::buildControllerClassPath(static::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createGlobalClient();
    }
}
