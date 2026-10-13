## Scenarios: the application tested by the people who use it

A scenario test takes the parts of several people on one browser — a visitor, a member, an administrator — and checks, page by page and moment by moment, that each one gets what they should and nothing else. Two sources feed it:

- an **access matrix**: the pages each actor may open, refused how;
- a **BPMN process** (read with `wexample/php-bpmn`): its covering paths — a few that together take every flow, each loop gone round once (`PathFinder::cover()`) — played lane by lane. At every task, every actor opens the task's page — the lane's actors must get it, the others must be turned away —, then the lane's first actor does the task.

The process stays a plain description of who does what. Everything tied to the application lives in a **fixtures file** beside it.

## The fixtures file

```yaml
imports: [actors.yaml]          # merged first, this file wins
login_route: user_security_login # where an anonymous visitor is sent (default)
firewall: main                   # the firewall actors are logged in on (default)

actors:                          # alias → account; ~ is the anonymous visitor
  visitor: ~
  member: { user: scenario-member }
  admin: { user: scenario-admin, roles: [ROLE_ADMIN], with: { locale: fr } }

lanes:                           # BPMN lane id → actor(s); the first one does the tasks
  administrator: member
  newcomer: [teammate]

data:                            # created before the first step, removed after the test, in reverse
  editorial:
    factory: team                # a TestFixtureFactoryInterface, by getName()
    with: { name: Editorial, members: [{ user: '@actor.member', grants: [administrator] }] }

tasks:                           # BPMN task id → what it is in the application
  add_newcomer:
    route: user_memberships_index
    params: { id: 'team:${editorial.id}' }
    expect: { admin: ok }        # over the default: lane actors ok, every other actor denied
    do:
      submit:
        button: Add to the group
        fill: { 'membership_add_form[member]': '@actor.teammate.id' }
        capture: newcomer        # the route parameters of the page landed on, as @newcomer
    when:                        # the task decides a gateway: another way, by the flow taken next —
                                 # read at each occurrence, so a task met twice on a loop may decide otherwise
      flow_refused: { submit: { button: Refuse } }
  remove_newcomer:
    do: { php: removeNewcomer }  # a method of the test: ($context, $step, $path)

access:                          # label (or route name) → who meets what
  user_accounts_index: { expect: { visitor: login, member: forbidden, admin: ok } }
  team members:
    route: user_memberships_index
    params: { id: 'team:${editorial.id}' }
    expect: { member: ok, outsider: not_found }
```

## Exploring: the smoke test

```yaml
explore:                         # actor → how it walks the application
  member:
    start: [home_index, /api/]   # routes or paths
    links: 'nav a, .doc-section--code a'   # the links followed, from every page met
    depth: 2                     # clicks away from a start page
    skip: ['^/_', '^/build/', 'logout']    # paths not followed (the defaults)
    max: 200                     # pages at most
```

Each page met, once: a server error is reported with the exception it names; an HTML page showing a translation key left raw (`@page::title`, `SomeBundle.pages.x::key`) is reported — templates rendered later by a script and code shown as code are not read —; a response saying it is JSON must decode. Redirects are answers, not followed. The API documentation page of symfony-api is a good start: its example links call every endpoint. The successor of network's `explore()` route maps, written as data.

**Expectations**: `ok` (shown, or redirected anywhere but the login), `login` (sent to `login_route`), `forbidden` (403), `not_found` (404: hidden rather than refused), `denied` (any of the three). A server error matches none.

**References**: `@name` and `@name.path` read what `data` created or a step captured (entities are read again by the current Doctrine manager); `@actor.alias` is an actor's user; `${…}` interpolates inside a longer text; `@@` escapes a leading `@`.

**Tasks**: `visit` (the default: opening the page is the task), `submit` (the form holding `button`; a value for a field the page does not render — a script-drawn search input — is sent all the same), `php`. A human task (`task`, `userTask`, `manualTask`) of a lane left without an entry stops the run: nothing a person does goes untested. Automatic tasks and events are the application's own and are skipped.

## The test

One class per application runs every scenario of a directory: each `name.bpmn` is played path by path with the `name.yaml` beside it, each `*.yaml` holding an `access` section is checked as a matrix, and each holding an `explore` section is walked as a smoke test. A new scenario is a new pair of files, no code.

