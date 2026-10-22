<?php

namespace Wexample\SymfonyTesting\Tests\Integration;

use Wexample\SymfonyTesting\Interface\TestActorProviderInterface;
use Wexample\SymfonyTesting\Tests\AbstractScenarioDirectoryTestCase;
use Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario\MemoryActorProvider;
use Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario\NoteFactory;

/**
 * The fixture application's scenarios, found in their directory: both paths
 * of note-review.bpmn, and access.yaml.
 */
class ScenarioDirectoryTest extends AbstractScenarioDirectoryTestCase
{
    protected static function getScenarioDirectory(): string
    {
        return __DIR__ . '/../Fixtures/App/Resources';
    }

    protected function getActorProvider(): TestActorProviderInterface
    {
        return new MemoryActorProvider();
    }

    protected function getFixtureFactories(): iterable
    {
        return [new NoteFactory()];
    }

    public function testFindsEveryScenario(): void
    {
        $this->assertSame(
            ['note-review › main: approved', 'note-review › verdict → default ↺', 'access › access', 'explore › explore'],
            array_keys(iterator_to_array(static::scenarios()))
        );
    }
}
