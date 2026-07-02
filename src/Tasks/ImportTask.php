<?php

namespace NSWDPC\Search\Typesense\Jobs;

use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection as Collection;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Build task for importing a collection to the server
 * To import a large collection, use the SyncJob instead as that will import the collection
 * over time
 */
class ImportTask extends BuildTask
{
    protected string $title = 'Typesense collection import';

    protected static string $description = 'Import a single collection into Typesense';

    protected static string $commandName = "TypesenseCollectionImportTask";

    public function getOptions(): array
    {
        return [
            new InputOption('collection', null, InputOption::VALUE_NONE, 'Typesense collection name'),
            new InputOption('limit', null, InputOption::VALUE_NONE, 'Batched record import limit'),
            new InputOption('verbose', null, InputOption::VALUE_NONE, 'Verbose output')
        ];
    }

    /**
     * Run the import task
     * @inheritdoc
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {

        $collectionName = $input->getOption('collection') ?? '';
        $limit = $input->getOption('limit') ?? 100;
        $verbose = (bool) $input->getOption('verbose');
        $sort = ['ID' => 'ASC'];
        if (!is_string($collectionName) || $collectionName === '') {
            $output->writeln(
                _t(
                    self::class . ".COLLECTION_NAME_NOT_PROVIDED",
                    "Provide a collection parameter, being the collection name"
                )
            );
            return Command::FAILURE;
        }

        $collection = Collection::get()->filter(['Name' => $collectionName])->first();
        if (!$collection || !$collection->isInDB()) {
            $output->writeln(
                _t(
                    self::class . ".COLLECTION_NOT_FOUND",
                    "The collection '{collectionName}' cannot be found",
                    [
                        'collectionName' => $collectionName
                    ]
                )
            );
            return Command::FAILURE;
        } else {

            try {
                $output->writeln(
                    _t(
                        self::class . ".COLLECTION_IMPORTING",
                        "The collection '{collectionName}' is importing",
                        [
                            'collectionName' => $collectionName
                        ]
                    )
                );
                $recordCount = $collection->import($limit, $sort, $verbose);
                $output->writeln(
                    _t(
                        self::class . ".COLLECTION_IMPORTING",
                        "The collection '{collectionName}' imported {recordCount} records",
                        [
                            'collectionName' => $collectionName,
                            'recordCount' => $recordCount
                        ]
                    )
                );
            } catch (\Exception $exception) {
                $output->writeln(
                    _t(
                        self::class . ".COLLECTION_IMPORT_TASK_FAILED",
                        "The collection '{collectionName}' import failed with error '{error}' of type '{type}'",
                        [
                            'collectionName' => $collectionName,
                            'error' => $exception->getMessage(),
                            'type' => $exception::class
                        ]
                    )
                );
                return Command::FAILURE;
            }

            $importSuccesses = $collection->getImportSuccesses();
            $importErrors = $collection->getImportErrors();
            $importStats = $collection->getImportStats();

            if ($verbose) {
                foreach ($importSuccesses as $success) {
                    $output->writeln(
                        json_encode($success)
                    );
                }

                foreach ($importErrors as $error) {
                    $output->writeln(
                        json_encode($error)
                    );
                }
            } else {
                $output->writeln("Success:" . count($importSuccesses));
                $output->writeln("Error:" . count($importErrors));
            }

            $docs = 0;
            $size = 0;
            $avgSize = 0;
            $sizeMB = 0;
            foreach ($importStats as $importStat) {
                $docs += $importStat['docs'];
                $size += $importStat['sizeBytes'];
            }

            if ($docs > 0) {
                $avgSize = round($size / $docs);
            }

            $sizeMB = round($size / (1024 * 1024));
            $output->writeln("Stats: docs={$docs} sizeBytes={$size} sizeMB={$sizeMB} avgSizeBytes={$avgSize}");
        }

        return Command::SUCCESS;

    }

}
