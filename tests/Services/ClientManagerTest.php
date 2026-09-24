<?php

namespace NSWDPC\Search\Typesense\Tests\Services;

use NSWDPC\Search\Typesense\Services\ClientManager;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use Typesense\Client as TypesenseClient;

class ClientManagerTest extends SapphireTest
{
    private ?string $originalServer = null;

    private ?string $originalApiKey = null;

    #[\Override]
    protected function setUp(): void
    {
        Environment::setEnv('TYPESENSE_SERVER', '');
        Environment::setEnv('TYPESENSE_API_KEY', '');
        Environment::setEnv('TYPESENSE_SEARCH_KEY', 'test-search-key');
        parent::setUp();
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testGetServerNodesParsesSingleServer(): void
    {
        $manager = ClientManager::create();
        $nodes = $manager->getServerNodes('https://typesense.example.com:443');
        $this->assertSame([
            ['host' => 'typesense.example.com', 'port' => 443, 'protocol' => 'https'],
        ], $nodes);
    }

    public function testGetServerNodesParsesMultipleCommaSeparatedServers(): void
    {
        $manager = ClientManager::create();
        $nodes = $manager->getServerNodes('https://one.example.com:443,http://two.example.com:8108');
        $this->assertSame([
            ['host' => 'one.example.com', 'port' => 443, 'protocol' => 'https'],
            ['host' => 'two.example.com', 'port' => 8108, 'protocol' => 'http'],
        ], $nodes);
    }

    public function testGetServerNodesFromConfigurationUsesEnvironment(): void
    {
        Environment::setEnv('TYPESENSE_SERVER', 'https://env.example.com:443');
        $manager = ClientManager::create();
        $this->assertSame([
            ['host' => 'env.example.com', 'port' => 443, 'protocol' => 'https'],
        ], $manager->getServerNodes());
    }

    public function testGetServerNodesReturnsEmptyArrayWhenNoServersConfigured(): void
    {
        Environment::setEnv('TYPESENSE_SERVER', '');
        $manager = ClientManager::create();
        $this->assertSame([], $manager->getServerNodes());
    }

    public function testGetClientCachesInstanceForIdenticalParams(): void
    {
        $manager = ClientManager::create();
        $params = [
            'api_key' => 'a-key',
            'nodes' => [['host' => 'a.example.com', 'port' => 443, 'protocol' => 'https']],
        ];

        $clientA = $manager->getClient($params);
        $clientB = $manager->getClient($params);

        $this->assertInstanceOf(TypesenseClient::class, $clientA);
        $this->assertSame($clientA, $clientB);
    }

    public function testGetClientReturnsDifferentInstancesForDifferentParams(): void
    {
        $manager = ClientManager::create();
        $clientA = $manager->getClient([
            'api_key' => 'a-key',
            'nodes' => [['host' => 'a.example.com', 'port' => 443, 'protocol' => 'https']],
        ]);
        $clientB = $manager->getClient([
            'api_key' => 'b-key',
            'nodes' => [['host' => 'b.example.com', 'port' => 443, 'protocol' => 'https']],
        ]);

        $this->assertNotSame($clientA, $clientB);
    }

    public function testGetConfiguredClientForApiKeyUsesProvidedApiKey(): void
    {
        Environment::setEnv('TYPESENSE_SERVER', 'https://env.example.com:443');
        $manager = ClientManager::create();
        $client = $manager->getConfiguredClientForApiKey('a-specific-key');
        $this->assertInstanceOf(TypesenseClient::class, $client);
    }
}
