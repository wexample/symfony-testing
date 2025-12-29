<?php

namespace Wexample\SymfonyTesting\Tests\Logs;

use Wexample\SymfonyTesting\Tests\AbstractSymfonyTestCase;

class TemplatingTest extends AbstractSymfonyTestCase
{
    public function testLogsTemplating(): void
    {
        $this->logTitle('Testing checkboxes');

        // Checkboxes.
        $this->logIndentUp();
        $this->logSuccessCheckbox('Test success');
        $this->logErrorCheckbox('Test fail');
        $this->logIndentDown();

        // Colors.
        $this->logTitle('Testing colors');
        $this->logIndentUp();
        foreach ($this->getAllLogColors() as $color) {
            $this->log(
                'Test string',
                $color
            );
        }
        $this->logIndentReset();

        // Errors
        $this->logTitle('Testing errors');

        try {
            throw new \Exception('Test error');
        } catch (\Exception $e) {
            $this->error(
                $e->getMessage(),
                false
            );
        }

        $this->assertTrue(true);
    }
}
