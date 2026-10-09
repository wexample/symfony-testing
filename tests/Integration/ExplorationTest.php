<?php

namespace Wexample\SymfonyTesting\Tests\Integration;

use Wexample\SymfonyTesting\Class\Scenario\ExplorationDefinition;
use Wexample\SymfonyTesting\Class\Scenario\ScenarioFixtures;
use Wexample\SymfonyTesting\Interface\TestActorProviderInterface;
use Wexample\SymfonyTesting\Tests\AbstractScenarioTestCase;
use Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario\MemoryActorProvider;

class ExplorationTest extends AbstractScenarioTestCase
{
    private const string RESOURCES = __DIR__ . '/../Fixtures/App/Resources/';

    protected function getActorProvider(): TestActorProviderInterface
    {
        return new MemoryActorProvider();
    }

    public function testASoundCornerIsWalkedThroughItsMenu(): void
    {
        $context = $this->startScenario(self::RESOURCES . 'explore.yaml');

        // The start page, A, B, the JSON endpoint; not the outside link, the
        // private path, nor the link out of the menu.
        $this->assertSame(4, $context->explore($context->fixtures->explore['alice']));
        $this->assertSame([], $context->getAccessFailures());
        $trace = implode("\n", $context->getTrace());
        $this->assertStringNotContainsString('/_private', $trace);
        $this->assertStringNotContainsString('/explore/deep', $trace);
        $this->assertStringContainsString('/explore/sound/b →', $trace, 'Followed without its fragment.');
    }

    public function testEveryKindOfBreakageIsReported(): void
    {
        $context = $this->startScenario(ScenarioFixtures::fromArray([
            'actors' => ['alice' => ['user' => 'alice']],
        ]));

        $context->explore(new ExplorationDefinition('alice', ['explore_broken'], 'nav a'));

        $failures = implode("\n", $context->getAccessFailures());
        $this->assertStringContainsString('explore alice /explore/crash: server error 500', $failures);
        $this->assertStringContainsString('The page fell over.', $failures);
        $this->assertStringContainsString('explore alice /explore/raw: translation keys left raw: @page::title, WexampleSymfonyUserBundle.pages.profile::name', $failures);
        $this->assertStringContainsString('explore alice /api/broken: says JSON but is not', $failures);
        $this->assertCount(3, $context->getAccessFailures());
    }

    public function testTheSectionRunsFromTheFile(): void
    {
        $this->assertExploration(self::RESOURCES . 'explore.yaml');
    }
}
