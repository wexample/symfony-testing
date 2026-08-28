## Architecture

The package ships no services and no configuration. `WexampleSymfonyTestingBundle` is an empty `extends AbstractBundle`, and everything else is consumed by inheritance (`extends AbstractSymfonyTestCase`) or by trait composition (`use ExplorationTestCaseTrait`). There is nothing to wire: a host project's test class picks a base class and mixes in the traits it needs.

### Three directories

src/Tests holds the abstract test cases and the kernels — the classes a project extends. src/Traits holds the behaviour, split by concern, each trait pulling in its own prerequisites through `use`. src/Helper holds one stateless class, `TestControllerHelper`, which maps controller class names to test class names and back.

### The base class chain

`AbstractWebTestCase` extends Symfony's `WebTestCase`, mixes in `LoggingTestCaseTrait`, carries `protected bool $hasRequested = false;` and declares two abstract methods the rest of the package relies on:

```php
abstract public function url($route, array $args = []): string;
abstract public function getStorageDir(string $name = null): string;
```

`AbstractSymfonyTestCase` implements them. Its `url()` does more than generate a route: it re-generates the route with dummied arguments, keys the result by `md5(json_encode($logEntry))` into `assets/json/test-requests-log.json`, and when a previously logged URL has changed it looks for a matching `RedirectMatch 301 ^$urlFrom$ $urlTo` line in the host's `public/.htaccess`, failing the test if none exists. Every navigation helper in the package routes through this method, so every route a test visits ends up under that surveillance.

Three classes sit on top. `AbstractApplicationTestCase` adds only `use ApplicationTestCaseTrait`. `AbstractRoleControllerTestCase` adds `RoleTestCaseTrait` and `ControllerTestCaseTrait`, calls `$this->createGlobalClient()` in `setUp()`, and resolves its controller through `TestControllerHelper::buildControllerClassPath(static::class)`. `AbstractSymfonyKernelTestCase` only snapshots the global error and exception handlers in `setUp()` and restores them in `tearDown()`.

### Trait layering

Traits declare their dependencies by `use`-ing each other, so a test class names one trait and gets the stack. `ControllerTestCaseTrait` → `HtmlDocumentTestCaseTrait` → `ApplicationTestCaseTrait`. `ExplorationTestCaseTrait` and `ScenarioTestCaseTrait` both pull `LoggedUserApplicationTestCaseTrait`, which pulls `LoggedUserTestCaseTrait` and `SessionTestCaseTrait`. `ControllerSyntaxTestCaseTrait` pulls `SplFileTestCaseTrait` and `ClassTestCaseTrait`. `LoggingTestCaseTrait` pulls `ConsoleLoggerTrait` from `symfony-helpers` — that is where `logTitle()`, `logIndentUp()` and `formatLogMessage()` come from.

`ApplicationTestCaseTrait` owns the browser: `protected ?KernelBrowser $client`, created by `createGlobalClient()` which calls `static::ensureKernelShutdown()` then `static::createClient()` and resets `$this->hasRequested`.

### Path of a navigation call

`goToControllerRouteAndCheckHtml('view')` in `ControllerTestCaseTrait` resolves the route name via `static::getControllerClass()::buildRouteName($routeName)`, then hands off to `goToRouteAndCheckHtml()` in `ApplicationTestCaseTrait`, which chains `goToRouteAndCheckStatusCode()` → `goToRoute()`. `goToRoute()` converts the route through `$this->url()` — the logging implementation above — and calls `go()`, which saves the current URI into `$this->pathPrevious` and calls `requestGet()`. That sets `$this->hasRequested = true` and issues `$this->client->request(Request::METHOD_GET, ...)`. Control returns up the chain to `assertStatusCodeEquals()`, which calls `logBodyExtract()` before failing (deliberately not `assertResponseStatusCodeSame()`, "as logged trace is far too long"), and finally to `assertPageBodyHasNotOrphanTranslationKey()` from `HtmlDocumentTestCaseTrait`, which regex-scans the crawler's `body` for untranslated `domain::key` patterns inside tags and inside attributes.

### The kernels

`TestKernel` is a self-contained `MicroKernelTrait` kernel for testing the package and its siblings without a host application: four bundles (Framework, Doctrine, Twig, `WexampleSymfonyHelpersBundle`), Doctrine on `pdo_sqlite` in memory, a hardcoded `security.role_hierarchy.roles`, no routes, and four `symfony-helpers` services registered `->setPublic(true)` so tests can pull them out of the container. It aliases `Kernel::class` to itself.

`AbstractFixtureKernel` extends it for tests that need a small on-disk app: subclasses implement `getFixtureDir()` and optionally `getExtraBundles()`, `getConfigFiles()`, `getRoutesControllersDir()`. It overrides `getProjectDir()` to return the fixture directory and puts the cache under `<fixture>/var/cache/<env>`, falling back to the parent's temp directory if that cannot be created.

### Naming conventions as executable rules

`TestControllerHelper` encodes the suite's class layout. `buildClassBundleNamespace()` finds the root namespace — the bundle namespace if the class uses `BundleClassTrait`, `App` otherwise, an exception if neither. From there `buildTestControllerClassPath()` and `buildControllerClassPath()` translate between `<Root>\Controller\…\FooController` and `<Root>\Tests\Application\Role\<Role>\…\FooControllerTest` using `ClassHelper::getCousin()`, with the path fragments held as constants on `AbstractRoleControllerTestCase` (`APPLICATION_TEST_CLASS_PATH_REL = 'Tests\\'`, `APPLICATION_ROLE_TEST_CLASS_PATH_REL = 'Application\\Role\\'`).

`ControllerSyntaxTestCaseTrait` enforces the same conventions by walking files: `forEachClassFileRecursive()` iterates `src/<subdir>` with a `RecursiveIteratorIterator`, `buildClassNameFromSpl()` turns each real path into an `App\…` class name, and the callback asserts the suffix, the position under `Controller\Entity\`, and the existence of the matching entity. `scanControllerPagesTemplates()` runs the mirror check from the templates side: every `.html.twig` under the pages directory must have a controller class and a camel-cased method of the same name.

### What the package assumes of its host

The traits are written against a specific application shape and will not compile in isolation. `LoggedUserTestCaseTrait` and `TextManipulationTestCaseTrait` type-hint `App\Entity\User`; the `Form` traits type-hint `App\Entity\SearchResult`; `TestKernel` imports `App\Kernel`; `AbstractTestStep` still imports `App\Wex\BaseBundle\Tests\SymfonyTestCase`, a namespace no longer present in the suite. Several called methods are supplied by neither the package nor Symfony — `createAndSaveUserIfNotExists()`, `assertRedirectionIsToLoginPage()`, `createApplication()`, `apiRequestsEntityCollectionMembers()`, `formFind()`, `$this->usersMap` — and must exist on the concrete test class. Runtime paths are hardcoded too: `./var/cache/testStepsCache.json` for scenario step caching, `../tmp/php.env.ini` read for `SITE_ENV`, `fos_user_security_login` / `fos_user_security_logout` as login routes, and the container parameter `api_test_error_log_length` for the truncation length of logged response bodies.

Two identifiers resolve to nothing: `AbstractEntityController`, referenced in `ControllerSyntaxTestCaseTrait` and `SplFileTestCaseTrait`, has no `use` statement and no class of that name in the trait namespace.

### The package's own tests

tests holds two files, `Syntax/ControllersTest.php` and `Logs/TemplatingTest.php`, both extending the package's own base classes. composer.json maps `Wexample\SymfonyTesting\` to `src/` only and declares no `autoload-dev`, so these files are not covered by the autoloader as it stands.
