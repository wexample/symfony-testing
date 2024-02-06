<?php

namespace Wexample\SymfonyTesting\Helper;

use Exception;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Service\Syntax\ControllerSyntaxService;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;

class TestControllerHelper
{
    /**
     * Guess controller class name from test controller class name.
     *
     * @param string|null $testControllerClass
     * @throws Exception
     */
    public static function buildControllerClassPath(
        string $testControllerClass = null,
        bool $checkExists = true
    ): string {
        $testControllerClass = $testControllerClass ?: static::class;

        if (!str_starts_with($testControllerClass, ClassHelper::NAMESPACE_SEPARATOR)) {
            $testControllerClass = ClassHelper::NAMESPACE_SEPARATOR.$testControllerClass;
        }

        $testControllerClassNoSuffix = TextHelper::trimString(
            $testControllerClass,
            AbstractRoleControllerTestCase::APPLICATION_ROLE_TEST_CLASS_PATH,
            ControllerSyntaxService::SUFFIX_TEST
        );

        // Count the number of chunks to remove,
        // first separators adds one more chunk which is expected,
        // to remove the RoleName folder.
        $removeChunksLength = count(explode(ClassHelper::NAMESPACE_SEPARATOR, AbstractRoleControllerTestCase::APPLICATION_ROLE_TEST_CLASS_PATH));

        $chunks = explode(ClassHelper::NAMESPACE_SEPARATOR, $testControllerClassNoSuffix);
        $chunks = array_splice($chunks, $removeChunksLength);

        array_unshift($chunks, ClassHelper::CLASS_PATH_PART_APP);
        $controllerClass = ClassHelper::join($chunks, true);

        if ($checkExists) {
            if (!class_exists($controllerClass)) {
                dump(class_exists($testControllerClass));
                dump(is_subclass_of($testControllerClass, AbstractRoleControllerTestCase::class));
                dd($testControllerClass);
                throw new Exception('Unable to find controller class from '.$testControllerClass.', tried '.$controllerClass);
            }
        }

        return $controllerClass;
    }
}