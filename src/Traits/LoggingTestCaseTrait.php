<?php

namespace Wexample\SymfonyTesting\Traits;

use DateTime;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DomCrawler\Crawler;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Traits\ConsoleLoggerTrait;
use function file_put_contents;
use function fwrite;
use function is_dir;
use function is_file;
use function mkdir;
use function preg_match;
use function print_r;
use function strpos;
use function substr;
use function unlink;

/**
 * Trait LoggingTestCase
 * Various debug and logging helper methods.
 */
trait LoggingTestCaseTrait
{
    use ConsoleLoggerTrait;
    use FileManipulationTestCaseTrait;

    public function log(
        string|array|object|null $message,
        string $color = TextHelper::ASCII_COLOR_WHITE,
        int $indent = null
    ): void {
        fwrite(
            STDERR,
            PHP_EOL.$this->formatLogMessage(
                $message,
                $color,
                $indent
            )
        );
    }

    public function logSecondary(
        string|array|object $message,
        int $indent = null
    ): void {
        $this->log(
            $message,
            TextHelper::ASCII_DARK_COLOR_GRAY,
            $indent ?: $this->logIndentCursor + 1,
        );
    }

    public function logArray($array): void
    {
        $this->log(
            print_r(
                $array,
                true
            )
        );
    }

    public function success(string $message): void
    {
        $this->log(
            $message,
            TextHelper::ASCII_COLOR_GREEN
        );
    }

    public function warn(string $message): void
    {
        $this->log(
            $message,
            TextHelper::ASCII_COLOR_YELLOW
        );
    }

    public function error(
        string $message,
        bool $fatal = true
    ): void {
        $this->log(
            $message,
            31
        );
        if ($fatal) {
            $this->fail($message);
        }
    }

    public function debugWrite(
        $body = null,
        $fileName = 'phpunit.debug.html',
        $quiet = false
    ): void {
        $tmpDir = $this->initTempDir();

        $logFile = $tmpDir.$fileName;

        if (is_file($logFile)) {
            unlink($logFile);
        }

        $output = $body ?: $this->content()
            // Error pages contains svg which breaks readability.
            .'<style> svg { display:none; } </style>';

        file_put_contents(
            $logFile,
            'At '
            .(new DateTime())->format(DateHelper::DATE_PATTERN_TIME_DEFAULT)
            .'<br><br>'
            .$output
        );

        if (!$quiet) {
            $this->info('See : '.$logFile);
            $this->logIfErrorPage($body);
        }
    }

    public function logIfErrorPage($body = null): void
    {
        $crawler = new Crawler($body ?: $this->content());
        $nodeList = $crawler->filter('h1.exception-message');

        if ($nodeList->count()) {
            $errorMessage = $nodeList->text();
            $this->error($errorMessage, false);
        }
    }

    public function initTempDir(): string
    {
        $tmpDir = $this->getStorageDir('tmp');

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        return $tmpDir;
    }

    public function info(string $message): void
    {
        $this->log(
            $message,
            34
        );
    }

    public function logBodyExtract(
        int $indent = null
    ): void {
        /** @var ParameterBagInterface $parameterBag */
        $parameterBag = self::getContainer()->get(ParameterBagInterface::class);

        $this->logSecondary(
            substr(
                $this->getBody(),
                0,
                $parameterBag->get('api_test_error_log_length') ?: 1000
            ),
            $indent
        );
    }
}
