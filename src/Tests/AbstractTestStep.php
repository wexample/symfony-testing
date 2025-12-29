<?php

namespace Wexample\SymfonyTesting\Tests;

use App\Entity\User;
use App\Wex\BaseBundle\Tests\SymfonyTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractTestStep
{
    public User $user;

    public function __construct(public SymfonyTestCase $test)
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
