<?php

namespace Wexample\SymfonyTesting\Traits\Rendering;

use Twig\Environment;

/**
 * Provides a lightweight alternative to BrowserKit for integration-style tests:
 * render Twig templates through the kernel container and expose the output via `content()`.
 */
trait TwigRenderTestCaseTrait
{
    private ?string $renderedContent = null;

    public function content(): string
    {
        return $this->renderedContent ?? '';
    }

    protected function renderTwig(string $template, array $context = []): string
    {
        if (! method_exists(static::class, 'bootKernel')) {
            throw new \LogicException(sprintf(
                '%s requires a KernelTestCase/WebTestCase compatible base class.',
                self::class
            ));
        }

        static::bootKernel();

        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        $this->renderedContent = $twig->render($template, $context);

        return $this->renderedContent;
    }

    protected function getKernelProjectDir(): string
    {
        if (! method_exists(static::class, 'getContainer')) {
            throw new \LogicException(sprintf(
                '%s requires a KernelTestCase/WebTestCase compatible base class.',
                self::class
            ));
        }

        return (string) static::getContainer()->getParameter('kernel.project_dir');
    }
}
