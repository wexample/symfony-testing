<?php

namespace Wexample\SymfonyTesting\Traits\Application;

use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyTesting\Traits\LoggedUserTestCaseTrait;
use Wexample\SymfonyTesting\Traits\SessionTestCaseTrait;

trait LoggedUserApplicationTestCaseTrait
{
    use LoggedUserTestCaseTrait;
    use ApplicationTestCaseTrait;
    use SessionTestCaseTrait;

    public function getUserLoginPath(): string
    {
        return $this->url($this->getUserLoginRoute());
    }

    /**
     * The route of wexample/symfony-user; override it for another login.
     */
    public function getUserLoginRoute(): string
    {
        return 'user_security_login';
    }

    public function logoutUser(): void
    {
        if (! $this->user) {
            return;
        }

        $this->log(
            'Logout @'.$this->user->getUserIdentifier()
        );

        $this->client->request(Request::METHOD_GET, $this->getUserLogoutPath());
        $this->client->followRedirects();

        // TODO ? $this->session->clear();

        $this->user = null;
    }

    public function getUserLogoutPath(): string
    {
        return $this->url($this->getUserLogoutRoute());
    }

    public function getUserLogoutRoute(): string
    {
        return 'user_security_logout';
    }
}
