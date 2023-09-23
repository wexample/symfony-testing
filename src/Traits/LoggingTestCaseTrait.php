<?php

namespace Wexample\SymfonyTesting\Traits;

use DateTime;
use Symfony\Component\DomCrawler\Crawler;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\TextHelper;
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

    public function log(
        string|array|object $message,
        string $color = TextHelper::ASCII_COLOR_WHITE,
        int $indent = null
    ): void {
        fwrite(
            STDERR,
            PHP_EOL . $this->formatLogMessage(
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
            $indent
        );
    }
    public function logArray($array)
    {
        $this->log(
            print_r($array, false)
        );
    }

    public function warn(string $message): void
    {
        $this->log(
            $message,
            33
        );
    }

    public function debugContent(Crawler $crawler = null): void
    {
        if (!$crawler) {
            $crawler = $this->getCurrentCrawler();
        }

        if (!$crawler) {
            $this->error('No crawler found in debug method !');
        }

        $body = $crawler->filter('body');

        $output = $body ? $body->html() : $this->content();

        echo PHP_EOL, '++++++++++++++++++++++++++',
        PHP_EOL, ' PATH :'.$this->client->getRequest()->getPathInfo(),
        PHP_EOL, ' CODE :'.$this->client->getResponse()->getStatusCode(),
        PHP_EOL;

        $exceptionMessagePosition = strpos($output, 'exception_message');
        $outputSuite = substr($output, $exceptionMessagePosition);
        if (false !== $exceptionMessagePosition) {
            preg_match(
                '/(?:exception_message">)([^<]*)(?:<\/span>)/',
                $outputSuite,
                $matches
            );
            echo ' Exception message : ', $matches[1];
            preg_match(
                '/<div class="block">.*?<\/div>/s',
                $outputSuite,
                $matches
            );

            echo PHP_EOL, ' Stack trace : ', PHP_EOL, $matches[0];
        } else {
            echo $output;
        }

        echo PHP_EOL, '++++++++++++++++++++++++++';
    }

    public function error(string $message, bool $fatal = true)
    {
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

        $output = $body ?: $this->getBody()
                // Error pages contains svg which breaks readability.
                .'<style> svg { display:none} </style>';

        if (!$quiet) {
            $this->info('See : '.$logFile);
        }

        file_put_contents(
            $logFile,
            'At '
            .(new DateTime())->format(DateHelper::DATE_PATTERN_TIME_DEFAULT)
            .'<br><br>'
            .$output
        );
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
}
