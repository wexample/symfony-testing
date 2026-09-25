<?php

namespace Wexample\SymfonyTesting\Traits;

use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\Helpers\Helper\TextHelper;

/**
 * Typed on the Symfony user, so it serves any application user class — an
 * AbstractUser of wexample/symfony-user or not.
 */
trait LoggedUserTestCaseTrait
{
    use SessionTestCaseTrait;

    public ?UserInterface $user = null;

    /**
     * The application knows its own user class: the test creates it.
     */
    abstract public function createAndSaveUserIfNotExists(
        string $username,
        array|string $roles = [],
        ?bool $forceRecreate = null
    ): UserInterface;

    public function loginUser(UserInterface $user): void
    {
        $this->log(
            'Login @'.$user->getUserIdentifier(),
            TextHelper::ASCII_COLOR_YELLOW
        );

        $this->client->loginUser(
            $user
        );

        $this->user = $user;
    }

    public function initUserLogged(
        string $username = self::USER_USERNAME,
        array|string $roles = [],
        ?bool $forceRecreate = null,
        ?string $sessionId = null
    ): UserInterface {
        // Nullify current user if exists.
        // It allows keeping user record in database and not destroy it.
        $this->user = null;

        $this->createGlobalClientWithSameSession($sessionId);

        $this->logIndentUp();

        $user = $this->createAndSaveUserIfNotExists(
            $username,
            $roles,
            $forceRecreate
        );

        $this->loginUser(
            $user
        );

        $this->logIndentDown();

        return $this->user;
    }
}
