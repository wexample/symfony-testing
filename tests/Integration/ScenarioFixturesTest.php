<?php

namespace Wexample\SymfonyTesting\Tests\Integration;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wexample\SymfonyTesting\Class\Scenario\ScenarioFixtures;
use Wexample\SymfonyTesting\Class\Scenario\TaskBinding;
use Wexample\SymfonyTesting\Enum\AccessExpectation;

class ScenarioFixturesTest extends TestCase
{
    private const string RESOURCES = __DIR__ . '/../Fixtures/App/Resources/';

    public function testReadsAFileWithItsImports(): void
    {
        $fixtures = ScenarioFixtures::fromFile(self::RESOURCES . 'note-review.yaml');

        $this->assertSame('login', $fixtures->loginRoute);
        $this->assertSame(['visitor', 'alice', 'carol', 'bob'], array_keys($fixtures->actors));
        $this->assertTrue($fixtures->getActor('visitor')->isAnonymous());
        $this->assertSame('bob', $fixtures->getActor('bob')->user);
        $this->assertSame(['writer' => ['alice'], 'reviewer' => ['bob']], $fixtures->lanes);

        $review = $fixtures->tasks['review'];
        $this->assertSame(TaskBinding::DO_SUBMIT, $review->do);
        $this->assertSame(['button' => 'Approve'], $review->doOptions);
        $this->assertSame([TaskBinding::DO_SUBMIT, ['button' => 'Reject']], $review->doOn(['flow_rejected']));
        $this->assertSame([TaskBinding::DO_SUBMIT, ['button' => 'Approve']], $review->doOn(['flow_approved']));

        $this->assertSame(AccessExpectation::NOT_FOUND, $fixtures->tasks['read_verdict']->expect['carol']);
    }

    public function testRefusesAnUnknownActor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lane writer: no actor dave');

        ScenarioFixtures::fromArray(['actors' => ['alice' => ['user' => 'alice']], 'lanes' => ['writer' => 'dave']]);
    }

    public function testRefusesAnUnknownExpectation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alice expects "maybe"');

        ScenarioFixtures::fromArray(['actors' => ['alice' => ['user' => 'alice']], 'access' => ['home' => ['expect' => ['alice' => 'maybe']]]]);
    }

    public function testRefusesAnUnknownKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown key(s) users');

        ScenarioFixtures::fromArray(['users' => []]);
    }

    public function testDeniedGathersTheThreeRefusals(): void
    {
        foreach ([AccessExpectation::LOGIN, AccessExpectation::FORBIDDEN, AccessExpectation::NOT_FOUND] as $refusal) {
            $this->assertTrue(AccessExpectation::DENIED->accepts($refusal));
        }

        $this->assertFalse(AccessExpectation::DENIED->accepts(AccessExpectation::OK));
        $this->assertFalse(AccessExpectation::DENIED->accepts(null));
        $this->assertSame(AccessExpectation::LOGIN, AccessExpectation::fromResponse(302, true));
        $this->assertSame(AccessExpectation::OK, AccessExpectation::fromResponse(302, false));
        $this->assertNull(AccessExpectation::fromResponse(500, false));
    }
}
