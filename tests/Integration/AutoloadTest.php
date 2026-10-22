<?php

namespace Wexample\SymfonyTesting\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every class, trait and interface of the package loads without the host
 * application: nothing in `src/` may lean on an `App\…` class.
 */
class AutoloadTest extends TestCase
{
    public function testEverySourceLoads(): void
    {
        $src = dirname(__DIR__, 2) . '/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS));
        $count = 0;

        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $name = 'Wexample\\SymfonyTesting\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($src) + 1));

            $this->assertTrue(
                class_exists($name) || trait_exists($name) || interface_exists($name) || enum_exists($name),
                $name . ' does not load.'
            );
            ++$count;
        }

        $this->assertGreaterThan(30, $count);
    }
}
