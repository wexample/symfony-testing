<?php

namespace Wexample\SymfonyTesting\Traits\Application;

use DOMElement;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyHelpers\Helper\RoleHelper;

trait ExplorationTestCaseTrait
{
    use LoggedUserApplicationTestCaseTrait;
    use HtmlDocumentTestCaseTrait;

    private string $EXPLORE_OPTION_LISTEN = 'listen';
    private string $EXPLORE_OPTION_ARGS = 'args';

    /**
     * Make crawler visit the website following the given map array.
     */
    public function explore(
        array $map,
        array $options = []
    ): void {
        $map = $this->exploreResolveMap($map);

        foreach ($map as $role => $roleMap) {
            $this->log('Exploring role '.$role);
            $this->logIndentUp();

            $this->logOutUser();

            if (RoleHelper::ROLE_ANONYMOUS !== $role) {
                $this->initUserLogged(
                    roles: $roleMap['roles'] ?? [$role],
                    forceRecreate: true,
                );

                $this->goToHome();
            } else {
                $this->createGlobalClient();
            }

            $this->exploreRole($roleMap, $options);
            $this->logIndentDown();
        }
    }

    protected function exploreRole(
        $map,
        array $options = []
    ): void {
        // Explore selectors.
        if (isset($map['selectors'])) {
            foreach ($map['selectors'] as $selector => $selectorOptions) {
                $this->goToRoute($selectorOptions['route']);

                $this->log('Exploring selector '.$selector.' ... ');

                $this->exploreEachLink(
                    $selector,
                    function () use (
                        &$map
                    ) {
                        $key = $this->client->getRequest()->get('_route');
                        // Check if current route has a map
                        if (isset($map[$this->EXPLORE_OPTION_LISTEN][$key])) {
                            $pathCurrent = $this->getCurrentPath();

                            $this->log('Explore child route '.$key);
                            $this->exploreRole($map[$this->EXPLORE_OPTION_LISTEN][$key]);
                            unset($map[$this->EXPLORE_OPTION_LISTEN][$key]);

                            // Go back
                            $this->log('Go back to parent path');
                            $this->go($pathCurrent);
                        }
                    },
                    $options
                );
            }
        }

        if (isset($map['routes'])) {
            $this->logIndentUp();

            // Visit extra routes.
            foreach ($map['routes'] as $key => $options) {
                if (is_array($options)) {
                    $args = [];

                    if (isset($options[$this->EXPLORE_OPTION_ARGS])) {
                        $args = is_callable($options[$this->EXPLORE_OPTION_ARGS])
                            ? $options[$this->EXPLORE_OPTION_ARGS]()
                            : $options[$this->EXPLORE_OPTION_ARGS];
                    }

                    $this->goToRoute($key, $args);

                    $this->assertStatusCodeEquals(
                        $options['code'] ?? Response::HTTP_OK
                    );
                } else {
                    $this->goToRoute($options);
                    $this->assertStatusCodeOk();
                }
            }

            $this->logIndentDown();
        }

        if (isset($map['paths'])) {
            $this->logIndentUp();

            // Visit extra paths.
            foreach ($map['paths'] as $key => $options) {
                if (is_array($options)) {
                    $this->go($key);
                    $this->assertStatusCodeOk();
                    $this->exploreRole($options);
                } else {
                    $this->go($options);
                    $this->assertStatusCodeOk();
                }
            }

            $this->logIndentDown();
        }
    }

    /**
     * Find all "a" links and visit non-external pages from it.
     */
    public function exploreEachLink(
        $selector,
        callable $callback = null,
        array $options = []
    ): void {
        $links = $this->find($selector);
        $cache = [];
        $this->logIndentUp();

        /** @var DOMElement $link */
        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            $this->log(
                'Found link href '.$href,
            );

            // Prevent duplicates.
            if (! isset($cache[$href])) {
                $cache[$href] = true;
                // Ignore external links and page internal anchors.
                if (! str_starts_with($href, 'http')
                    && '#' !== $href[0]
                    && 'javascript:void(0);' !== $href
                    // Ignore logout path.
                    && $href !== $this->getUserLogoutPath()
                ) {
                    $this->go($href);

                    if ($callback) {
                        if ($options['checkMissingTranslations'] ?? false) {
                            $this->assertPageBodyHasNotOrphanTranslationKey();
                        }

                        $callback();
                    }

                    if ($options['debugWrite'] ?? false) {
                        $this->debugWrite();
                    }

                    $this->assertStatusCodeOk();
                } else {
                    $this->logSecondary(
                        'Unable to explore path, ignore.',
                    );
                }
            } else {
                $this->logSecondary(
                    'Already explored, ignore.',
                );
            }
        }

        $this->logIndentDown();
    }

    protected function exploreResolveMap(
        array &$map,
        array &$output = null
    ): array {
        if (is_null($output)) {
            $output = $map;
        }

        foreach ($map as $role => $mapItem) {
            $map[$role] = $this->exploreResolveMapItem($mapItem, $map);
        }

        return $map;
    }

    protected function exploreResolveMapItem(
        array $mapItem,
        array &$map
    ): array {
        if (isset($mapItem['extends'])) {
            foreach ($mapItem['extends'] as $role) {
                $map[$role] = $this->exploreResolveMapItem($map[$role], $map);

                foreach (array_keys($map[$role]) as $type) {
                    $mapItem[$type] = array_merge(
                        $map[$role][$type],
                        $mapItem[$type] ?? []
                    );
                }
            }

            unset($mapItem['extends']);
        }

        return $mapItem;
    }
}
