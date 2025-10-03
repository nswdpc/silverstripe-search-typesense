<?php

namespace NSWDPC\Search\Typesense\Services;

use ElliotSawyer\SilverstripeTypesense\Collection;
use ElliotSawyer\SilverstripeTypesense\Typesense;
use NSWDPC\Search\Typesense\Jobs\DeleteJob;
use NSWDPC\Search\Typesense\Jobs\UpsertJob;
use NSWDPC\Search\Typesense\Models\Result;
use NSWDPC\Search\Typesense\Models\SearchResults;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ViewableData;
use Typesense\Client as TypesenseClient;

/**
 * Typesense search handler
 */
class SearchHandler
{
    use Configurable;
    use Injectable;

    public const MAX_PER_PAGE = 250;

    public const DEFAULT_PER_PAGE = 10;

    private static bool $log_queries = false;

    private static string $log_level = "INFO";

    protected string $startVarName = 'start';

    public function __construct(string $startVarName = 'start')
    {
        if ($startVarName === '') {
            throw new \InvalidArgumentException('Start var name cannot be an empty string');
        }

        $this->startVarName = $startVarName;
    }

    public function getStartVarName(): string
    {
        return $this->startVarName;
    }

    public static function escapeString(string $string): string
    {
        if (str_contains($string, "`")) {
            return "`" . str_replace("`", "\\`", $string) . "`";
        } else {
            return $string;
        }
    }


    public static function escapeArray(array $array): array
    {
        $result = [];
        foreach ($array as $string) {
            if (!is_string($string)) {
                continue;
            }

            $result[] = self::escapeString($string);
        }

        return $result;
    }

    /**
     * Get the Typesense client from the Typesense SDK
     * Provide a search scope and search only API key to return a client using that scoped API key
     * If not provided, the default API key will be used
     * @param array $searchScope a Typesense scope
     * @param string $searchOnlyApiKey a Typesense search-only API key
     */
    protected static function getClient(array $searchScope = [], string $searchOnlyApiKey = ''): TypesenseClient
    {
        $manager = new ClientManager();
        if ($searchOnlyApiKey !== '') {
            $scopedApiKey = ScopedSearch::getScopedApiKey($searchOnlyApiKey, $searchScope);
            $client = $manager->getConfiguredClientForApiKey($scopedApiKey);
        } else {
            $client = $manager->getConfiguredClient();
        }

        return $client;
    }

