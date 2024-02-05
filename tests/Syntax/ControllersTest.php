<?php

namespace Wexample\SymfonyTesting\Tests\Syntax;

use Wexample\SymfonyTesting\Traits\ControllerSyntaxTestCaseTrait;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyTesting\Tests\AbstractApplicationTestCase;

class ControllersTest extends AbstractApplicationTestCase
{
    use ControllerSyntaxTestCaseTrait;

    public function testApiControllers()
    {
        $this->scanControllerFolder(
            'Api/Controller'
        );
    }

    public function testControllers()
    {
        $this->scanControllerFolder(
            'Controller'
        );
    }

    public function testTemplates()
    {
        $this->scanControllerPagesTemplates(
            BundleHelper::DIR_TEMPLATE_PAGES,
            $this->getProjectDir()
            .'front/',
            'src/Controllers'
        );
    }
}
