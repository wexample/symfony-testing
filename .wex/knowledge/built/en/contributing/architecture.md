## Architecture

The package ships no services and no configuration. `WexampleSymfonyTestingBundle` is an empty `extends AbstractBundle`, and everything else is consumed by inheritance (`extends AbstractSymfonyTestCase`) or by trait composition (`use ControllerTestCaseTrait`). There is nothing to wire: a host project's test class picks a base class and mixes in the traits it needs. The scenario engine — src/Class/Scenario, driven by `AbstractScenarioTestCase` and `AbstractScenarioDirectoryTestCase` — is plain classes too; its usage is the scenarios page.

### Directories

src/Tests holds the abstract test cases and the kernels — the classes a project extends. src/Class/Scenario, src/Enum and src/Interface hold the scenario engine. src/Traits holds the behaviour, split by concern, each trait pulling in its own prerequisites through `use`. src/Helper holds one stateless class, `TestControllerHelper`, which maps controller class names to test class names and back.

### The base class chain

`AbstractWebTestCase` extends Symfony's `WebTestCase`, mixes in `LoggingTestCaseTrait`, carries `protected bool $hasRequested = false;` and declares two abstract methods the rest of the package relies on:

```php
abstract public function url($route, array $args = []): string;
abstract public function getStorageDir(string $name = null): string;
```

`AbstractSymfonyTestCase` implements them. With `protected static bool $urlSurveillance = true` on a test case, its `url()` does more than generate a route: it re-generates the route with dummied arguments, keys the result by `md5(json_encode($logEntry))` into `assets/json/test-requests-log.json`, and when a previously logged URL has changed it looks for a matching `RedirectMatch 301 ^$urlFrom$ $urlTo` line in the host's `public/.htaccess`, failing the test if none exists. Every navigation helper in the package routes through this method, so a test case that switches it on has every route it visits under that surveillance. It is off by default.

Three classes sit on top. `AbstractApplicationTestCase` adds only `use ApplicationTestCaseTrait`. `AbstractRoleControllerTestCase` adds `RoleTestCaseTrait` and `ControllerTestCaseTrait`, calls `$this->createGlobalClient()` in `setUp()`, and resolves its controller through `TestControllerHelper::buildControllerClassPath(static::class)`. `AbstractSymfonyKernelTestCase` only snapshots the global error and exception handlers in `setUp()` and restores them in `tearDown()`.

### Trait layering

Traits declare their dependencies by `use`-ing each other, so a test class names one trait and gets the stack. `ControllerTestCaseTrait` → `HtmlDocumentTestCaseTrait` → `ApplicationTestCaseTrait`. `ControllerSyntaxTestCaseTrait` pulls `SplFileTestCaseTrait` and `ClassTestCaseTrait`. `LoggingTestCaseTrait` pulls `ConsoleLoggerTrait` from `symfony-helpers` — that is where `logTitle()`, `logIndentUp()` and `formatLogMessage()` come from.

`ApplicationTestCaseTrait` owns the browser: `protected ?KernelBrowser $client`, created by `createGlobalClient()` which calls `static::ensureKernelShutdown()` then `static::createClient()` and resets `$this->hasRequested`.

### Path of a navigation call

`goToControllerRouteAndCheckHtml('view')` in `ControllerTestCaseTrait` resolves the route name via `static::getControllerClass()::buildRouteName($routeName)`, then hands off to `goToRouteAndCheckHtml()` in `ApplicationTestCaseTrait`, which chains `goToRouteAndCheckStatusCode()` → `goToRoute()`. `goToRoute()` converts the route through `$this->url()` — the logging implementation above — and calls `go()`, which saves the current URI into `$this->pathPrevious` and calls `requestGet()`. That sets `$this->hasRequested = true` and issues `$this->client->request(Request::METHOD_GET, ...)`. Control returns up the chain to `assertStatusCodeEquals()`, which calls `logBodyExtract()` before failing (deliberately not `assertResponseStatusCodeSame()`, "as logged trace is far too long"). A `domain::key` left untranslated is not looked for in the page: the strict translator of `symfony-translations`, on in the test environment, throws while rendering it.

### The kernels

`TestKernel` is a self-contained `MicroKernelTrait` kernel for testing the package and its siblings without a host application: four bundles (Framework, Doctrine, Twig, `WexampleSymfonyHelpersBundle`), Doctrine on `pdo_sqlite` in memory, a hardcoded `security.role_hierarchy.roles`, no routes, and four `symfony-helpers` services registered `->setPublic(true)` so tests can pull them out of the container. It aliases `Kernel::class` to itself.

`AbstractFixtureKernel` extends it for tests that need a small on-disk app: subclasses implement `getFixtureDir()` and optionally `getExtraBundles()`, `getConfigFiles()`, `getRoutesControllersDir()`. It overrides `getProjectDir()` to return the fixture directory and puts the cache under `<fixture>/var/cache/<env>`, falling back to the parent's temp directory if that cannot be created.

### Naming conventions as executable rules

`TestControllerHelper` encodes the suite's class layout. `buildClassBundleNamespace()` finds the root namespace — the bundle namespace if the class uses `BundleClassTrait`, `App` otherwise, an exception if neither. From there `buildTestControllerClassPath()` and `buildControllerClassPath()` translate between `<Root>\Controller\…\FooController` and `<Root>\Tests\Application\Role\<Role>\…\FooControllerTest` using `ClassHelper::getCousin()`, with the path fragments held as constants on `AbstractRoleControllerTestCase` (`APPLICATION_TEST_CLASS_PATH_REL = 'Tests\\'`, `APPLICATION_ROLE_TEST_CLASS_PATH_REL = 'Application\\Role\\'`).

`ControllerSyntaxTestCaseTrait` enforces the same conventions by walking files: `forEachClassFileRecursive()` iterates `src/<subdir>` with a `RecursiveIteratorIterator`, `buildClassNameFromSpl()` turns each real path into an `App\…` class name, and the callback asserts the suffix, the position under `Controller\Entity\`, and the existence of the matching entity. `scanControllerPagesTemplates()` runs the mirror check from the templates side: every `.html.twig` under the pages directory must have a controller class and a camel-cased method of the same name.

### What the package assumes of its host

Everything under `src/` loads without the host application (`tests/Integration/AutoloadTest.php` proves it). What is left of the host's shape is convention, not code: the syntax traits (`ControllerSyntaxTestCaseTrait`, `SplFileTestCaseTrait`) and `AbstractRoleControllerTestCase` name the host's own `App\…` namespace as the one they walk, and `TestKernel` aliases `App\Kernel` to itself so services typed on it resolve. `AbstractEntityController`, referenced by the syntax traits, has no class of that name in the suite.

network's multi-user session layer (`SessionTestCaseTrait`, `LoggedUserTestCaseTrait`, the step-based `ScenarioTestCaseTrait` and `AbstractTestStep`) and its `explore()` route maps (`ExplorationTestCaseTrait`) were removed on 2026-10-09: the scenario engine replaces them (`ActorSessionPool`, BPMN scenarios, the `access` and `explore` sections).

### The package's own tests

tests runs on a fixture application (`tests/Fixtures/App`: a memory user provider, notes, a review process, pages to explore) through `phpunit.xml.dist`; `autoload-dev` maps `tests/Fixtures` and `tests/Integration`.
