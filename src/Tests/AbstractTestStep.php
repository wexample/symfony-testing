<?php

namespace Wexample\SymfonyTesting\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyTesting\Traits\Application\ScenarioTestCaseTrait;

abstract class AbstractTestStep
{
    public UserInterface $user;

    /**
     * @param TestCase $test a test case using ScenarioTestCaseTrait
     *
     * @see ScenarioTestCaseTrait
     */
    public function __construct(public TestCase $test)
    {
    }

    abstract public function getSynopsis(): string;

    abstract public function execute();

    protected function getContainer(): ContainerInterface
    {
        return $this->test->getScenarioContainer();
    }

    protected array $dataBag = [];

    public function setDataBag(array $dataBag): void
    {
        $this->dataBag = $dataBag;
    }

    public function getData(string $key)
    {
        return $this->dataBag[$key];
    }
}
