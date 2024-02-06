<?php

namespace Wexample\SymfonyTesting\Helper;

use Exception;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyHelpers\Service\Syntax\ControllerSyntaxService;
use Wexample\SymfonyHelpers\Traits\BundleClassTrait;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;

class TestControllerHelper
{
    /**
     * @throws Exception
     */
    public static function buildClassBundleNamespace(string $classOrBundleClass): string
    {
        // Class is related to a bundle.
        if (ClassHelper::classUsesTrait($classOrBundleClass, BundleClassTrait::class)) {
            /** @var $classOrBundleClass BundleClassTrait */
            return ClassHelper::trimLastClassChunk($classOrBundleClass::getBundleClassName());
        } elseif (str_starts_with($classOrBundleClass, ClassHelper::CLASS_PATH_PART_APP)) {
            return ClassHelper::CLASS_PATH_PART_APP;
        }

        throw new Exception('Base namespace path not found for : '.$classOrBundleClass
            .'. You might need to add a BundleClassTrait to the class,'
            .' specifying to which bundle the class is from.');
    }

    /**
     * @throws Exception
     */
    public static function buildControllerRoleNamespace(string $testControllerClass): string
    {
        return ClassHelper::NAMESPACE_SEPARATOR
            .self::buildClassBundleNamespace($testControllerClass)
            .ClassHelper::NAMESPACE_SEPARATOR
            .AbstractRoleControllerTestCase::APPLICATION_TEST_CLASS_PATH_REL
            .AbstractRoleControllerTestCase::APPLICATION_ROLE_TEST_CLASS_PATH_REL;
    }

    /**
     * @throws Exception
     */
    public static function buildControllerRoleName(string $testControllerClass): string
    {
        return 'ROLE_'
            .strtoupper(
                current(
                    explode(
                        ClassHelper::NAMESPACE_SEPARATOR,
                        substr(
                            ClassHelper::NAMESPACE_SEPARATOR.$testControllerClass,
                            strlen(
                                self::buildControllerRoleNamespace($testControllerClass)
                            )
                        )
                    )
                )
            );
    }

    /**
     * Guess test controller class name from controller class name.
     * @throws Exception
     */
    public static function buildTestControllerClassPath(
        string $controllerClass,
        string $role,
        bool $checkExists = true
    ): string {
        // Ensure the controller class name starts with a namespace separator for consistency
        if (!str_starts_with($controllerClass, ClassHelper::NAMESPACE_SEPARATOR)) {
            $controllerClass = ClassHelper::NAMESPACE_SEPARATOR.$controllerClass;
        }

        $bundleNamespace = self::buildClassBundleNamespace($controllerClass);

        $testControllerClass = ClassHelper::getCousin(
            $controllerClass,
            ClassHelper::NAMESPACE_SEPARATOR.$bundleNamespace.ClassHelper::NAMESPACE_SEPARATOR,
            '',
            ClassHelper::NAMESPACE_SEPARATOR.$bundleNamespace
            .ClassHelper::NAMESPACE_SEPARATOR
            .AbstractRoleControllerTestCase::APPLICATION_TEST_CLASS_PATH_REL
            .AbstractRoleControllerTestCase::APPLICATION_ROLE_TEST_CLASS_PATH_REL
            .RoleHelper::getRoleNamePartAsClass($role)
            .ClassHelper::NAMESPACE_SEPARATOR
        );

        // Append 'Test' suffix to indicate it's a test class
        $testControllerClass .= ControllerSyntaxService::SUFFIX_TEST;

        // Optionally, check if the class exists
        if ($checkExists && !class_exists($testControllerClass)) {
            throw new Exception('Test controller class does not exist: '.$testControllerClass);
        }

        return $testControllerClass;
    }

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

        $bundleNamespace = self::buildClassBundleNamespace($testControllerClass);

        $roleNamespace = self::buildControllerRoleNamespace($testControllerClass);
        $role = current(explode(ClassHelper::NAMESPACE_SEPARATOR, substr($testControllerClass, strlen($roleNamespace))));

        $controllerClass = ClassHelper::getCousin(
            $testControllerClass,
            $roleNamespace . $role . ClassHelper::NAMESPACE_SEPARATOR,
            'Test',
            ClassHelper::NAMESPACE_SEPARATOR . $bundleNamespace . ClassHelper::NAMESPACE_SEPARATOR
        );

        if ($checkExists) {
            if (!class_exists($controllerClass)) {
                throw new Exception('Unable to find controller class from '.$testControllerClass.', tried '.$controllerClass);
            }
        }

        return $controllerClass;
    }
}
