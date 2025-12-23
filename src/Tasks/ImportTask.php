<?php

namespace NSWDPC\Search\Typesense\Jobs;

use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection as Collection;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Build task for importing a collection to the server
 * To import a large collection, use the SyncJob instead as that will import the collection
 * over time
 */
class ImportTask extends BuildTask
{
    protected $title = 'Typesense collection import';

    protected $description = 'Import a single collection into Typesense';

    private static string $segment = "TypesenseCollectionImportTask";

    /**
     * Run the import task
     * @inheritdoc
     */
    public function run($request)
    {

        $collectionName = $request->getVar('collection') ?? '';
        $limit = $request->getVar('limit') ?? 100;
        $verbose = (bool) $request->getVar('verbose');
        $sort = ['ID' => 'ASC'];
        if (!is_string($collectionName) || $collectionName === '') {
            DB::alteration_message(
                _t(
                    self::class . ".COLLECTION_NAME_NOT_PROVIDED",
                    "Provide a collection parameter, being the collection name"
                ),
                "error"
            );
            return;
        }

        $collection = Collection::get()->filter(['Name' => $collectionName])->first();
        if (!$collection || !$collection->isInDB()) {
            DB::alteration_message(
                _t(
                    self::class . ".COLLECTION_NOT_FOUND",
                    "The collection '{collectionName}' cannot be found",
                    [
                        'collectionName' => $collectionName
                    ]
                ),
                "error"
            );
            return;
        } else {

            try {
                DB::alteration_message(
                    _t(
                        self::class . ".COLLECTION_IMPORTING",
                        "The collection '{collectionName}' is importing",
                        [
                            'collectionName' => $collectionName
                        ]
                    ),
                    "changed"
                );
                $recordCount = $collection->import($limit, $sort, $verbose);
                DB::alteration_message(
                    _t(
                        self::class . ".COLLECTION_IMPORTING",
                        "The collection '{collectionName}' imported {recordCount} records",
                        [
                            'collectionName' => $collectionName,
                            'recordCount' => $recordCount
                        ]
                    ),
                    "changed"
                );
            } catch (\Exception $exception) {
                DB::alteration_message(
                    _t(
                        self::class . ".COLLECTION_IMPORT_TASK_FAILED",
                        "The collection '{collectionName}' import failed with error '{error}' of type '{type}'",
                        [
                            'collectionName' => $collectionName,
                            'error' => $exception->getMessage(),
                            'type' => $exception::class
                        ]
                    ),
                    "error"
                );
            }

            $importSuccesses = $collection->getImportSuccesses();
            $importErrors = $collection->getImportErrors();
            $importStats = $collection->getImportStats();

            if($verbose) {
                foreach ($importSuccesses as $success) {
                    DB::alteration_message(
                        json_encode($success),
                        "changed"
                    );
                }

                foreach ($importErrors as $error) {
                    DB::alteration_message(
                        json_encode($error),
                        "error"
                    );
                }
            } else {
                DB::alteration_message("Success:" . count($importSuccesses), "changed");
                DB::alteration_message("Error:" . count($importErrors), "error");
            }

            $docs = 0;
            $size = 0;
            $avgSize = 0;
            $sizeMB = 0;
            foreach($importStats as $importStat) {
                $docs += $importStat['docs'];
                $size += $importStat['sizeBytes'];
            }
            if($docs > 0) {
                $avgSize = round($size / $docs);
            }
            $sizeMB = round($size / (1024*1024));
            DB::alteration_message("Stats: docs={$docs} sizeBytes={$size} sizeMB={$sizeMB} avgSizeBytes={$avgSize}", "changed");
        }

    }

}
