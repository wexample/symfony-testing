<?php

namespace Wexample\SymfonyTesting\Tests;

use App\Kernel;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyHelpers\Service\Entity\EntityNeutralService;
use Wexample\SymfonyHelpers\Service\Syntax\ControllerSyntaxService;
use Wexample\SymfonyHelpers\Service\Syntax\RoleSyntaxService;
use Wexample\SymfonyHelpers\WexampleSymfonyHelpersBundle;

class TestKernel extends SymfonyKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new TwigBundle(),
            // Every application the bundles ship into has a firewall, and some
            // of them read it — a menu drawing only the links the reader may
            // follow asks `security.access_map`. A fixture without the bundle
            // would fail to compile where no application ever does.
            new SecurityBundle(),
            new WexampleSymfonyHelpersBundle(),
        ];
    }

    protected function configureContainer(
        ContainerBuilder $container,
        LoaderInterface $loader
    ): void {
        $container->setAlias(Kernel::class, self::class);

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
                // Doctrine proxies need them since symfony/var-exporter 8
                // dropped the LazyGhost implementation.
                'enable_native_lazy_objects' => true,
                'auto_generate_proxy_classes' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping' => true,
            ],
        ]);

        $container->loadFromExtension('twig', [
            'debug' => true,
            'strict_variables' => true,
        ]);

        // SecurityBundle owns security.role_hierarchy.roles, so the hierarchy
        // is declared here rather than set as a parameter it would overwrite.
        $container->loadFromExtension('security', [
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
                'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
            ],
        ]);

        if (! $this->configuresItsOwnSecurity()) {
            // A firewall is required for the extension to load at all. This one
            // protects nothing: it exists so the services a bundle reads —
            // `security.access_map` and the decision manager — are there.
            $container->loadFromExtension('security', [
                'providers' => [
                    'fixture' => ['memory' => null],
                ],
                'firewalls' => [
                    'main' => ['security' => false],
                ],
            ]);
        }

        $container->register(BundleService::class, BundleService::class)
            ->setArguments([new Reference('kernel')])
            ->setPublic(true);

        $container->register(EntityNeutralService::class, EntityNeutralService::class)
            ->setArguments([new Reference('doctrine.orm.entity_manager')])
            ->setPublic(true);

        $container->register(ControllerSyntaxService::class, ControllerSyntaxService::class)
            ->setArguments([new Reference('twig')])
            ->setPublic(true);

        $container->register(RoleSyntaxService::class, RoleSyntaxService::class)
            ->setArguments([
                new Reference('parameter_bag'),
                new Reference(ControllerSyntaxService::class),
                new Reference('kernel'),
            ])
            ->setPublic(true);
    }

    /**
     * Whether the fixture declares its own `security` providers and firewall.
     *
     * A fixture exercising authentication does, and the unprotected default
     * above would collide with its own `main`.
     */
    protected function configuresItsOwnSecurity(): bool
    {
        return false;
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
