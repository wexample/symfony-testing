<?php

namespace Wexample\SymfonyTesting\Interface;

use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyTesting\Class\Scenario\ActorDefinition;
use Wexample\SymfonyTesting\Class\Scenario\ScenarioContext;

/**
 * The application knows its user class: it hands the scenario the account an
 * actor of the fixtures file stands for — found, or created for the run.
 */
interface TestActorProviderInterface
{
    public function provide(ActorDefinition $actor, ScenarioContext $context): UserInterface;

    /**
     * Once the scenario is over: remove what `provide()` created for it, leave
     * what was there before.
     */
    public function release(UserInterface $user, ActorDefinition $actor, ScenarioContext $context): void;
}
