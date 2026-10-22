<?php

namespace Wexample\SymfonyTesting\Class\Scenario;

use Closure;
use InvalidArgumentException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * What a scenario has made or met, by name: `@editorial`, `@editorial.id`,
 * `@actor.member.email`; inside a longer text, `${editorial.id}`. A value
 * starting with `@@` is the literal text after the first `@`. Objects are refreshed on each read, so an entity stays the
 * one the current request's manager knows.
 */
final class ReferenceBag
{
    private const string ACTOR_PREFIX = 'actor.';

    /** @var array<string, mixed> */
    private array $values = [];

    private readonly PropertyAccessorInterface $accessor;

    /**
     * @param Closure(object): object       $refresh
     * @param Closure(string): (object|null) $actor   the user of an alias
     */
    public function __construct(
        private readonly Closure $refresh,
        private readonly Closure $actor,
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    public function set(string $name, mixed $value): void
    {
        $this->values[$name] = $value;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function get(string $name): mixed
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException('No reference @' . $name . ', known: ' . (implode(', ', array_keys($this->values)) ?: 'none') . '.');
        }

        $value = $this->values[$name];

        return is_object($value) ? ($this->refresh)($value) : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Every `@…` string of a value, arrays walked through.
     */
    public function resolve(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->resolve(...), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        if (! str_starts_with($value, '@')) {
            return str_contains($value, '${')
                ? preg_replace_callback('/\$\{([^}]+)\}/', fn (array $match) => (string) $this->path($match[1]), $value)
                : $value;
        }

        if (str_starts_with($value, '@@')) {
            return substr($value, 1);
        }

        return $this->path(substr($value, 1));
    }

    private function path(string $path): mixed
    {
        if (str_starts_with($path, self::ACTOR_PREFIX)) {
            $rest = substr($path, strlen(self::ACTOR_PREFIX));
            [$alias, $property] = array_pad(explode('.', $rest, 2), 2, null);
            $root = ($this->actor)($alias);
        } else {
            [$name, $property] = array_pad(explode('.', $path, 2), 2, null);
            $root = $this->get($name);
        }

        if (null === $property) {
            return $root;
        }

        // An array is read key by key: `@note.id` on what a page captured.
        if (is_array($root)) {
            $property = '[' . str_replace('.', '][', $property) . ']';
        }

        return $this->accessor->getValue($root, $property);
    }
}
