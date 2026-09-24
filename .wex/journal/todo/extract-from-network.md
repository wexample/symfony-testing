# Extract testing primitives from network (multi-actor sessions, access matrix, cleanup)

Opened: 2026-09-24
Updated: 2026-09-24
Author: agent:archeology

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It was written by the 2026-09 network archaeology pass. Read it, then discuss it with the owner: every design choice and recommendation below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2` (entry point, order between packages), then `sources.md.j2` (where the legacy code lives: archive repo, branch checkouts, GitLab issues) and the domain page linked below.
> - Pending owner decisions affecting this work are listed in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/recap.md.j2`, section "Décisions qui t'attendent". Where this todo assumes an answer, treat it as an open question.
> - Safety: `NETWORK/local/network` runs on **production data** (real bookkeeping, real invoices in `var/`, a prod dump in `.wex/mysql/dumps/`) — read its code only, never run anything against it. Anonymize any fixture taken from network (bank exports, FEC, mails contain real names/accounts). Never copy secrets found in its history (Stripe keys, tokens, passwords, private keys).

## Goal

Make `symfony-testing` a host-agnostic, Symfony 7 compatible base on which the new `wexample/symfony-scenario` package (declarative multi-actor scenarios) can run, by porting the *working* multi-user session code from network's `develop-131-fos-user` branch, removing the network couplings that still live in this package, and adding a data-driven access matrix (successor of network's 227–366 "role ladder" test classes).

This todo is the **prerequisite** of `WEXAMPLE/NETWORK/archeo/proposed-packages/symfony-scenario/todo/extract-from-network.md`.

## Read first

- Knowledge page (sections "Inventory", "How it works", "State in target package(s)", "Recommended target design", "Pitfalls"): `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/testing.md.j2`
- Sources map: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/sources.md.j2`
- This package's own architecture notes (they list the broken imports): `.wex/knowledge/contributing/architecture.md.j2`
- Issues: #131 (FOSUser removal, test sessions rewrite), #174–#177 (per-role tests), #271 (re-login after role change), #311 (URL surveillance), #269/#213 (fixtures, not done).
- Design rules: `wex ai::design/rules --formatter php-code` from this directory.

## Decisions already implied by the owner

- The scenario engine goes to a separate package (`symfony-scenario`); this package keeps low-level primitives only (browser, sessions, assertions, exploration, syntax, access matrix).
- Nothing in `src/` may reference `App\…` any more (network is being rebuilt; `symfony-user` has no User model yet): use `Symfony\Component\Security\Core\User\UserInterface` and extension points.
- Symfony 7.4 / PHPUnit 11 (see `SERVICES/local/app-board`), strict phpunit config.

## Steps

1. **Fix loading blockers** (small, verifiable by `php -l` + a test that autoloads every class under `src/`):
   - `src/Tests/AbstractTestStep.php`: remove imports `App\Entity\User`, `App\Wex\BaseBundle\Tests\SymfonyTestCase`; type `$test` as `AbstractSymfonyTestCase`; declare `abstract public function getActorName(): string` (network forgot to declare `getUserName()`). Mark the class `@deprecated` in favour of symfony-scenario.
   - `src/Traits/LoggedUserTestCaseTrait.php`, `src/Traits/TextManipulationTestCaseTrait.php`: `App\Entity\User` → `UserInterface`; `getUsername()` → `getUserIdentifier()`.
   - `src/Tests/TestKernel.php`: drop the `App\Kernel` import/alias if unused by package tests.
   - `src/Traits/ControllerSyntaxTestCaseTrait.php`, `src/Traits/SplFileTestCaseTrait.php`: resolve `AbstractEntityController` (import from the package that owns it, or make it a parameter).
   - `composer.json`: add `autoload-dev` `Wexample\SymfonyTesting\Tests\` → `tests/` so the package tests run.
2. **Configurable user management** (replaces methods network provided on the concrete test class): add `src/Interface/TestUserProviderInterface.php` (`findOrCreate(string $identifier, array $roles, bool $forceRecreate): UserInterface`, `delete(UserInterface)`); `LoggedUserTestCaseTrait::initUserLogged()` calls `static::getTestUserProvider()` (abstract static or container service id `wexample_symfony_testing.test_user_provider`). Remove the implicit `createAndSaveUserIfNotExists()` / `$this->usersMap` dependencies.
   - Read: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/tests/Traits/Entity/UserTestTrait.php`, `.../tests/Traits/TestCase/LoggedUserTestCaseTrait.php`.