    /**
     * do a search using the input values from a form and the model used for configuration
     * @param Collection $collection search in this collection
     * @param array|string $searchQuery search with this query or using an array of query data
     * @param int $pageStart the start offset for the results, e.g 0, 10, 20 for 10 results per page
     * @param int $perPage the number of items per page, cannot be more than 250. If <= 0 the default of 10 is used
     * @param array $searchScope a Typesense search scope to be merged into the search parameters. The scope is an array of search parameters
     * @param string $searchOnlyApiKey an optional search-only API key for this particular search
     */
    public function doSearch(Collection $collection, array|string $searchQuery, int $pageStart = 0, int $perPage = 10, array $searchScope = [], string $searchOnlyApiKey = ''): ?SearchResults
    {

        $collectionName = trim((string)$collection->Name);

        if ($collectionName === '') {
            return null;
        }

        // Client
        $client = static::getClient($searchScope, $searchOnlyApiKey);

        // query by handling
        $queryBy = '';
        if (!isset($searchScope['query_by'])) {
            // TODO nested object, object[] search
            $fieldsForSearch = $collection->Fields()
                ->filter([
                    'index' => 1,
                    'type' => ['string','string[]'] // Only fields that have a datatype of string or string[] in the collection schema can be specified in query_by
                ])
                ->column('name');
            $queryBy = implode(",", self::escapeArray($fieldsForSearch));
        } elseif (is_string($searchScope['query_by'])) {
            $queryBy = trim($searchScope['query_by']);
        }

        $search = [];
        if (is_string($searchQuery)) {
            // basic string search on multiple fields
            $searchParameters = [
                'q' => $searchQuery
            ];
            if ($queryBy !== '') {
                $searchParameters['query_by'] = $queryBy;
            }
        } else {
            // array of search queries
            // Search in all fields and filter by fields
            // v26
            //Ref: https://github.com/typesense/typesense/issues/561
            //Ref: https://github.com/typesense/typesense/issues/696#issuecomment-1985042336
            $searchParameters = [
                'q' => '*'
            ];
            if ($queryBy !== '') {
                $searchParameters['query_by'] = $queryBy;
            }

            $filterBy = [];
            foreach ($searchQuery as $field => $value) {
                //TODO escaping
                //TODO operations
                $filterBy[] = "{$field}:*{$value}*";
            }

            if ($filterBy !== []) {
                $searchParameters['filter_by'] = implode(" || ", $filterBy);
            }
        }

        // pagination parameters
        // items per page
        $perPage = $this->setPerPage($perPage);
        // current page number (if perPage = 10, e.g 0 = 1, 1 = 2)
        $pageNumber = (int)(floor($pageStart / $perPage)) + 1;
        $paginationParameters = [
            'page' => $pageNumber,
            'per_page' => $perPage
        ];

        // construct search parameters using the searchScope, allow derived parameters to override
        $searchParameters = array_merge($searchScope, $searchParameters, $paginationParameters);

        $this->logQuery($searchParameters, $collectionName);
        $search = $client->collections[$collectionName]->documents->search($searchParameters);

        // handle results
        if (isset($search['hits']) && is_array($search['hits'])) {

            $list = ArrayList::create();
            foreach ($search['hits'] as $hit) {

                if (!isset($hit['document']) || !is_array($hit['document'])) {
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
     * @TODO this implementation needs work
     */
    public function doMultiSearch(string $collectionName, array $searchQuery, array $searchScope = [], string $searchOnlyApiKey = ''): array
    {
        // an array, do a multisearch on each column using the term from each field
        $searches = [];
        foreach ($searchQuery as $field => $value) {
            if (is_string($value)) {
                $searches[] = [
                    'collection' => $collectionName,
                    'query_by' => $field,
                    'q' => $value
                ];
            }
        }

        $search = [];
        if ($searches !== []) {
            $searchRequests = [
                'searches' => $searches
            ];
            // allow custom argument setting
            $searchRequests = array_merge($searchRequests, $searchScope);
            $commonSearchParams = [];
            $client = static::getClient($searchScope, $searchOnlyApiKey);
            $this->logQuery($searchRequests);
            $search = $client->multiSearch->perform($searchRequests, $commonSearchParams);
        }

        return $search;
    }

    /**
     * Log queries, if enabled
     */
    protected function logQuery(array $query, string $collectionName = ''): bool
    {
        if (!static::config()->get('log_queries')) {
            return false;
        } else {
            Logger::log("Typesense Query=" . json_encode(["query" => $query, "collection" => $collectionName]), static::config()->get('log_level'));
            return true;
        }
    }

    public function setPerPage(int $perPage): int
    {
        if ($perPage > 250) {
            $perPage = static::MAX_PER_PAGE;
        } elseif ($perPage <= 0) {
            $perPage = static::DEFAULT_PER_PAGE;
            // default used
        }

        return $perPage;
    }

    /**
     * Return all collections for a record, removing certain parent classes
     */
    public static function getCollectionsForRecord(DataObject $record): ?DataList
    {
        $ancestry = ClassInfo::ancestry($record, false);
        $ancestry = array_filter(
            $ancestry,
            // @phpstan-ignore notIdentical.alwaysTrue, notIdentical.alwaysTrue
            fn ($k, $v): true => $v !== DataObject::class && $v !== ViewableData::class,
            ARRAY_FILTER_USE_BOTH
        );

        if ($ancestry === []) {
            return null;
        }

        return Collection::get()
            ->filter(['RecordClass' => $ancestry]);
    }

    /*
     * Return whether this record is linked to any collections
     */
    public static function isLinkedToCollections(DataObject $record): ?DataList
    {
        $collections = static::getCollectionsForRecord($record);
        if (is_null($collections) || $collections->count() == 0) {
            return null;
        } else {
            return $collections;
        }
    }

    /**
     * Attempt to upsert this record to Typesense collections via a queued job
     */
    public static function upsertToTypesense(DataObject $record, bool $viaQueuedJob = false): bool
    {

        // Check if this record is linked to any collections
        if (!($collections = static::isLinkedToCollections($record)) instanceof \SilverStripe\ORM\DataList) {
            Logger::log("Attempt to upsert record #{$record->ID}/{$record->ClassName} not linked to any collections", "INFO");
            return false;
        }

        if (!$viaQueuedJob) {
            // Direct upsert .. UpsertJob process calls this.
            $success = 0;
            $client = static::getClient();
            foreach ($collections as $collection) {
                try {
                    /** @phpstan-ignore method.notFound (method in SilverstripeTypesense\Collection) */
                    if ($collection->checkExistance()) {
                        $data = [];
                        /** @phpstan-ignore method.notFound (method in SilverstripeTypesense\Collection) */
                        $fieldsArray = $collection->FieldsArray();
                        if ($record->hasMethod('getTypesenseDocument')) {
                            $data = $record->getTypesenseDocument($fieldsArray);
                        } else {
                            /** @phpstan-ignore method.notFound (method in SilverstripeTypesense\Collection) */
                            $data = $collection->getTypesenseDocument($record, $fieldsArray);
                        }

                        $upsert = $client->collections[$collection->Name]->documents->upsert($data);
                        Logger::log("Upserted record #{$record->ID}/{$record->ClassName} to collection {$collection->Name}", "INFO");
                        $success++;
                    }
                } catch (\Exception $exception) {
                    Logger::log($exception::class . ": failed to upsert #{$record->ID}/{$record->ClassName} to collection {$collection->Name}: " . $exception->getMessage(), "NOTICE");
                }
            }

            return $success == $collections->count();
        } else {
            // Upsert via job
            return UpsertJob::queueMyself($record);
        }
    }

    /**
     * Delete this record from all linked collections via a queued job
     */
    public static function deleteFromTypesense(DataObject $record, bool $viaQueuedJob = false): bool
    {

        // Check if this record is linked to any collections
        if (!($collections = static::isLinkedToCollections($record)) instanceof \SilverStripe\ORM\DataList) {
            Logger::log("Attempt to delete record #{$record->ID}/{$record->ClassName} not linked to any collections", "INFO");
            return false;
        }

        if (!$viaQueuedJob) {
            // Direct delete .. DeleteJob process calls this.
            $success = 0;
            $client = static::getClient();
            foreach ($collections as $collection) {
                try {
                    /** @phpstan-ignore method.notFound (method in SilverstripeTypesense\Collection) */
                    if ($collection->checkExistance()) {
                        $data = [];
                        /** @phpstan-ignore method.notFound (method in SilverstripeTypesense\Collection) */
                        $fieldsArray = $collection->FieldsArray();
                        if ($record->hasMethod('getTypesenseDocument')) {
                            $data = $record->getTypesenseDocument($fieldsArray);
                        } else {
                            /** @phpstan-ignore method.notFound (method in SilverstripeTypesense\Collection) */
                            $data = $collection->getTypesenseDocument($record, $fieldsArray);
                        }

                        $client->collections[$collection->Name]->documents[(string) $record->ID]->delete();
                        Logger::log("Delete record #{$record->ID}/{$record->ClassName} from collection {$collection->Name}", "INFO");
                        $success++;
                    }
                } catch (\Exception $exception) {
                    Logger::log($exception::class . ": failed to delete #{$record->ID}/{$record->ClassName} from collection {$collection->Name}: " . $exception->getMessage(), "NOTICE");
                }
            }

            return $success == $collections->count();
        } else {
            // delete via job
            return DeleteJob::queueMyself($record);
        }
    }

}
