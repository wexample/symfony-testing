<?php

namespace Wexample\SymfonyTesting\Traits;

use App\Entity\User;
use Wexample\SymfonyHelpers\Helper\TextHelper;

trait LoggedUserTestCaseTrait
{
    use SessionTestCaseTrait;

    public ?User $user = null;

    public function loginUser(User $user): void
    {
        $this->log(
            'Login #'.$user->getId().' @'.$user->getUsername(),
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
        bool $forceRecreate = null,
        ?string $sessionId = null
    ): User {
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
