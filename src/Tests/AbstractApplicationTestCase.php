<?php

namespace Wexample\SymfonyTesting\Tests;

use Wexample\SymfonyTesting\Traits\Application\ApplicationTestCaseTrait;

abstract class AbstractApplicationTestCase extends AbstractSymfonyTestCase
{
    use ApplicationTestCaseTrait;
}
