<?php

namespace Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario;

/**
 * The notes of the fixture application, kept for the whole PHP process: the
 * kernel reboots between requests, the store does not.
 */
final class NoteStore
{
    /** @var array<int, array{id: int, title: string, author: string, status: string}> */
    private static array $notes = [];

    public static function add(string $title, string $author): array
    {
        $id = count(self::$notes) + 1;

        return self::$notes[$id] = ['id' => $id, 'title' => $title, 'author' => $author, 'status' => 'written'];
    }

    public static function get(int $id): ?array
    {
        return self::$notes[$id] ?? null;
    }

    public static function setStatus(int $id, string $status): void
    {
        self::$notes[$id]['status'] = $status;
    }

    public static function remove(int $id): void
    {
        unset(self::$notes[$id]);
    }
}
