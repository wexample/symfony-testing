<?php

namespace Wexample\SymfonyTesting\Tests\Fixtures\App\Controller;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages to walk: a sound corner, linked from a menu, and a broken one.
 */
class ExploreController extends AbstractController
{
    #[Route('/explore/sound', name: 'explore_sound')]
    public function sound(): Response
    {
        return $this->page('<nav><a href="/explore/sound/a">A</a> <a href="/explore/sound/b#top">B</a> <a href="https://example.com/out">Out</a> <a href="/_private">Private</a> <a href="/api/sound">Api</a></nav><p>Sound</p><a href="/explore/deep">Not in the menu</a>');
    }

    #[Route('/explore/sound/{page}', name: 'explore_sound_page')]
    public function soundPage(string $page): Response
    {
        return $this->page('<nav><a href="/explore/sound">Back</a></nav><p>Page ' . $page . '</p>');
    }

    #[Route('/api/sound', name: 'explore_api_sound')]
    public function apiSound(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/explore/broken', name: 'explore_broken')]
    public function broken(): Response
    {
        return $this->page('<nav><a href="/explore/crash">Crash</a> <a href="/explore/raw">Raw</a> <a href="/api/broken">Api</a></nav>');
    }

    #[Route('/explore/crash', name: 'explore_crash')]
    public function crash(): Response
    {
        throw new RuntimeException('The page fell over.');
    }

    #[Route('/explore/raw', name: 'explore_raw')]
    public function raw(): Response
    {
        return $this->page('<h1>@page::title</h1><p>WexampleSymfonyUserBundle.pages.profile::name</p>');
    }

    #[Route('/api/broken', name: 'explore_api_broken')]
    public function apiBroken(): Response
    {
        return new Response('{"ok": tru', 200, ['Content-Type' => 'application/json']);
    }

    private function page(string $body): Response
    {
        return new Response('<html><body>' . $body . '</body></html>');
    }
}
