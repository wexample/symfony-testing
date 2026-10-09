<?php

namespace Wexample\SymfonyTesting\Interface;

use Wexample\SymfonyTesting\Class\Scenario\ScenarioContext;

/**
 * Creates one kind of data a scenario acts on — a team, an envelope — from
 * the `with` values of the fixtures file, references already resolved.
 */
interface TestFixtureFactoryInterface
{
    /**
     * The name the fixtures file calls it by: `factory: team`.
     */
    public static function getName(): string;

    public function create(array $with, ScenarioContext $context): object;

    public function remove(object $fixture, ScenarioContext $context): void;
}
