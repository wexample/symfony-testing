<?php

namespace Wexample\SymfonyTesting\Tests\Integration;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use Wexample\SymfonyTesting\Class\Scenario\BpmnScenario;
use Wexample\SymfonyTesting\Class\Scenario\ScenarioFixtures;
use Wexample\SymfonyTesting\Interface\TestActorProviderInterface;
use Wexample\SymfonyTesting\Tests\AbstractScenarioTestCase;
use Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario\MemoryActorProvider;
use Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario\NoteFactory;
use Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario\NoteStore;

class ScenarioTest extends AbstractScenarioTestCase
{
    private const string RESOURCES = __DIR__ . '/../Fixtures/App/Resources/';

    protected function getActorProvider(): TestActorProviderInterface
    {
        return new MemoryActorProvider();
    }

    protected function getFixtureFactories(): iterable
    {
        return [new NoteFactory()];
    }

    public static function noteReviewPaths(): iterable
    {
        return self::bpmnPathsOf(self::RESOURCES . 'note-review.bpmn', self::RESOURCES . 'note-review.yaml');
    }

    public function testEachActorKeepsItsOwnSession(): void
    {
        $context = $this->startScenario(self::RESOURCES . 'note-review.yaml');

        foreach ([1, 2, 3] as $round) {
            foreach (['alice', 'bob', 'visitor', 'carol'] as $alias) {
                $context->as($alias)->open('whoami');
                $this->assertSame('visitor' === $alias ? 'anonymous' : $alias, $this->client->getResponse()->getContent(), 'round ' . $round);
            }
        }
    }

    public function testAccessMatrix(): void
    {
        $this->assertAccessMatrix(self::RESOURCES . 'access.yaml');
    }

    public function testDataIsRemovedAfterTheScenario(): void
    {
        $context = $this->startScenario(self::RESOURCES . 'access.yaml');
        $id = $context->get('alice_note')->id;
        $this->assertNotNull(NoteStore::get($id));
        $this->assertSame($id, $context->references->resolve('@alice_note.id'));
        $this->assertSame('note-' . $id . ' by alice', $context->references->resolve('note-${alice_note.id} by ${actor.alice.userIdentifier}'));
        $this->assertSame('@alice_note', $context->references->resolve('@@alice_note'));

        $context->tearDown();

        $this->assertNull(NoteStore::get($id));
    }

    public function testFindsBothWaysThroughTheReview(): void
    {
        $paths = BpmnScenario::fromFiles(self::RESOURCES . 'note-review.bpmn', self::RESOURCES . 'note-review.yaml')->getPaths();

        $this->assertSame(['main: approved', 'verdict → default ↺'], array_keys($paths));
    }

    #[DataProvider('noteReviewPaths')]
    public function testNoteReview(string $path): void
    {
        $this->playBpmnPath(self::RESOURCES . 'note-review.bpmn', self::RESOURCES . 'note-review.yaml', $path);

        // Sent back or not, the note ends approved.
        $this->assertSame('approved', NoteStore::get($this->scenario->get('note')['id'])['status']);
    }

    public function testReportsEveryAccessThatDiffers(): void
    {
        $fixtures = ScenarioFixtures::fromArray([
            'login_route' => 'login',
            'actors' => ['visitor' => null, 'alice' => ['user' => 'alice'], 'bob' => ['user' => 'bob']],
            'access' => [
                // Both wrong: the visitor is sent to log in, alice is let in.
                'note_new' => ['expect' => ['visitor' => 'ok', 'alice' => 'forbidden', 'bob' => 'ok']],
            ],
        ]);
        $context = $this->startScenario($fixtures);
        foreach ($fixtures->access as $label => $entry) {
            $context->checkAccess($entry['route'], $entry['params'], $entry['expect'], 'access ' . $label);
        }

        $this->assertSame([
            'access note_new: visitor expected ok on note_new, met login (302)',
            'access note_new: alice expected forbidden on note_new, met ok (200)',
        ], $context->getAccessFailures());

        $this->expectException(AssertionFailedError::class);
        $this->assertScenarioPassed();
    }

    public function testAHumanTaskLeftUnboundStopsTheRun(): void
    {
        $bpmn = BpmnScenario::fromFiles(self::RESOURCES . 'note-review.bpmn', self::RESOURCES . 'actors.yaml');
        $context = $this->startScenario($bpmn->fixtures);

        $this->expectExceptionMessage('Task "Write a note" (write) is done by a person but bound to nothing');
        $bpmn->play($bpmn->getPaths()['main: approved'], $context, $this);
    }
}
