<?php

namespace NSWDPC\Search\Typesense\Tests\Mocks;

use NSWDPC\Search\Typesense\Services\ClientManager;
use Typesense\Client as TypesenseClient;

/**
 * A ClientManager that ignores environment configuration and always builds a
 * Typesense\Client wired to a FakeTypesenseHttpClient, so tests never make a
 * real network call.
 *
 * Register this in place of ClientManager via:
 *   Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
 *
 * ClientManager caches built Typesense\Client instances in a *static* property
 * shared across the whole test run, so reset() must be called (e.g. in setUp())
 * before use to avoid a client built for an earlier test being reused.
 */
class TestClientManager extends ClientManager
{
    public static FakeTypesenseHttpClient $httpClient;

    /**
     * Clear ClientManager's static client cache and provide a fresh fake HTTP client
     */
    public static function reset(): FakeTypesenseHttpClient
    {
        $reflection = new \ReflectionClass(ClientManager::class);
        $property = $reflection->getProperty('clients');
        $property->setValue(null, []);

        static::$httpClient = new FakeTypesenseHttpClient();
        return static::$httpClient;
    }

    #[\Override]
    public function getConfiguredClientForApiKey(string $apiKey, array $clientOptions = []): TypesenseClient
    {
        return $this->getClient(array_merge(
            [
                'api_key' => $apiKey !== '' ? $apiKey : 'test-api-key',
                'nodes' => [
                    ['host' => 'typesense.test', 'port' => 443, 'protocol' => 'https'],
                ],
                'num_retries' => 0,
                'client' => static::$httpClient,
            ],
            $clientOptions
        ));
    }

    #[\Override]
    public function getServerNodes(string $servers = ''): array
    {
        if ($servers !== '') {
            return parent::getServerNodes($servers);
        }

        return [
            ['host' => 'typesense.test', 'port' => 443, 'protocol' => 'https'],
        ];
    }
}
