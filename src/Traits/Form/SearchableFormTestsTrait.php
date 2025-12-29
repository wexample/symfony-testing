<?php

/**
 * Created by PhpStorm.
 * User: weeger
 * Date: 09/02/19
 * Time: 20:00.
 */

namespace Wexample\SymfonyTesting\Traits\Form;

use App\Entity\SearchResult;
use Symfony\Component\DomCrawler\Form;

trait SearchableFormTestsTrait
{
    use SearchTestTrait;

    /**
     * When filling searchable value, the client changes due to the api call,
     * and the form is emptied, so we must execute this method before filling any field.
     */
    public function formFillSearchableValues(
        array $fields,
        string $formClassName
    ): array {
        $form = $this->formFind($formClassName);
        $currentPath = $this->getCurrentPath();

        $searchFieldsValues = $this->formFieldsGetSearchableValues(
            $fields,
            $form
        );

        $this->go($currentPath);
        $form = $this->formFind($formClassName);

        foreach ($searchFieldsValues as $fieldName => $result) {
            $this->fieldSetValue(
                $fieldName,
                $result,
                $form
            );
        }

        return $searchFieldsValues;
    }

    public function formFieldsGetSearchableValues(
        array $fields,
        Form $form
    ): array {

        $searchableFields = [];
        foreach ($fields as $fieldName => $searchString) {
            $fieldNode = $this->formGetNode($fieldName, $form);

            if ($this->isFieldSearchable($fieldNode)) {
                $searchableFields[$fieldName] = (object) [
                    'id' => $fieldNode->getAttribute('id'),
                    'string' => $searchString,
                ];
            }
        }

        $resultsValues = [];
        foreach ($searchableFields as $fieldName => $options) {
            $searchResults = $this->apiRequestSearch(
                $options->string,
                $options->id,
            );

            $this->assertNotEmpty(
                $searchResults,
                'The searchable field with id #'
                .$options->id.', searching "'.$options->string.'" should not be empty'
            );

            /** @var SearchResult $searchResult */
            $searchResult = current($searchResults);
            $resultsValues[$fieldName] = $searchResult->id;
        }

        return $resultsValues;
    }
}
