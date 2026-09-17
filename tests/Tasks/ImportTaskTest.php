<?php

namespace NSWDPC\Search\Typesense\Tests\Tasks;

use NSWDPC\Search\Typesense\Tasks\ImportTask;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;

class ImportTaskTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        TypesenseTestRecord::class,
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();

        TypesenseSearchCollection::get()->filter(['RecordClass' => TypesenseTestRecord::class])->removeAll();
        TypesenseTestRecord::get()->removeAll();

        // ImportTask reports via DB::alteration_message(); an earlier test in the
        // suite building the temp database can leave the schema manager "quiet",
        // which would otherwise silently swallow this output
        DB::get_schema()->quiet(false);
    }

    protected function runTask(array $getVars): string
    {
        $request = new HTTPRequest('GET', '/', $getVars);
        ob_start();
        ImportTask::create()->run($request);
        return ob_get_clean();
    }

    public function testRunWithoutCollectionParameterShowsError(): void
    {
        $output = $this->runTask([]);
        $this->assertStringContainsString('Provide a collection parameter', $output);
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testRunWithUnknownCollectionShowsError(): void
    {
        $output = $this->runTask(['collection' => 'does-not-exist']);
        $this->assertStringContainsString('cannot be found', $output);
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testRunImportsAnExistingCollection(): void
    {
        $record = TypesenseTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();

        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class, 'Enabled' => true]);
        $collection->Metadata = json_encode([
            'name' => 'task-docs',
            'fields' => [['name' => 'Title', 'type' => 'string']],
        ]);
        $collection->write();

        // 1: batchedImport()'s exists() check, 2: documents import, then a second batch finds no more records
        TestClientManager::$httpClient->queueJson(200, ['name' => 'task-docs']);
        TestClientManager::$httpClient->queueRaw(200, json_encode(['success' => true, 'id' => (string) $record->ID]));

        $output = $this->runTask(['collection' => 'task-docs', 'limit' => 100]);

        $this->assertStringContainsString('imported 1 records', $output);
        $this->assertStringContainsString('Success:1', $output);
        $this->assertStringContainsString('Error:0', $output);
    }
}