```php
class ScenariosTest extends AbstractScenarioDirectoryTestCase
{
    protected static function getScenarioDirectory(): string
    {
        return __DIR__;
    }

    protected function getActorProvider(): TestActorProviderInterface
    {
        return new DemoActorProvider();   // provide() an account per actor, release() it after
    }

    protected function getFixtureFactories(): iterable
    {
        return [new DemoTeamFactory()];   // one per `factory:` name the files use
    }

    // The methods `do: { php: … }` tasks call: ($context, $step, $path).
    public function removeNewcomer(ScenarioContext $context): void { … }
}
```

A fixtures file without a `.bpmn` of the same name and without `access` — a shared `actors.yaml` — is only imported. A `.bpmn` without its `.yaml` fails the suite.

`AbstractScenarioTestCase`, which it extends, is there for a scenario written by hand: `playBpmnPath($bpmn, $yaml, $path)`, `assertAccessMatrix($yaml)`, `bpmnPathsOf($bpmn, $yaml)` for a data provider, `startScenario($yaml)` for a context to drive yourself.

One PHPUnit test per path, named after the file and what the path is there to show (`team-membership › keep → remove`, `note-review › verdict → default ↺` for a loop). Access mismatches are gathered and reported together; a task that cannot be done stops the path. Either way the failure prints every request made, by whom, and what came back:

```
[keep → remove] step 7 "Check the team": teammate expected not_found on user_memberships_index, met ok (200)

Requests:
  member submits "Add to the group" on user_memberships_index → 200
  teammate GET /en/memberships/team:01a1… → 200
```

`ScenarioContext` is also usable by hand: `as($alias)`, `open($route, $params)`, `submit(…)`, `checkAccess(…)`, `capture($name)`, `set()` / `get()`, `getUser($alias)`, `getContainer()`.

## Each actor its own session

`ActorSessionPool` keeps one cookie jar per actor on the single kernel browser: switching back to an actor puts its cookies back, an actor without any is logged in with `loginUser()` — no login form, no second factor —, an anonymous one starts empty. `markDirty($alias)` forgets a session after a change of roles, so the next turn logs in again.

## The database

Each test creates its accounts and data through the provider and the factories and removes them after, even when it fails: the test database is left as it was found. The application needs a test database with its schema (`doctrine:schema:create --env=test`).

## Recipe: adding a scenario to an application

1. **Once per application**: a scenario directory (`tests/Scenario/`), its `ScenariosTest` as above, an actor provider (`TestActorProviderInterface`: create the account an actor stands for, with what the application requires before letting it in — accepted terms, no second factor —, delete it in `release()`), one factory per kind of data, a shared `actors.yaml`, and a test database with its schema. The design-system app of MOJOE has all of it: `tests/Scenario/`.
2. **The process**: `name.bpmn`, drawn in any BPMN 2.0 tool or written by hand (php-bpmn's usage page has the skeleton). Lanes are the people, `userTask`s what they do, an exclusive gateway with a `default` flow each choice. Ids matter: the fixtures name lanes, tasks and flows by them. Only what concerns the test needs drawing.
3. **The fixtures**: `name.yaml` beside it, `imports: [actors.yaml]`, then `lanes` (every lane with a human task), `data`, and one `tasks` entry per human task: its `route` and `params`, the `expect` exceptions to "lane ok, everyone else denied", and how it is done.
4. **Run it**, read the report: every line is an access that differs from the rule, or the task that could not be done, with the requests that led there. Decide each time whether the application or the rule is wrong; never loosen an expectation only to make it pass.
5. **Code only when the file cannot say it**: a value only the database knows (an id the pages never show), a mail to read. Write it as a method of `ScenariosTest` and call it with `do: { php: method }`.

To find a form's field names and buttons, open the page as the actor in a throwaway test (`$context->as('member')->open(…)`, then the crawler) and delete it after.

## Not to confuse with

symfony-bpmn's `BpmnScenarioService::play()` runs a process **inside** the application, on its runner, to test the process. These scenarios test **the application** through its pages, by HTTP, against a process: the application does not need symfony-bpmn, nor to run the process at all.