3. **Login/logout routes**: replace hardcoded `fos_user_security_login` / `fos_user_security_logout` in `src/Traits/Application/LoggedUserApplicationTestCaseTrait.php` with overridable methods defaulting to container parameters (`wexample_symfony_testing.login_route`, `.logout_route`), default `app_login`/`app_logout`.
   - Read: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/tests/Traits/TestCase/LoggedUserIntegrationTestCaseTrait.php`.
4. **Multi-actor sessions (the key port)**: create `src/Class/ActorSessionPool.php` (class, not trait): one `KernelBrowser`, a `CookieJar` snapshot per actor alias, `register(alias, ?UserInterface)`, `switchTo(alias)` (clear jar, restore snapshot, lazily `loginUser()` if the actor has no session cookie yet or is marked dirty), `markDirty(alias)` (after role/grant change, #271), `current()`, anonymous alias support (empty jar). Keep kernel reboot enabled (Symfony default). Then rewrite `src/Traits/SessionTestCaseTrait.php` as a thin wrapper around it and delete the `session`-service based code (removed in Symfony 6).
   - Read the working Symfony 6 version: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/tests/Traits/TestCase/SessionTestCaseTrait.php` (`storeUserSessionData`, `changeActiveUser`) and the test proving it: `.../tests/Integration/Crawl/SessionTestCase.php`.
   - Read the outdated one you are replacing: `src/Traits/SessionTestCaseTrait.php`, `src/Traits/Application/ScenarioTestCaseTrait.php` (history: commit `c26b9c6ce` vs `9327b202a`/`b08c65d38` in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/repo.git`).
5. **Scenario trait**: port the fos-user `ScenarioTestCaseTrait` logic onto `ActorSessionPool` only as a deprecated compatibility layer (named steps + actor switch + data bag, **no** JSON cache, no `../tmp/php.env.ini` sniffing); document that new code must use `symfony-scenario`.
   - Read: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/tests/Traits/TestCase/ScenarioTestCaseTrait.php`.
6. **Browser helpers parity**: add `goBack()`, `reload()`, `reloadAndCheckSame()`, `savePathPrevious()` history to `ApplicationTestCaseTrait`; keep `assertResponseIsForbiddenOrRedirectsToLoginPage()` but make the login route configurable (step 3).
   - Read: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/tests/Traits/TestCase/IntegrationTestCaseTrait.php` (lines 398–441).
7. **URL surveillance opt-in**: in `src/Tests/AbstractSymfonyTestCase.php::url()` guard the `assets/json/test-requests-log.json` / `public/.htaccess` logic behind `protected static bool $urlSurveillance = false` and configurable paths; add a test with a fixture kernel proving: new URL logged, changed URL without `RedirectMatch 301` fails, with rule passes.
8. **Access matrix**: add `src/Traits/Application/AccessMatrixTestCaseTrait.php` + `src/Class/AccessMatrix/*` value objects: input = array (or YAML file) `routes: {route: {params, xhr, expect: {ROLE_X: ok|forbidden|login|not_found|<int>}}}`, `roles_from: security.role_hierarchy` to inherit expectations along the hierarchy (use `RoleHelper` from symfony-helpers; network's `RoleHelper::flattenRolesConfig` is the reference), actors created through `TestUserProviderInterface`, params may be callables receiving the logged user. Reuse `ExplorationTestCaseTrait` internals (`exploreResolveMap` `extends` merge) instead of duplicating.
   - Read: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/tests/Integration/Exploration/WorkspaceExplorationTest.php`, `.../tests/Integration/Role/Worker/Controller/Entity/InvoiceControllerTest.php`, `.../tests/Integration/Role/Anonymous/Controller/Entity/InvoiceControllerTest.php`, `.../src/Service/Syntax/RoleSyntaxService.php`, `.../config/packages/security.yaml` (role_hierarchy).
9. **Role traits**: generalize `src/Traits/Application/Role/*` into one `RoleTestCaseTrait` with a `#[TestRole('ROLE_X')]` attribute or a constant, instead of one trait per role (network had 9 `Abstract<Role>TestCaseTrait`). Remove the duplicate `src/Tests/Traits/RoleAnonymousTestCaseTrait.php` vs `src/Traits/Application/Role/AnonymousTestCaseTrait.php` (keep one, deprecate the other; `symfony-api` tests use the former).
10. Update `README.md`/`.wex/knowledge` (architecture: remove the "What the package assumes of its host" list items that are fixed).

## Do not

- Do not copy network's `tests/Integration/Role/**` classes (227 in fos-user, 366 in step3 of which 47 are `assertTrue(false)` stubs) nor the `RoleSyntaxService` class generator.
- Do not port the step JSON cache (`./var/cache/testStepsCache.json`) nor `runOnlyMissingStepsInLocalEnv()`; checkpoints are redesigned in symfony-scenario.
- Do not port `MailTestTrait::resetMail()` (mass `UPDATE` of all mails) nor `MessagingTestCaseTrait` (sleep polling).
- Do not move `FormTestCaseTrait` here (goes to symfony-forms testing namespace) nor `ApiTestCaseTrait` (already in symfony-api).
- Never run anything against `NETWORK/local/network` (production data). Test only with the package `TestKernel`/fixture kernels.

## Acceptance criteria (tests inside this package)

- A test autoloads every class/trait under `src/` without `App\` classes present.
- Fixture-kernel app (`tests/Fixtures/App`) with a minimal in-memory user provider, form login, two roles, a `/whoami` JSON route and a `/admin` route:
  - `ActorSessionPoolTest`: alice (ROLE_USER) and bob (ROLE_ADMIN) alternate 3 times; `/whoami` always returns the active actor; anonymous gets redirected to login; `markDirty` + role change → next request reflects the new role.
  - `AccessMatrixTest`: `/admin` expected `ROLE_ANONYMOUS: login, ROLE_USER: forbidden, ROLE_ADMIN: ok` with ROLE_ADMIN inheriting from ROLE_USER only where not overridden.
  - `UrlSurveillanceTest` (step 7).
- `composer test` (phpunit) green with `failOnDeprecation`.
