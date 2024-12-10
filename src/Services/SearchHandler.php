<?php

namespace NSWDPC\Search\Typesense\Services;

use ElliotSawyer\SilverstripeTypesense\Collection;
use ElliotSawyer\SilverstripeTypesense\Typesense;
use NSWDPC\Search\Typesense\Models\Result;
use NSWDPC\Search\Typesense\Models\SearchResults;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;

/**
 * Typesense search handler
 */
class SearchHandler {

    use Configurable;
    use Injectable;

    const MAX_PER_PAGE = 250;

    const DEFAULT_PER_PAGE = 10;

    private static bool $log_queries = false;

    private static string $log_level = "INFO";

    protected string $startVarName = 'start';

    public function __construct(string $startVarName = 'start') {
        if($startVarName === '') {
            throw new \InvalidArgumentException('Start var name cannot be an empty string');
        }
        $this->startVarName = $startVarName;
    }

    public function getStartVarName(): string {
        return $this->startVarName;
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
     * @param HTTPRequest $request the current request object
     * @param Collection $collection
     * @param array|string $searchQuery
     * @param int $pageStart the start offset for the results, e.g 0, 10, 20 for 10 results per page
     * @param int $perPage the number of items per page, cannot be more than 250. If <= 0 the default of 10 is used
     * @param array $typesenseArgs extra search arguments to do a custom search beyond what can be automatically determined
     * @return PaginatedList|null
     */
    public function doSearch(Collection $collection, array|string $searchQuery, int $pageStart = 0, int $perPage = 10, array $typesenseArgs = []): ?SearchResults {
        $client = Typesense::client();
        $collectionName = '';
        if($collection instanceof Collection) {
            $collectionName = (string)$collection->Name;
        }

        if($collectionName === '') {
            return null;
        }

        // TODO nested object, object[] search
        $fieldsForSearch = $collection->Fields()
            ->filter([
                'index' => 1,
                'type' => ['string','string[]'] // Only fields that have a datatype of string or string[] in the collection schema can be specified in query_by
            ])
            ->column('name');
        if($fieldsForSearch === []) {
            return null;
        }

        $search = [];
        if(is_string($searchQuery)) {
            // basic string search on multiple fields
            $searchParameters = [
                'q' => $searchQuery,
                'query_by' => implode(",", self::escapeArray($fieldsForSearch))
            ];
        } else {
            // Search in all fields and filter by fields
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
                //TODO operations
                $filterBy[] = "{$field}:*{$value}*";
            }
            if($filterBy !== []) {
                $searchParameters['filter_by'] = implode(" || ", $filterBy);
            }
        }



        // pagination parameters
        // items per page
        $perPage = $this->setPerPage($perPage);
        // current page number (if perPage = 10, e.g 0 = 1, 1 = 2)
        $pageNumber = floor($pageStart / $perPage) + 1;
        $paginationParameters = [
            'page' => $pageNumber,
            'per_page' => $perPage
        ];

        // allow custom argument setting
        $searchParameters = array_merge($searchParameters, $paginationParameters, $typesenseArgs);

        $this->logQuery($searchParameters, $collectionName);
        $search = $client->collections[$collectionName]->documents->search($searchParameters);

        // handle results
        if(isset($search['hits']) && is_array($search['hits'])) {

            $list = ArrayList::create();
            foreach($search['hits'] as $hit) {

                if(!isset($hit['document']) || !is_array($hit['document'])) {
                    // skip if no result returned
                    continue;
                }
                $list->push(
                    Result::create(
                        $hit['document'],
                        isset($hit['highlight']) && is_array($hit['highlight']) ? $hit['highlight'] : [],
                        isset($hit['highlights']) && is_array($hit['highlights']) ? $hit['highlights'] : [],
                        isset($hit['text_match']) && is_int($hit['text_match']) ? $hit['text_match'] : 0,
                        isset($hit['text_match_info']) && is_array($hit['text_match_info']) ? $hit['text_match_info'] : []
                    )
                );
            }

            $results = SearchResults::create($list);
            $results->setResultData($search, ['hits']);
            $results->setPaginationGetVar($this->startVarName);
            // page length should be set before the current page number
            // as pageStart depends on pageLength
            $results->setPageLength($perPage);
            $results->setCurrentPage($pageNumber);// will set pageStart
            // total items found in the search (not total index size)
            $results->setTotalItems($search['found']);

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
            $this->logQuery($searchRequests);
            $search = $client->multiSearch->perform($searchRequests, $commonSearchParams);
        }
        return $search;
    }

    /**
     * Log queries, if enabled
     */
    protected function logQuery(array $query, string $collectionName = ''): bool {
        if(!self::config()->get('log_queries')) {
            return false;
        } else {
            Logger::log("Query:" . json_encode($query) . " Collection:" . $collectionName, self::config()->get('log_level'));
            return true;
        }
    }

    public function setPerPage(int $perPage): int {
        if($perPage > 250) {
            $perPage = static::MAX_PER_PAGE;
        } else if($perPage <= 0) {
            $perPage = static::DEFAULT_PER_PAGE;// default used
        }
        return $perPage;
    }

    public function getPageNumber(): int {
        return $this->pageNumber;
    }
}
