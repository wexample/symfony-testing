<?php

namespace Wexample\SymfonyTesting\Class\Scenario;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;
use Wexample\SymfonyTesting\Enum\AccessExpectation;

/**
 * A fixtures file: who takes part, which lane each one plays, the data the
 * steps act on, what each task is in the application, and the pages each
 * actor may or may not open. The process says what happens; this file says
 * where, and with whom.
 *
 *     imports: [actors.yaml]          # merged first, this file wins
 *     login_route: user_security_login
 *     firewall: main
 *     actors:
 *         visitor: ~                  # anonymous
 *         member: { user: demo }
 *         admin: { user: demo-admin, roles: [ROLE_ADMIN] }
 *     lanes:
 *         member: [member, admin]
 *     data:
 *         editorial: { factory: team, with: { name: Editorial, administrator: '@actor.member' } }
 *     tasks:
 *         open-team:
 *             route: user_memberships_index
 *             params: { id: '@editorial.id' }
 *             expect: { admin: ok }
 *             do: { submit: { button: Add, fill: { 'form[member]': '@actor.admin.id' } } }
 *             when:                   # where the task decides a gateway: by the flow taken after it
 *                 flow_refused: { submit: { button: Refuse } }
 *     access:
 *         user_accounts_index: { expect: { visitor: login, member: forbidden, admin: ok } }
 */
final class ScenarioFixtures
{
    public const string DEFAULT_LOGIN_ROUTE = 'user_security_login';

    public const string DEFAULT_FIREWALL = 'main';

    private const array KEYS = ['imports', 'login_route', 'firewall', 'actors', 'lanes', 'data', 'tasks', 'access'];

    /**
     * @param array<string, ActorDefinition>          $actors
     * @param array<string, list<string>>             $lanes  lane id → actor aliases, the first one doing the tasks
     * @param list<FixtureDefinition>                 $data
     * @param array<string, TaskBinding>              $tasks
     * @param array<string, array{route: string, params: array<string, mixed>, expect: array<string, AccessExpectation>}> $access
     */
    public function __construct(
        public readonly string $loginRoute = self::DEFAULT_LOGIN_ROUTE,
        public readonly string $firewall = self::DEFAULT_FIREWALL,
        public readonly array $actors = [],
        public readonly array $lanes = [],
        public readonly array $data = [],
        public readonly array $tasks = [],
        public readonly array $access = [],
    ) {
    }

    public static function fromFile(string $path): self
    {
        return self::fromArray(self::load($path), $path);
    }

    public static function fromArray(array $config, string $source = 'fixtures'): self
    {
        $unknown = array_diff(array_keys($config), self::KEYS);
        if ($unknown) {
            throw new InvalidArgumentException($source . ': unknown key(s) ' . implode(', ', $unknown) . ', expected ' . implode(', ', self::KEYS) . '.');
        }

        $actors = [];
        foreach ($config['actors'] ?? [] as $alias => $actor) {
            $actor ??= [];
            $actors[$alias] = new ActorDefinition(
                alias: $alias,
                user: isset($actor['user']) ? (string) $actor['user'] : null,
                roles: $actor['roles'] ?? [],
                with: $actor['with'] ?? [],
            );
        }

        $lanes = [];
        foreach ($config['lanes'] ?? [] as $laneId => $aliases) {
            $lanes[$laneId] = self::aliases((array) $aliases, $actors, $source . ': lane ' . $laneId);
        }

        $data = [];
        foreach ($config['data'] ?? [] as $name => $fixture) {
            if (! isset($fixture['factory'])) {
                throw new InvalidArgumentException($source . ': data ' . $name . ' names no factory.');
            }

            $data[] = new FixtureDefinition($name, $fixture['factory'], $fixture['with'] ?? []);
        }

        $tasks = [];
        foreach ($config['tasks'] ?? [] as $taskId => $task) {
            $task ??= [];
            [$do, $doOptions] = self::doOf($task['do'] ?? TaskBinding::DO_VISIT, $source . ': task ' . $taskId);

            $tasks[$taskId] = new TaskBinding(
                taskId: $taskId,
                route: $task['route'] ?? null,
                params: $task['params'] ?? [],
                expect: self::expectations($task['expect'] ?? [], $actors, $source . ': task ' . $taskId),
                do: $do,
                doOptions: $doOptions,
                when: array_map(
                    static fn (string|array $do) => self::doOf($do, $source . ': task ' . $taskId . ' when'),
                    $task['when'] ?? []
                ),
            );
        }

        $access = [];
        foreach ($config['access'] ?? [] as $label => $entry) {
            $access[$label] = [
                'route' => $entry['route'] ?? $label,
                'params' => $entry['params'] ?? [],
                'expect' => self::expectations($entry['expect'] ?? [], $actors, $source . ': access ' . $label),
            ];
        }

        return new self(
            loginRoute: $config['login_route'] ?? self::DEFAULT_LOGIN_ROUTE,
            firewall: $config['firewall'] ?? self::DEFAULT_FIREWALL,
            actors: $actors,
            lanes: $lanes,
            data: $data,
            tasks: $tasks,
            access: $access,
        );
    }

    public function getActor(string $alias): ActorDefinition
    {
        return $this->actors[$alias] ?? throw new InvalidArgumentException('No actor ' . $alias . ' in the fixtures.');
    }

    /**
     * Imports first, deeper keys of this file over theirs.
     */
    private static function load(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('No fixtures file at ' . $path . '.');
        }

        $config = Yaml::parseFile($path) ?? [];
        $merged = [];

        foreach ($config['imports'] ?? [] as $import) {
            $merged = array_replace_recursive($merged, self::load(dirname($path) . '/' . $import));
        }
        unset($config['imports']);

        return array_replace_recursive($merged, $config);
    }

    /**
     * @return list<string>
     */
    private static function aliases(array $aliases, array $actors, string $where): array
    {
        foreach ($aliases as $alias) {
            if (! isset($actors[$alias])) {
                throw new InvalidArgumentException($where . ': no actor ' . $alias . '.');
            }
        }

        return array_values($aliases);
    }

    /**
     * @return array<string, AccessExpectation>
     */
    private static function expectations(array $expect, array $actors, string $where): array
    {
        $expectations = [];

        foreach ($expect as $alias => $value) {
            self::aliases([$alias], $actors, $where);

            $expectations[$alias] = AccessExpectation::tryFrom((string) $value)
                ?? throw new InvalidArgumentException($where . ': ' . $alias . ' expects "' . $value . '", one of ' . implode(', ', array_map(static fn (AccessExpectation $case) => $case->value, AccessExpectation::cases())) . '.');
        }

        return $expectations;
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private static function doOf(string|array $do, string $where): array
    {
        if (is_string($do)) {
            $do = [$do => []];
        }

        $kind = array_key_first($do);
        $options = $do[$kind] ?? [];

        if (TaskBinding::DO_PHP === $kind && is_string($options)) {
            $options = ['method' => $options];
        }

        if (! in_array($kind, [TaskBinding::DO_VISIT, TaskBinding::DO_SUBMIT, TaskBinding::DO_PHP], true) || 1 !== count($do)) {
            throw new InvalidArgumentException($where . ': do is one of visit, submit, php.');
        }

        return [$kind, (array) $options];
    }
}
