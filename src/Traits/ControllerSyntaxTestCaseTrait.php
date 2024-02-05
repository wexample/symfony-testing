<?php

namespace Wexample\SymfonyTesting\Traits;

use SplFileInfo;
use Wexample\SymfonyDesignSystem\Controller\AbstractEntityController;
use Wexample\SymfonyDesignSystem\Helper\TemplateHelper;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;
use Wexample\SymfonyHelpers\Helper\TextHelper;
use function basename;
use function class_exists;
use function explode;
use function is_dir;
use function is_file;
use function method_exists;
use function scandir;
use function str_ends_with;
use function str_starts_with;

trait ControllerSyntaxTestCaseTrait
{
    use SplFileTestCaseTrait;
    use ClassTestCaseTrait;

    protected function isSpecialFile(SplFileInfo $fileInfo): bool
    {
        // Ignore .htaccess or .gitignore.
        return '.' === $fileInfo->getBasename()[0];
    }

    public function scanControllerFolder(string $srcSubDir): void
    {
        $projectDir = $this->getProjectDir();

        $this->forEachClassFileRecursive(
            $srcSubDir,
            function(
                SplFileInfo $file
            ) use
            (
                $projectDir
            ): void {
                if ($this->isSpecialFile($file)) {
                    return;
                }

                $controllerClass = $this->buildClassNameFromSpl($file);
                $split = explode('\\', $controllerClass);

                $this->assertSplFileNameHasSuffix($file, [
                    'Controller',
                    'ControllerInterface',
                    'ControllerTrait',
                ]);

                // Controller is placed in the entity dir.
                if ('Entity' === $split[2]) {
                    if (str_starts_with($file->getBasename('.php'), 'Abstract')) {
                        return;
                    }

                    $this->assertSplSrcFileIsSubClassOf(
                        $file,
                        AbstractEntityController::class
                    );

                    $entityClassName = $this->buildRelatedEntityClassNameFromSplFile(
                        $file,
                        'Controller'
                    );

                    $entityTableized = ClassHelper::getTableizedName($entityClassName);

                    $this->assertTrue(
                        class_exists($entityClassName),
                        'Entity controller placed in the Entity folder should have a final entity name, entity not found '.$entityClassName
                    );

                    // Templates

                    $templateEntityWrongDir = $projectDir.'templates/pages/'.$entityTableized.'/';
                    $hasTemplateEntityWrongDir = is_dir($templateEntityWrongDir);
                    $hasAViewOrEditTemplate = is_file($templateEntityWrongDir.'view.html.twig')
                        || is_file($templateEntityWrongDir.'edit.html.twig');

                    $this->assertFalse(
                        $hasTemplateEntityWrongDir && $hasAViewOrEditTemplate,
                        'The entity dir should not be in '
                        .$templateEntityWrongDir
                        .' or it should not contains no view.html.twig or edit.html.twig'
                    );
                } else {
                    $this->assertNotEquals(
                        'Entity',
                        $split[2],
                        'All non-entity controller should be placed into the Controller\\Entity\\ folder : '.$controllerClass
                    );
                }
            }
        );
    }

    protected function scanControllerPagesTemplates(
        string $templatesRelDir,
        string $templatesDir,
        string $classSrcDir,
    ): void {
        $scan = scandir($templatesDir.$templatesRelDir);

        foreach ($scan as $item) {
            if ('.' !== $item[0]) {
                $realSubPath = $templatesDir.$templatesRelDir.$item;

                if (is_dir($realSubPath)) {
                    $this->scanControllerPagesTemplates(
                        $templatesRelDir.$item.'/',
                        $templatesDir,
                        $classSrcDir
                    );
                } elseif (str_ends_with(
                    $item,
                    TemplateHelper::TEMPLATE_FILE_EXTENSION
                )) {
                    $controllerClassName = ClassHelper::buildClassNameFromPath(
                        $templatesRelDir,
                        '\\App\\Controller\\',
                        'Controller'
                    );

                    $this->assertTrue(
                        class_exists(
                            $controllerClassName,
                        ),
                        'The controller class '.$controllerClassName.' exists for template '.$realSubPath
                    );

                    $methodName = TextHelper::toCamel(
                        FileHelper::removeExtension(
                            basename($realSubPath),
                            TemplateHelper::TEMPLATE_FILE_EXTENSION
                        )
                    );

                    $this->assertTrue(
                        method_exists(
                            $controllerClassName,
                            $methodName
                        ),
                        'The method exists in controller : '
                        .$controllerClassName.ClassHelper::METHOD_SEPARATOR.$methodName
                    );
                }
            }
        }
    }
}
