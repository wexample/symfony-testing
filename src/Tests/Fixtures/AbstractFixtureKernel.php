<?php

namespace Wexample\SymfonyTesting\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyTesting\Tests\TestKernel;

abstract class AbstractFixtureKernel extends TestKernel
{
    /**
     * Absolute path to the fixture "app" directory (contains config/, var/, etc.).
     */
    abstract protected function getFixtureDir(): string;

    /**
     * Additional bundles needed by the fixture app.
     */
    protected function getExtraBundles(): iterable
    {
        return [];
    }

    /**
     * Absolute paths to YAML config files to load.
     *
     * @return list<string>
     */
    protected function getConfigFiles(): array
    {
        return [];
    }

    /**
     * Absolute path to a directory containing attribute controllers, or null.
     */
    protected function getRoutesControllersDir(): ?string
    {
        return null;
    }

    public function registerBundles(): iterable
    {
        yield from parent::registerBundles();
        yield from $this->getExtraBundles();
    }

    protected function configureContainer(
        ContainerBuilder $container,
        LoaderInterface $loader
    ): void {
        parent::configureContainer($container, $loader);

        foreach ($this->getConfigFiles() as $configFile) {
            $loader->load($configFile);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $controllersDir = $this->getRoutesControllersDir();
        if ($controllersDir) {
            $routes->import($controllersDir, 'attribute');
        }
    }

    public function getProjectDir(): string
    {
        return $this->getFixtureDir();
    }

    public function getCacheDir(): string
    {
        $fixtureCacheDir = $this->getProjectDir() . '/var/cache/' . $this->environment;

        if (is_dir($fixtureCacheDir) || @mkdir($fixtureCacheDir, 0777, true)) {
            return $fixtureCacheDir;
        }

        return parent::getCacheDir();
    }
}

