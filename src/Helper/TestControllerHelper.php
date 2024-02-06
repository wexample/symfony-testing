<?php

namespace Wexample\SymfonyTesting\Helper;

use Exception;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Service\Syntax\ControllerSyntaxService;
use Wexample\SymfonyHelpers\Traits\BundleClassTrait;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;

class TestControllerHelper
{
    /**
     * Guess controller class name from test controller class name.
     */
    public static function buildControllerClassPath(
        string $testControllerClass,
        bool $checkExists = true
    ): string {
        if (!str_starts_with($testControllerClass, ClassHelper::NAMESPACE_SEPARATOR)) {
            $testControllerClass = ClassHelper::NAMESPACE_SEPARATOR.$testControllerClass;
        }

        // Class is related to a bundle.
        if (ClassHelper::classUsesTrait($testControllerClass, BundleClassTrait::class)) {
            /** @var $testControllerClass BundleClassTrait */
            $bundleNamespace = ClassHelper::trimLastClassChunk($testControllerClass::getBundleClassName());
            $baseNameSpace = ClassHelper::NAMESPACE_SEPARATOR
                .$bundleNamespace
                .ClassHelper::NAMESPACE_SEPARATOR.'Tests'.ClassHelper::NAMESPACE_SEPARATOR;
        } else {
            $bundleNamespace = ClassHelper::CLASS_PATH_PART_APP;
            $baseNameSpace = AbstractRoleControllerTestCase::APPLICATION_TEST_CLASS_PATH;
        }

        $testControllerClassNoSuffix = TextHelper::trimString(
            $testControllerClass,
            AbstractRoleControllerTestCase::APPLICATION_ROLE_TEST_CLASS_PATH,
            ControllerSyntaxService::SUFFIX_TEST
        );

        // Count the number of chunks to remove,
        // first separators adds one more chunk which is expected to remove the RoleName folder.
        $removeChunksLength = count(
            explode(
                ClassHelper::NAMESPACE_SEPARATOR,
                $baseNameSpace.AbstractRoleControllerTestCase::APPLICATION_ROLE_TEST_CLASS_PATH_REL
            )
        );

        $chunks = explode(ClassHelper::NAMESPACE_SEPARATOR, $testControllerClassNoSuffix);
        $chunks = array_splice($chunks, $removeChunksLength);

        array_unshift($chunks, $bundleNamespace);
        $controllerClass = ClassHelper::join($chunks, true);

        if ($checkExists) {
            if (!class_exists($controllerClass)) {
                throw new Exception('Unable to find controller class from '.$testControllerClass.', tried '.$controllerClass);
            }
        }

        return $controllerClass;
    }
}
