<?php

namespace Wexample\SymfonyTesting\Class\Scenario;

/**
 * How an actor walks the application for a smoke test: from its start pages,
 * every link found in `links` is followed, `depth` times over, and each page
 * met must answer without a server error, with no translation key left raw,
 * and as readable JSON where it says it is JSON.
 */
final readonly class ExplorationDefinition
{
    public const array DEFAULT_SKIP = ['^/_', '^/build/', 'logout'];

    /**
     * @param list<string|array{route: string, params?: array<string, mixed>}> $start routes or paths
     * @param list<string>                                                      $skip  path patterns not followed
     */
    public function __construct(
        public string $actor,
        public array $start,
        public string $links = 'a',
        public int $depth = 1,
        public array $skip = self::DEFAULT_SKIP,
        public int $max = 200,
    ) {
    }
}
