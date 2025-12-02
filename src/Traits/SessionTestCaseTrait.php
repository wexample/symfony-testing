<?php

namespace Wexample\SymfonyTesting\Traits;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

trait SessionTestCaseTrait
{
    protected function saveSessionVar(
        string $name,
        mixed $value
    ): void {
        $this->getSession()->set($name, $value);
        $this->getSession()->save();
    }

    public function getSession(): Session
    {
        return self::getContainer()->get('session');
    }

    protected function createGlobalClientWithSameSession(?string $sessionId = null): void
    {
        if (! $sessionId) {
            if ($session = $this->getSession()) {
                $sessionId = $session->getId();
            }
        }

        if ($sessionId) {
            $this->logSecondary('Reusing session ' . $sessionId);
        }

        $this->createGlobalClient();

        $session = $this->getSession();

        if ($sessionId) {
            $session->setId($sessionId);
        }

        $this->clientSetCurrentSessionCookie(
            $this->client,
            $session
        );
    }

    protected function clientSetCurrentSessionCookie(
        HttpKernelBrowser $client,
        Session $session
    ): void {
        $name = $session->getName();

        $client->getCookieJar()->set(
            new Cookie(
                $name,
                $session->getId()
            )
        );
    }
}
