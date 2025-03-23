<?php

namespace Wexample\SymfonyTesting\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyHelpers\Service\Entity\EntityNeutralService;
use Wexample\SymfonyHelpers\WexampleSymfonyHelpersBundle;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new WexampleSymfonyHelpersBundle(),
            new WexampleSymfonyTranslationsBundle(),
        ];
    }

    protected function configureContainer(
        ContainerBuilder $container,
        LoaderInterface $loader
    ): void
    {
        $container->loadFromExtension('framework', [
            'test' => true,
            'router' => ['utf8' => true],
            'secret' => 'test',
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping' => true,
            ],
        ]);

        $container->register(BundleService::class, BundleService::class)
            ->setArguments(['@kernel'])
            ->setPublic(true);

        $container->register(EntityNeutralService::class, EntityNeutralService::class)
            ->setArguments(['@doctrine.orm.entity_manager'])
            ->setPublic(true);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // No routes needed for translation tests
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/logs';
    }
}
