<?php

namespace NSWDPC\Search\Typesense\Services;

use ElliotSawyer\SilverstripeTypesense\Collection;
use ElliotSawyer\SilverstripeTypesense\Typesense;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;

/**
 * Typesense search handler
 */
class SearchHandler {

    use Injectable;

    protected int $pageLength = 0;

    protected int $perPage = 10;

    public function  __construct(int $pageLength = 0, int $perPage = 10) {

    }

    public static function escapeString(string $string): string {
        if(str_contains($string, "`")) {
            return "`" . str_replace("`", "\\`", $string) . "`";
        } else {
            return $string;
        }
    }


    public static function escapeArray(array $array): array {
        $result = [];
        foreach($array as $string) {
            if(!is_string($string)) {
                continue;
            }
            $result[] = self::escapeString($string);
        }
        return $result;
    }

    /**
     * do a search using the input values from a form and the model used for configuration
     * @param array $typesenseArgs extra search arguments to do a custom search beyond what can be automatically determined
     */
    public function doSearch(Collection $collection, array|string $searchQuery, array $typesenseArgs = []): ?ArrayList {
        $client = Typesense::client();
        $collectionName = '';
        if($collection instanceof Collection) {
            $collectionName = (string)$collection->Name;
        }
        $results = ArrayList::create();
        if($collectionName === '') {
            return null;
        }

        $fieldsForSearch = $collection->Fields()->filter(['index' => 1])->column('name');
        if($fieldsForSearch === []) {
            return null;
        }

        $search = [];
        if(is_string($searchQuery)) {
            $searchParameters = [
                'q' => $searchQuery,
                'query_by' => implode(",", self::escapeArray($fieldsForSearch))
            ];
            // allow custom argument setting
            $searchParameters = array_merge($searchParameters, $typesenseArgs);
            $search = $client->collections[$collectionName]->documents->search($searchParameters);
        } else {
            // v26
            //Ref: https://github.com/typesense/typesense/issues/561
            //Ref: https://github.com/typesense/typesense/issues/696#issuecomment-1985042336
            $searchParameters = [
                'q' => '*',
                'query_by' => implode(",", self::escapeArray($fieldsForSearch))
            ];
            $filterBy = [];
            foreach($searchQuery as $field => $value) {
                //TODO escaping
                $filterBy[] = "{$field}:*{$value}*";
            }
            if($filterBy !== []) {
                $searchParameters['filter_by'] = implode(" || ", $filterBy);
                // allow custom argument setting
                $searchParameters = array_merge($searchParameters, $typesenseArgs);
                $search = $client->collections[$collectionName]->documents->search($searchParameters);
            }
        }

        // handle results
        if(isset($search['hits'])) {
            foreach($search['hits'] as $hit) {
                $result = [];
                $result = array_merge($result, (array)$hit['document']);
                $results->push(
                    ArrayData::create($result)
                );
            }
            return $results;
        } else {
            return null;
        }
    }

    /**
     * Perform a multisearch in the single given collection
     */
    public function doMultiSearch(string $collectionName, array $searchQuery, array $typesenseArgs = []): array {
        // an array, do a multisearch on each column using the term from each field
        $searches = [];
        foreach($searchQuery as $field => $value) {
            if(is_string($value)) {
                $searches[] = [
                    'collection' => $collectionName,
                    'query_by' => $field,
                    'q' => $value
                ];
            }
        }
        $search = [];
        if($searches !== []) {
            $searchRequests = [
                'searches' => $searches
            ];
            // allow custom argument setting
            $searchRequests = array_merge($searchRequests, $typesenseArgs);
            $commonSearchParams = [];
            $client = Typesense::client();
            $search = $client->multiSearch->perform($searchRequests, $commonSearchParams);
        }
        return $search;
    }
}
