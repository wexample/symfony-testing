<?php

namespace Wexample\SymfonyTesting\Traits\Application;

use DOMElement;
use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

trait HtmlDocumentTestCaseTrait
{
    use ApplicationTestCaseTrait;

    /**
     * @return DOMElement|null
     */
    public function findOne(
        string $selector,
        int $index = 0
    ): ?DOMNode {
        $nodes = $this->find($selector);

        return $nodes->getNode($index);
    }

    public function getBody(?Crawler $crawler = null): string
    {
        $crawler ??= $this->getCurrentCrawler();
        $body = $crawler->filter('body');

        if ($body->count()) {
            return $body->html();
        }

        return $this->content();
    }

    public function nodeHasClass(
        $node,
        string $className
    ): bool {
        $classes = explode(' ', (string) $node->getAttribute('class'));

        return in_array($className, $classes);
    }

    public function assertNodeExists($selector): void
    {
        $node = $this->find($selector);

        $this->assertTrue(
            $node->count() > 0,
            'There is a node matching '.$selector
        );
    }

    public function find($selector): Crawler
    {
        return $this->getCurrentCrawler()->filter($selector);
    }
}
