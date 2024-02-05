<?php

namespace Wexample\SymfonyTesting\Traits;

use SplFileInfo;
use Wexample\SymfonyDesignSystem\Controller\AbstractEntityController;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use function implode;
use function is_subclass_of;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * Trait LoggingTestCase
 * Various debug and logging helper methods.
 */
trait SplFileTestCaseTrait
{
    public function assertSplFileNameHasSuffix(
        SplFileInfo $file,
        array $suffixes
    ) {
        $this->assertTrue(
            $this->splFileNameHasAnySuffix($file, $suffixes),
            $file->getRealPath()
            .' has any suffix in : '.implode(', ', $suffixes)
        );
    }

    public function spfFilenameWithoutExt(SplFileInfo $file): string
    {
        return $file->getBasename(
            '.'.$file->getExtension()
        );
    }

    public function splFileNameHasAnySuffix(
        SplFileInfo $file,
        array $suffixes
    ): bool {
        $baseNameWithoutExt = $this->spfFilenameWithoutExt($file);

        foreach ($suffixes as $suffix) {
            if (str_ends_with($baseNameWithoutExt, $suffix)) {
                return true;
            }
        }

        return false;
    }

    protected function assertSplSrcFileIsSubClassOf(
        SplFileInfo $splFileInfo,
        string $classToExtend
    ) {
        $this->assertTrue(
            $this->splFileIsSubClassOf($splFileInfo, $classToExtend),
            'All controller placed in the Entity dir should extend the class '.AbstractEntityController::class
        );
    }

    protected function splFileIsSubClassOf(
        SplFileInfo $splFileInfo,
        string $classToExtend
    ): bool {
        $controllerClass = $this->buildClassNameFromSpl($splFileInfo);

        return is_subclass_of(
            $controllerClass,
            $classToExtend
        );
    }

    public function splFileTestCousin(
        SplFileInfo $file,
        string $srcFileSubDir,
        string $testFileSubDir
    ): string {
        return $this->getProjectDir().BundleHelper::DIR_TESTS
            .$testFileSubDir.substr(
                $file->getRealPath(),
                strlen($this->getProjectDir().BundleHelper::DIR_SRC.$srcFileSubDir)
            );
    }

    protected function buildClassNameFromSpl(SplFileInfo $file): string
    {
        $srcDir = $this->getSrcDir();

        $controllerClass = substr(
            $file->getRealPath(),
            strlen($srcDir),
            -4
        );

        return 'App\\'
            .str_replace(
                '/',
                '\\',
                $controllerClass
            );
    }

    public function buildRelatedEntityClassNameFromSplFile(
        SplFileInfo $fileInfo,
        string $fileSuffix = null
    ): string {
        $controllerClass = $this->buildClassNameFromSpl($fileInfo);
        $split = explode('\\', $controllerClass);
        $controllerName = end($split);

        $entityName = $fileSuffix ? substr(
            $controllerName,
            0,
            -strlen($fileSuffix)
        ) : $controllerName;

        return '\\App\\Entity\\'.$entityName;
    }
}
