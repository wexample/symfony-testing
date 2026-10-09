<?php

namespace Wexample\SymfonyTesting\Class\Scenario;

use InvalidArgumentException;
use RuntimeException;
use Wexample\PhpBpmn\Class\Element\Process;
use Wexample\PhpBpmn\Class\Element\Task;
use Wexample\PhpBpmn\Class\Path\Path;
use Wexample\PhpBpmn\Class\Path\PathFinder;
use Wexample\PhpBpmn\Class\Path\PathStep;
use Wexample\PhpBpmn\Class\Xml\BpmnXmlReader;
use Wexample\SymfonyTesting\Enum\AccessExpectation;

/**
 * A process played against the application, one path at a time, by the
 * actors of its lanes. At each task, every actor of the fixtures opens the
 * task's page — the lane's actors must get it, the others must be turned
 * away, unless the task says otherwise —, then the lane's first actor does
 * the task. Needs wexample/php-bpmn.
 */
final class BpmnScenario
{
    public function __construct(
        public readonly Process $process,
        public readonly ScenarioFixtures $fixtures,
    ) {
    }

    public static function fromFiles(string $bpmnPath, string $fixturesPath, ?string $processId = null): self
    {
        $definitions = (new BpmnXmlReader())->readFile($bpmnPath);
        $process = null !== $processId
            ? $definitions->getProcess($processId)
            : (array_values($definitions->getProcesses())[0] ?? null);

        if (! $process) {
            throw new InvalidArgumentException('No process ' . ($processId ?? '') . ' in ' . $bpmnPath . '.');
        }

        return new self($process, ScenarioFixtures::fromFile($fixturesPath));
    }

    /**
     * The complete paths, each a story to play.
     *
     * @return array<string, Path> keyed by what each path chose
     */
    public function getPaths(): array
    {
        $paths = [];

        foreach ((new PathFinder(maxVisits: 1))->find($this->process) as $path) {
            if ($path->isComplete()) {
                $paths[$path->describe()] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param object $test the test case, whose methods `php` tasks call with ($context, $step, $path)
     */
    public function play(Path $path, ScenarioContext $context, object $test): void
    {
        foreach ($path->getSteps() as $index => $step) {
            $binding = $this->bindingOf($step);

            if (! $binding) {
                continue;
            }

            $where = sprintf('[%s] step %d "%s"', $path->describe(), $index + 1, $step->name ?? $step->nodeId);
            $lane = $this->laneActors($step, $where);

            if ($binding->route) {
                $context->checkAccess($binding->route, $binding->params, $this->expectations($lane, $binding), $where);
            }

            $context->as($lane[0]);

            try {
                $this->perform($binding, $context, $test, $step, $path);
            } catch (\Throwable $exception) {
                throw new RuntimeException($where . ', done by ' . $lane[0] . ': ' . $exception->getMessage(), 0, $exception);
            }
        }
    }

    /**
     * A task bound in the fixtures; a human task left unbound is a gap the
     * run reports, an automatic one or an event is the application's own.
     */
    private function bindingOf(PathStep $step): ?TaskBinding
    {
        if ($binding = $this->fixtures->tasks[$step->nodeId] ?? null) {
            return $binding;
        }

        $node = $this->process->getFlow()->getNode($step->nodeId);

        if ($node instanceof Task && $node->getType()->isHuman() && $step->laneId) {
            throw new RuntimeException(sprintf('Task "%s" (%s) is done by a person but bound to nothing: give it an entry under `tasks` in the fixtures.', $step->name ?? $step->nodeId, $step->nodeId));
        }

        return null;
    }

    /**
     * @return non-empty-list<string>
     */
    private function laneActors(PathStep $step, string $where): array
    {
        $actors = $step->laneId ? ($this->fixtures->lanes[$step->laneId] ?? []) : [];

        if (! $actors) {
            throw new RuntimeException($where . ': lane ' . ($step->laneId ?? 'none') . ' is played by no actor, add it under `lanes` in the fixtures.');
        }

        return $actors;
    }

    /**
     * @param list<string> $lane
     *
     * @return array<string, AccessExpectation>
     */
    private function expectations(array $lane, TaskBinding $binding): array
    {
        $expectations = [];

        foreach (array_keys($this->fixtures->actors) as $alias) {
            $expectations[$alias] = in_array($alias, $lane, true) ? AccessExpectation::OK : AccessExpectation::DENIED;
        }

        return array_replace($expectations, $binding->expect);
    }

    private function perform(TaskBinding $binding, ScenarioContext $context, object $test, PathStep $step, Path $path): void
    {
        [$do, $options] = $binding->doOn(array_map(static fn (array $choice) => $choice[1], $path->getChoices()));

        switch ($do) {
            case TaskBinding::DO_VISIT:
                if (! $binding->route) {
                    throw new RuntimeException('nothing to visit: the task has no route.');
                }

                $response = $context->open($binding->route, $binding->params);
                if (AccessExpectation::OK !== $context->met($response)) {
                    throw new RuntimeException(sprintf('opening %s answered %d.', $binding->route, $response->getStatusCode()));
                }
                break;

            case TaskBinding::DO_SUBMIT:
                $context->submit(
                    $binding->route ?? throw new RuntimeException('nothing to submit: the task has no route.'),
                    $binding->params,
                    $options['button'] ?? throw new RuntimeException('submit names no button.'),
                    $options['fill'] ?? [],
                );

                if (isset($options['capture'])) {
                    $context->capture($options['capture']);
                }
                break;

            case TaskBinding::DO_PHP:
                $method = $options['method'] ?? throw new RuntimeException('php names no method.');
                if (! method_exists($test, $method)) {
                    throw new RuntimeException(sprintf('%s has no method %s.', $test::class, $method));
                }
                $test->$method($context, $step, $path);
                break;
        }
    }
}
