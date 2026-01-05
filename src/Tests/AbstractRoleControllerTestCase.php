<?php

namespace Wexample\SymfonyTesting\Tests;

use Wexample\SymfonyTesting\Helper\TestControllerHelper;
use Wexample\SymfonyTesting\Traits\ControllerTestCaseTrait;
use Wexample\SymfonyTesting\Traits\RoleTestCaseTrait;

abstract class AbstractRoleControllerTestCase extends AbstractSymfonyTestCase
{
    use RoleTestCaseTrait;
    use ControllerTestCaseTrait;

    public const APPLICATION_TEST_CLASS_PATH = '\\App\\' . self::APPLICATION_TEST_CLASS_PATH_REL;

    public const APPLICATION_TEST_CLASS_PATH_REL = 'Tests\\';

    public const APPLICATION_ROLE_TEST_CLASS_PATH_REL = 'Application\\Role\\';

    public const APPLICATION_ROLE_TEST_CLASS_PATH =
        self::APPLICATION_TEST_CLASS_PATH.
        self::APPLICATION_ROLE_TEST_CLASS_PATH_REL;

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
