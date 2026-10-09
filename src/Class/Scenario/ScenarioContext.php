<?php

namespace Wexample\SymfonyTesting\Class\Scenario;

use Closure;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyTesting\Enum\AccessExpectation;
use Wexample\SymfonyTesting\Interface\TestActorProviderInterface;
use Wexample\SymfonyTesting\Interface\TestFixtureFactoryInterface;

/**
 * A scenario while it runs: its actors logged in on one browser, the data it
 * created, and the requests it makes on their behalf. Refusals met where an
 * access was expected — or the reverse — are gathered rather than thrown, so
 * one run reports every gap; a task that cannot be done stops it.
 */
final class ScenarioContext
{
    public readonly ActorSessionPool $actors;

    public readonly ReferenceBag $references;

    /** @var list<string> */
    private array $accessFailures = [];

    /** @var list<array{TestFixtureFactoryInterface, object}> */
    private array $created = [];

    /** @var array<string, TestFixtureFactoryInterface> */
    private array $factories = [];

    /** @var list<string> */
    private array $trace = [];

    /**
     * @param Closure(): ContainerInterface             $container the current one: the kernel reboots between requests
     * @param iterable<TestFixtureFactoryInterface>     $factories
     */
    public function __construct(
        public readonly KernelBrowser $client,
        public readonly ScenarioFixtures $fixtures,
        private readonly Closure $container,
        private readonly TestActorProviderInterface $actorProvider,
        iterable $factories = [],
    ) {
        $this->actors = new ActorSessionPool($client, $fixtures->firewall);
        $this->references = new ReferenceBag(
            $this->refresh(...),
            fn (string $alias): ?object => ($user = $this->actors->getUser($alias)) ? $this->refresh($user) : null,
        );

        foreach ($factories as $factory) {
            $this->factories[$factory::getName()] = $factory;
        }
    }

    public function getContainer(): ContainerInterface
    {
        return ($this->container)();
    }

    /**
     * The actors, then the data, in the order of the file.
     */
    public function setUp(): void
    {
        foreach ($this->fixtures->actors as $alias => $actor) {
            $this->actors->register(
                $alias,
                $actor->isAnonymous() ? null : $this->actorProvider->provide($actor, $this)
            );
        }

        foreach ($this->fixtures->data as $fixture) {
            $factory = $this->factories[$fixture->factory]
                ?? throw new RuntimeException('No fixture factory ' . $fixture->factory . ' for @' . $fixture->name . ', known: ' . (implode(', ', array_keys($this->factories)) ?: 'none') . '.');

            $object = $factory->create($this->references->resolve($fixture->with), $this);
            $this->created[] = [$factory, $object];
            $this->references->set($fixture->name, $object);
        }
    }

    /**
     * The data removed in the reverse order, then the actors released.
     */
    public function tearDown(): void
    {
        foreach (array_reverse($this->created) as [$factory, $object]) {
            $factory->remove($this->refresh($object), $this);
        }
        $this->created = [];

        foreach ($this->fixtures->actors as $alias => $actor) {
            if ($user = $this->actors->getUser($alias)) {
                $this->actorProvider->release($this->refresh($user), $actor, $this);
            }
        }
    }

    public function as(string $alias): self
    {
        $this->fixtures->getActor($alias);
        $this->actors->switchTo($alias);

        return $this;
    }

    public function getUser(string $alias): ?UserInterface
    {
        $user = $this->actors->getUser($alias);

        return $user ? $this->refresh($user) : null;
    }

    public function set(string $name, mixed $value): void
    {
        $this->references->set($name, $value);
    }

    public function get(string $name): mixed
    {
        return $this->references->get($name);
    }

