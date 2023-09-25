## Testing in your project

### Add to Composer

In composer.json

    "autoload-dev": {
        "psr-4": {
            "Wexample\\SymfonyTesting\\Tests\\": "vendor/wexample/symfony-testing/tests/"
        }
    },

### Add to PhpUnit

In phpunit.xml.dist

    <testsuites>
        <testsuite name="Testing Test Suite">
            <directory>vendor/symfony-testing/tests</directory>
        </testsuite>
    </testsuites>