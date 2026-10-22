<?php

namespace Wexample\SymfonyTesting\Class\Scenario;

/**
 * One actor of a fixtures file: an alias the scenario speaks of, and the
 * account behind it — none for the anonymous visitor.
 */
final readonly class ActorDefinition
{
    /**
     * @param string|null $user  the identifier handed to the actor provider; null for an anonymous visitor
     * @param list<string> $roles
     * @param array<string, mixed> $with free values for the provider: a locale, a name
     */
    public function __construct(
        public string $alias,
        public ?string $user = null,
        public array $roles = [],
        public array $with = [],
    ) {
    }

    public function isAnonymous(): bool
    {
        return null === $this->user;
    }
}
