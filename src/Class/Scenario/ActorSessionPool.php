<?php

namespace Wexample\SymfonyTesting\Class\Scenario;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Several people on one browser: each actor keeps its own cookies, put back
 * when the scenario turns to it, so a session opened by one is never seen by
 * another. An actor without cookies yet is logged in on its first turn; an
 * anonymous one starts with an empty jar.
 */
final class ActorSessionPool
{
    /** @var array<string, UserInterface|null> */
    private array $users = [];

    /** @var array<string, list<\Symfony\Component\BrowserKit\Cookie>> */
    private array $jars = [];

    private ?string $current = null;

    public function __construct(
        private readonly KernelBrowser $client,
        private readonly string $firewall,
    ) {
    }

    public function register(string $alias, ?UserInterface $user): void
    {
        $this->users[$alias] = $user;
        unset($this->jars[$alias]);
    }

    public function has(string $alias): bool
    {
        return array_key_exists($alias, $this->users);
    }

    public function getUser(string $alias): ?UserInterface
    {
        return $this->users[$alias] ?? null;
    }

    public function current(): ?string
    {
        return $this->current;
    }

    public function switchTo(string $alias): void
    {
        if ($alias === $this->current) {
            return;
        }

        $jar = $this->client->getCookieJar();

        if (null !== $this->current) {
            $this->jars[$this->current] = $jar->all();
        }

        $jar->clear();

        if (isset($this->jars[$alias])) {
            foreach ($this->jars[$alias] as $cookie) {
                $jar->set($cookie);
            }
        } elseif ($user = $this->users[$alias] ?? null) {
            $this->client->loginUser($user, $this->firewall);
        }

        $this->current = $alias;
    }

    /**
     * After a change of roles or grants: the actor logs in again on its next
     * turn, its old session forgotten.
     */
    public function markDirty(string $alias): void
    {
        unset($this->jars[$alias]);

        if ($alias === $this->current) {
            $this->client->getCookieJar()->clear();
            $this->current = null;
        }
    }
}
