<?php

namespace Wexample\SymfonyTesting\Tests;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new WexampleSymfonyTranslationsBundle(),
        ];
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'test' => true,
            'router' => ['utf8' => true],
            'secret' => 'test',
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // No routes needed for translation tests
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/logs';
    }
}
