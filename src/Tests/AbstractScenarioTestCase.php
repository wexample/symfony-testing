<?php

namespace Wexample\SymfonyTesting\Tests;

use Throwable;
use Wexample\SymfonyTesting\Class\Scenario\BpmnScenario;
use Wexample\SymfonyTesting\Class\Scenario\ScenarioContext;
use Wexample\SymfonyTesting\Class\Scenario\ScenarioFixtures;
use Wexample\SymfonyTesting\Interface\TestActorProviderInterface;
use Wexample\SymfonyTesting\Interface\TestFixtureFactoryInterface;

/**
 * Tests that take the parts of several people in the application: from a
 * fixtures file, the pages each one may open (`access`), and the paths of a
 * BPMN process played lane by lane (`tasks`). The data and the accounts
 * created for the test are removed after it.
 */
abstract class AbstractScenarioTestCase extends AbstractApplicationTestCase
{
    protected ?ScenarioContext $scenario = null;

    abstract protected function getActorProvider(): TestActorProviderInterface;

    /**
     * @return iterable<TestFixtureFactoryInterface>
     */
    protected function getFixtureFactories(): iterable
    {
        return [];
    }

    /**
     * The paths of a process, for a data provider: one test per path, named
     * after the choices it makes.
     *
     * @return iterable<string, array{string}>
     */
    public static function bpmnPathsOf(string $bpmnPath, string $fixturesPath): iterable
    {
        foreach (array_keys(BpmnScenario::fromFiles($bpmnPath, $fixturesPath)->getPaths()) as $label) {
            yield $label => [$label];
        }
    }

    protected function startScenario(string|ScenarioFixtures $fixtures): ScenarioContext
    {
        $this->createGlobalClient();

        $this->scenario = new ScenarioContext(
            $this->client,
            is_string($fixtures) ? ScenarioFixtures::fromFile($fixtures) : $fixtures,
            static fn () => static::getContainer(),
            $this->getActorProvider(),
            $this->getFixtureFactories(),
        );
        $this->scenario->setUp();

        return $this->scenario;
    }

    /**
     * Every page of the `access` section, opened by each actor it names.
     */
    protected function assertAccessMatrix(string $fixturesPath): void
    {
        $context = $this->startScenario($fixturesPath);

        foreach ($context->fixtures->access as $label => $entry) {
            $context->checkAccess($entry['route'], $entry['params'], $entry['expect'], 'access ' . $label);
        }

        $this->assertScenarioPassed();
    }

    /**
     * Every actor of the `explore` section walks the application: no server
     * error, no raw translation key, JSON that reads.
     */
    protected function assertExploration(string $fixturesPath): void
    {
        $context = $this->startScenario($fixturesPath);

        foreach ($context->fixtures->explore as $exploration) {
            $this->assertGreaterThan(0, $context->explore($exploration), 'explore ' . $exploration->actor . ' visited nothing.');
        }

        $this->assertScenarioPassed();
    }

    /**
     * One path of the process, played from its start to its end.
     */
    protected function playBpmnPath(string $bpmnPath, string $fixturesPath, string $pathLabel, ?string $processId = null): void
    {
        $bpmn = BpmnScenario::fromFiles($bpmnPath, $fixturesPath, $processId);
        $path = $bpmn->getPaths()[$pathLabel] ?? null;
        $this->assertNotNull($path, 'No path "' . $pathLabel . '" in ' . $bpmnPath . '.');

        $context = $this->startScenario($bpmn->fixtures);

        try {
            $bpmn->play($path, $context, $this);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() . $this->describeTrace());
        }

        $this->assertScenarioPassed();
    }

    protected function assertScenarioPassed(): void
    {
        $failures = $this->scenario?->getAccessFailures() ?? [];

        $this->assertSame([], $failures, implode("\n", $failures) . $this->describeTrace());
    }

    protected function tearDown(): void
    {
        $scenario = $this->scenario;
        $this->scenario = null;
        $scenario?->tearDown();

        parent::tearDown();
    }

    private function describeTrace(): string
    {
        $trace = $this->scenario?->getTrace() ?? [];

        return $trace ? "\n\nRequests:\n  " . implode("\n  ", array_slice($trace, -40)) : '';
    }
}