    public function url(string $route, array $params = []): string
    {
        return $this->router()->generate($route, $this->references->resolve($params), UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Redirects are not followed: where they lead is part of the answer.
     */
    public function request(string $method, string $url, array $parameters = []): Response
    {
        $this->client->followRedirects(false);
        $this->client->request($method, $url, $parameters);
        $response = $this->client->getResponse();

        $this->trace[] = sprintf('%s %s %s → %d', $this->actors->current() ?? '-', $method, $url, $response->getStatusCode())
            . $this->describeError($response);

        return $response;
    }

    public function open(string $route, array $params = []): Response
    {
        return $this->request(Request::METHOD_GET, $this->url($route, $params));
    }

    /**
     * What a response was for the one who got it, in the words of a
     * fixtures file.
     */
    public function met(Response $response): ?AccessExpectation
    {
        return AccessExpectation::fromResponse($response->getStatusCode(), $this->isRedirectToLogin($response));
    }

    /**
     * Each actor named opens the page; the one whose turn it was gets it
     * back. Whatever differs is kept for the report.
     *
     * @param array<string, AccessExpectation> $expectations per alias
     */
    public function checkAccess(string $route, array $params, array $expectations, string $where): void
    {
        $previous = $this->actors->current();

        foreach ($expectations as $alias => $expected) {
            $this->as($alias);
            $response = $this->open($route, $params);
            $met = $this->met($response);

            if (! $expected->accepts($met)) {
                $this->accessFailures[] = sprintf(
                    '%s: %s expected %s on %s, met %s (%d)',
                    $where,
                    $alias,
                    $expected->value,
                    $route,
                    $met?->value ?? 'an error',
                    $response->getStatusCode()
                );
            }
        }

        if (null !== $previous) {
            $this->as($previous);
        }
    }

    /**
     * The form of a page holding the button, filled and sent; the answer
     * must not be an error, and is followed when it redirects. A value for a
     * field the page does not render — one a script draws, as a search
     * input — is sent along all the same.
     *
     * @param array<string, mixed> $fill field name → value, references allowed
     */
    public function submit(string $route, array $params, string $button, array $fill = []): Response
    {
        $this->open($route, $params);
        $buttons = $this->client->getCrawler()->selectButton($button);

        if (! $buttons->count()) {
            throw new RuntimeException(sprintf('No button "%s" on %s for %s.', $button, $route, $this->actors->current() ?? 'nobody'));
        }

        $form = $buttons->form();
        $extra = [];

        foreach ($this->references->resolve($fill) as $name => $value) {
            if ($form->has($name)) {
                $form[$name] = $value;
            } else {
                $extra[] = rawurlencode($name) . '=' . rawurlencode((string) $value);
            }
        }

        parse_str(implode('&', $extra), $extraValues);

        $this->client->followRedirects(true);
        $this->client->request($form->getMethod(), $form->getUri(), array_replace_recursive($form->getPhpValues(), $extraValues), $form->getPhpFiles());
        $this->client->followRedirects(false);
        $response = $this->client->getResponse();

        $this->trace[] = sprintf('%s submits "%s" on %s → %d', $this->actors->current() ?? '-', $button, $route, $response->getStatusCode())
            . $this->describeError($response);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException(sprintf('Submitting "%s" on %s answered %d.', $button, $route, $response->getStatusCode()));
        }

        return $response;
    }

    /**
     * A smoke test by one actor: its start pages, then every link of the
     * `links` selector, followed `depth` times over, each page once. Every
     * answer is checked — no server error, no translation key left raw in a
     * page, JSON readable where it says it is JSON — and what fails is kept
     * for the report. Redirects are answers, not followed: the login page a
     * visitor is sent to is not visited for it.
     *
     * @return int the pages visited
     */
    public function explore(ExplorationDefinition $exploration): int
    {
        $this->as($exploration->actor);
        $queue = [];
        $visited = [];

        foreach ($exploration->start as $start) {
            $queue[] = [$this->startUrl($start), 0];
        }

        while ($queue && count($visited) < $exploration->max) {
            [$url, $depth] = array_shift($queue);

            if (isset($visited[$url])) {
                continue;
            }

            $visited[$url] = true;
            $response = $this->request(Request::METHOD_GET, $url);
            $this->checkPage($exploration->actor, $url, $response);

            if ($depth >= $exploration->depth || ! $response->isSuccessful() || ! $this->isHtml($response)) {
                continue;
            }

            foreach ($this->client->getCrawler()->filter($exploration->links) as $link) {
                $next = $this->internalPath((string) $link->getAttribute('href'));

                if (null !== $next && ! isset($visited[$next]) && ! $this->isSkipped($next, $exploration->skip)) {
                    $queue[] = [$next, $depth + 1];
                }
            }
        }

        return count($visited);
    }

    private function checkPage(string $actor, string $url, Response $response): void
    {
        $where = 'explore ' . $actor . ' ' . $url;

        if ($response->isServerError()) {
            $this->accessFailures[] = sprintf('%s: server error %d%s', $where, $response->getStatusCode(), $this->describeError($response));

            return;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode((string) $response->getContent(), true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                $this->accessFailures[] = $where . ': says JSON but is not (' . json_last_error_msg() . ')';

                return;
            }

            // An endpoint of symfony-api, when it is installed: held to its norm.
            if (str_starts_with($url, '/api/') && class_exists(self::API_NORM)) {
                foreach ((self::API_NORM)::problems($decoded) as $problem) {
                    $this->accessFailures[] = $where . ': ' . $problem;
                }
            }

            return;
        }

        if ($response->isSuccessful() && $this->isHtml($response)) {
            // Text node by text node, so that two elements side by side do
            // not run their words together. Left out: scripts and styles,
            // templates a script renders later (their keys are translated
            // then), and code shown as code.
            $text = implode(' ', (new Crawler((string) $response->getContent()))
                ->filterXPath('//body//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::template) and not(ancestor::code) and not(ancestor::pre)]')
                ->each(static fn (Crawler $node) => $node->text('', true)));

            if (preg_match_all(self::RAW_TRANSLATION_KEY, $text, $matches)) {
                $this->accessFailures[] = $where . ': translation keys left raw: ' . implode(', ', array_slice(array_unique($matches[0]), 0, 5));
            }
        }
    }

    private const string API_NORM = 'Wexample\\SymfonyApi\\Helper\\ApiResponseNormHelper';

    /**
     * A key the translator handed back as is: `@page::title`,
     * `WexampleSymfonyUserBundle.pages.profile::name`.
     */
    private const string RAW_TRANSLATION_KEY = '/(?:@[a-z_]+|\b[A-Z][A-Za-z]*Bundle(?:\.[\w-]+)*)::[\w.-]+/';

    private function startUrl(string|array $start): string
    {
        if (is_array($start)) {
            return $this->url($start['route'], $start['params'] ?? []);
        }

        return str_starts_with($start, '/') ? $start : $this->url($start);
    }

    /**
     * The path of a link staying in the application, its fragment dropped;
     * null for anything else.
     */
    private function internalPath(string $href): ?string
    {
        if ('' === $href || str_starts_with($href, '#') || str_starts_with($href, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $href) && ! str_starts_with($href, 'http://localhost')) {
            return null;
        }

        $href = preg_replace('/^http:\/\/localhost/', '', $href);
        $href = explode('#', $href, 2)[0];

        return str_starts_with($href, '/') ? $href : null;
    }

    /**
     * @param list<string> $patterns
     */
    private function isSkipped(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('#' . str_replace('#', '\#', $pattern) . '#', $path)) {
                return true;
            }
        }

