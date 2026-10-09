<?php

namespace Wexample\SymfonyTesting\Class\Scenario;

use Wexample\SymfonyTesting\Enum\AccessExpectation;

/**
 * What a task of the process is in the application: the page it is done on,
 * who may open that page when the task comes, and how it is done.
 */
final readonly class TaskBinding
{
    public const string DO_VISIT = 'visit';

    public const string DO_SUBMIT = 'submit';

    public const string DO_PHP = 'php';

    /**
     * @param array<string, mixed>             $params route parameters, references allowed
     * @param array<string, AccessExpectation> $expect per actor alias, over the default: the lane's actors `ok`, the others `denied`
     * @param string                           $do        `visit` (the default: opening the page is the task), `submit` a form of the page, `php` a method of the test
     * @param array<string, mixed>             $doOptions for `submit`: `button`, `fill`, `capture` (a name for the parameters of the page landed on); for `php`: `method`
     * @param array<string, array{string, array<string, mixed>}> $when another way of doing it, by the id of a flow the path takes further on: the task decides a gateway
     */
    public function __construct(
        public string $taskId,
        public ?string $route = null,
        public array $params = [],
        public array $expect = [],
        public string $do = self::DO_VISIT,
        public array $doOptions = [],
        public array $when = [],
    ) {
    }

    /**
     * How the task is done on a path taking these flows.
     *
     * @param list<string> $flowIds
     *
     * @return array{string, array<string, mixed>}
     */
    public function doOn(array $flowIds): array
    {
        foreach ($this->when as $flowId => $do) {
            if (in_array($flowId, $flowIds, true)) {
                return $do;
            }
        }

        return [$this->do, $this->doOptions];
    }
}
