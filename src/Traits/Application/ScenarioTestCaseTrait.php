<?php

namespace Wexample\SymfonyTesting\Traits\Application;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Wexample\SymfonyHelpers\Helper\EnvironmentHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;
use function chmod;
use function file_put_contents;
use function is_array;
use function json_encode;

trait ScenarioTestCaseTrait
{
    use LoggedUserApplicationTestCaseTrait;

    protected array $users = [];

    protected bool $useCache = false;

    protected bool $runOnlyMissingStepsInLocalEnv = false;

    protected array $steps = [];

    protected string $stepsTraceFile = './var/cache/testStepsCache.json';

    protected array $stepPreviousResponsesBag = [];

    public function getScenarioClient(): ?KernelBrowser
    {
        return $this->client;
    }

    protected function scenarioSetUp(): void {
        $this->keepCreatedUser = true;

        if ($this->useCache) {
            $this->runOnlyMissingStepsInLocalEnv();
        } else {
            FileHelper::deleteFileIfExists($this->stepsTraceFile);
        }
    }

    public function buildUsersMap(): array
    {
        return [];
    }

    protected function createTestUsers(array &$map): array
    {
        $testUsers = [];

        foreach ($map as $userName => $item) {
            $this->users[$userName] =
            $map[$userName]['user'] = $this->createAndSaveUserIfNotExists(
                $userName,
                $item['role'],
                !$this->useCache
            );
        }

        return $testUsers;
    }

    public function getScenarioContainer(): ContainerInterface
    {
        return self::getContainer();
    }

    /**
     * Should be enabled only in local env.
     *
     * Ignore complete steps on last test execution, it allows to run tests
     * faster without repassing on the working part.
     *
     * Should be commented in local to execute full step. This behaviour will
     * be ignored in dev env.
     */
    public function runOnlyMissingStepsInLocalEnv(
        bool $bool = true,
        string $envName = EnvironmentHelper::LOCAL
    ): void {
        // Only supported in standard wex env management.
        $localEnvFile = '../tmp/php.env.ini';

        if (is_file($localEnvFile)) {
            $config = parse_ini_file($localEnvFile);

            // Ignore non local env.
            if (isset($config['SITE_ENV'])
                && $config['SITE_ENV'] !== $envName) {
                return;
            }

            if (is_file($this->stepsTraceFile)) {
                $this->steps = json_decode(
                    file_get_contents(
                        $this->stepsTraceFile
                    ),
                    JSON_OBJECT_AS_ARRAY
                );

                // When deleting cache, recreate also users, then
                // keep it after first pass.
                $this->keepCreatedUser = $bool;
            }
        } else {
            return;
        }

        $this->runOnlyMissingStepsInLocalEnv = $bool;
    }

    public function step(
        string $name,
        callable $callback,
        callable $callbackIfCompleted = null
    ): mixed {
        $this->logTitle('STEP ____________ '.$name);
        $this->logIndentUp();

        $name = $this::class.'-'.$name;

        if ($this->runOnlyMissingStepsInLocalEnv) {
            if (isset($this->steps[$name]) && 'complete' === $this->steps[$name]['status']) {
                $response = $this->steps[$name]['response'];

                if ($callbackIfCompleted) {
                    $callbackIfCompleted($response);
                }

                $this->stepPreviousResponsesBag += is_array($response) ? $response : [];

                $this->logSecondary('  STEP END '.$name.' (from cache)');
                $this->logIndentDown();

                return null;
            }

            $response = $callback($this->stepPreviousResponsesBag);

            if (false !== $response) {
                $this->steps[$name] = [
                    'status' => 'complete',
                    'response' => $response,
                ];

                file_put_contents(
                    $this->stepsTraceFile,
                    json_encode(
                        $this->steps,
                        JSON_PRETTY_PRINT
                    )
                );

                // Ease removing.
                chmod(
                    $this->stepsTraceFile,
                    0777
                );

                $this->logSecondary('  STEP END '.$name.' (saved in cache)');
            } else {
                $this->logSecondary('  STEP END '.$name.' (not cached)');
            }
        } else {
            $response = $callback($this->stepPreviousResponsesBag);

            $this->logSecondary('  STEP END '.$name.' (no cache)');
        }

        $this->stepPreviousResponsesBag += is_array($response) ? $response : [];

        $this->logIndentDown();

        return $response;
    }

    protected function executeSteps(array $steps): void
    {
        $userPrevious = null;

        foreach ($steps as $stepName) {
            /** @var TestStep $step */
            $step = new $stepName($this);

            $this->step(
                $step::class,
                function ($previousResponse) use ($step, &$userPrevious) {
                    $step->setDataBag($previousResponse);

                    $this->logSecondary(
                        'Synopsis : '.$step->getSynopsis()
                    );

                    $username = $step->getUserName();
                    $mapUser = &$this->usersMap[$username];


                    $user = $this->initUserLogged(
                        username : $username,
                        sessionId: isset($mapUser['session']) ? $mapUser['session']->getId() : null
                    );

                    $mapUser['session'] = $this->getSession();

                    $response = $step->execute();

                    $this->logOutUser();

                    $userPrevious = $user;

                    return $response;
                }
            );
        }
    }
}