        return false;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'html');
    }

    /**
     * The parameters of the page the browser stands on, kept under a name:
     * the note a form just created, reached through `@note.id`.
     */
    public function capture(string $name): array
    {
        $path = $this->client->getRequest()->getPathInfo();
        $parameters = array_filter(
            $this->router()->match($path),
            static fn (string $key) => ! str_starts_with($key, '_'),
            ARRAY_FILTER_USE_KEY
        );

        $this->references->set($name, $parameters);
        $this->trace[] = sprintf('captured @%s from %s', $name, $path);

        return $parameters;
    }

    /**
     * @return list<string>
     */
    public function getAccessFailures(): array
    {
        return $this->accessFailures;
    }

    /**
     * Every request made, who made it and what came back: the story of the
     * run, printed when it fails.
     *
     * @return list<string>
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * What broke, on a server error: the exception Symfony names in debug.
     */
    private function describeError(Response $response): string
    {
        if (! $response->isServerError()) {
            return '';
        }

        if ($exception = $response->headers->get('X-Debug-Exception')) {
            return ' (' . rawurldecode($exception) . ')';
        }

        // A JSON error says it in its body; Symfony's error page, in its title.
        $data = json_decode((string) $response->getContent(), true);
        if (is_array($data) && is_string($message = $data['message'] ?? $data['detail'] ?? $data['title'] ?? null)) {
            return ' (' . $message . ')';
        }

        if (preg_match('/<title>(.*?)<\/title>/s', (string) $response->getContent(), $match)) {
            return ' (' . trim(html_entity_decode(strip_tags($match[1]))) . ')';
        }

        return '';
    }

    private function isRedirectToLogin(Response $response): bool
    {
        if (! $response->isRedirection() || ! $location = $response->headers->get('Location')) {
            return false;
        }

        try {
            $match = $this->router()->match(parse_url($location, PHP_URL_PATH) ?: '/');
        } catch (RoutingException) {
            return false;
        }

        return ($match['_route'] ?? null) === $this->fixtures->loginRoute;
    }

    private function router(): RouterInterface
    {
        return $this->getContainer()->get('router');
    }

    /**
     * A Doctrine entity read again by the current manager; anything else as
     * it is.
     */
    private function refresh(object $object): object
    {
        $container = $this->getContainer();

        if (! $container->has('doctrine')) {
            return $object;
        }

        $manager = $container->get('doctrine')->getManagerForClass($object::class);

        if (! $manager || $manager->getMetadataFactory()->isTransient($object::class) || $manager->contains($object)) {
            return $object;
        }

        $metadata = $manager->getClassMetadata($object::class);
        $id = $metadata->getIdentifierValues($object);

        return $id ? ($manager->find($metadata->getName(), $id) ?? $object) : $object;
    }
}
