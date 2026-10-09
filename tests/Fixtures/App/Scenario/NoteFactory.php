<?php

namespace Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario;

use Wexample\SymfonyTesting\Class\Scenario\ScenarioContext;
use Wexample\SymfonyTesting\Interface\TestFixtureFactoryInterface;

final class NoteFactory implements TestFixtureFactoryInterface
{
    public static function getName(): string
    {
        return 'note';
    }

    public function create(array $with, ScenarioContext $context): object
    {
        $note = NoteStore::add($with['title'] ?? 'Note', $with['author']->getUserIdentifier());

        return new Note($note['id'], $note['title'], $note['author']);
    }

    public function remove(object $fixture, ScenarioContext $context): void
    {
        NoteStore::remove($fixture->id);
    }
}
