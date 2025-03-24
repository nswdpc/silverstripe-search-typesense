<?php
namespace NSWDPC\Search\Typesense\Services;

use SilverStripe\Core\Environment;
use Typesense\Client as TypesenseClient;

/**
 * Create and retrieve a typesense client
 */
class ClientManager
{

    /**
     * Get the client based on environment configuration
     */
    public function getConfiguredClient(): TypesenseClient
    {
        $apiKey = Environment::getEnv('TYPESENSE_API_KEY') ?? '';
        return $this->getConfiguredClientForApiKey($apiKey);
    }

    /**
     * Get the client based on environment configuration with a specific API key
     */
    public function getConfiguredClientForApiKey(string $apiKey): TypesenseClient
    {
        return $this->getClient([
            'api_key' => $apiKey,
            'nodes' => $this->getNodesFromConfiguration()
        ]);
    }

    /**
     * Return a Typesense node using the TYPESENSE_SERVER environment value
     * @todo multiple server values
     */
    protected function getNodesFromConfiguration(): array {
        $nodes = [];
        $url = Environment::getEnv('TYPESENSE_SERVER') ?? '';
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? '';
        $scheme = $parts['scheme'] ?? 'https';
        $nodes = [];
        if ($host && $port && $scheme) {
            $nodes[] = [
                'host' => $host,
                'port' => $port,
                'protocol' => $scheme,
            ];
        }
        return $nodes;
    }

    /**
     * Get the Typesense Client instance for the params provided
     */
    public function getClient(array $params): TypesenseClient {
        return new TypesenseClient($params);
    }

}
