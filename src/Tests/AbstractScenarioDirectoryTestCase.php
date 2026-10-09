<?php

namespace Wexample\SymfonyTesting\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Wexample\SymfonyTesting\Class\Scenario\BpmnScenario;

/**
 * Every scenario of a directory, without a test class each: a process
 * `name.bpmn` is played path by path with the fixtures `name.yaml` beside
 * it, and any fixtures file holding an `access` section is checked as a
 * matrix. One class per application, giving its actors and its data:
 *
 *     class ScenariosTest extends AbstractScenarioDirectoryTestCase
 *     {
 *         protected static function getScenarioDirectory(): string { return __DIR__; }
 *         protected function getActorProvider(): TestActorProviderInterface { … }
 *     }
 *
 * The methods `php` tasks call are written on that class.
 */
abstract class AbstractScenarioDirectoryTestCase extends AbstractScenarioTestCase
{
    abstract protected static function getScenarioDirectory(): string;

    /**
     * @return iterable<string, array{?string, string, ?string}> bpmn file (null for a matrix or an exploration), fixtures file, path (or `access`, `explore`)
     */
    public static function scenarios(): iterable
    {
        $directory = static::getScenarioDirectory();

        foreach (glob($directory . '/*.bpmn') ?: [] as $bpmn) {
            $fixtures = substr($bpmn, 0, -strlen('.bpmn')) . '.yaml';

            if (! is_file($fixtures)) {
                throw new \RuntimeException(basename($bpmn) . ' has no fixtures beside it: ' . basename($fixtures) . '.');
            }

            foreach (array_keys(BpmnScenario::fromFiles($bpmn, $fixtures)->getPaths()) as $path) {
                yield basename($bpmn, '.bpmn') . ' › ' . $path => [$bpmn, $fixtures, $path];
            }
        }

        foreach (glob($directory . '/*.yaml') ?: [] as $fixtures) {
            $config = Yaml::parseFile($fixtures) ?? [];

            if (isset($config['access'])) {
                yield basename($fixtures, '.yaml') . ' › access' => [null, $fixtures, 'access'];
            }

            if (isset($config['explore'])) {
                yield basename($fixtures, '.yaml') . ' › explore' => [null, $fixtures, 'explore'];
            }
        }
    }

    #[DataProvider('scenarios')]
    public function testScenario(?string $bpmn, string $fixtures, ?string $path): void
    {
        if (null === $bpmn) {
            'explore' === $path ? $this->assertExploration($fixtures) : $this->assertAccessMatrix($fixtures);

            return;
        }

        $this->playBpmnPath($bpmn, $fixtures, $path);
    }
}
