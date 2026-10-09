<?php

namespace Wexample\SymfonyTesting\Class\Scenario;

/**
 * One piece of data of a fixtures file, created before the first step under
 * its name, so steps and other fixtures refer to it as `@name`.
 */
final readonly class FixtureDefinition
{
    /**
     * @param array<string, mixed> $with
     */
    public function __construct(
        public string $name,
        public string $factory,
        public array $with = [],
    ) {
    }
}
