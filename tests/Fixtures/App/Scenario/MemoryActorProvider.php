<?php

namespace Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario;

use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyTesting\Class\Scenario\ActorDefinition;
use Wexample\SymfonyTesting\Class\Scenario\ScenarioContext;
use Wexample\SymfonyTesting\Interface\TestActorProviderInterface;

/**
 * The accounts of the fixture application are written in its configuration:
 * the provider only looks them up.
 */
final class MemoryActorProvider implements TestActorProviderInterface
{
    public function provide(ActorDefinition $actor, ScenarioContext $context): UserInterface
    {
        return $context->getContainer()->get('security.user.provider.concrete.memory')->loadUserByIdentifier($actor->user);
    }

    public function release(UserInterface $user, ActorDefinition $actor, ScenarioContext $context): void
    {
    }
}
