<?php

namespace Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario;

final readonly class Note
{
    public function __construct(
        public int $id,
        public string $title,
        public string $author,
    ) {
    }
}
