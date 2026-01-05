<?php

/**
 * Created by PhpStorm.
 * User: weeger
 * Date: 09/02/19
 * Time: 20:00.
 */

namespace Wexample\SymfonyTesting\Traits\Form;

use App\Entity\SearchResult;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\RequestHelper;

trait SearchTestTrait
{
    /**
     * Create a string for sending to search api :
     * return only a relevant part of the searched string.
     */
    public function buildSearchString(string $source): string
    {
        return substr($source, 0, 15);
    }

    public function isFieldSearchable(\DOMElement $fieldNode): bool
    {
        return 'forms_themes-vue-entity-search'
            === $fieldNode->nextElementSibling->getAttribute('data-vue-com-name');
    }

    public function apiRequestSearch(
        string $searchString,
        string $action,
        string $searchEntityClass = null,
        ?int $max = 10,
    ): array {
        $parameters = [
            'q' => $searchString,
            'action' => $action,
            'max' => $max ?: 10,
            'display_format' => 'small',
        ];

        if ($searchEntityClass) {
            $parameters['entity'] = ClassHelper::getTableizedName($searchEntityClass);
        }

        $url = 'list?'.RequestHelper::buildQueryString($parameters);

        $this->log('Searching : '.$url);

        return $this->apiRequestsEntityCollectionMembers(
            SearchResult::class,
            $url
        );
    }
}
