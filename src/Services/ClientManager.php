<?php

namespace NSWDPC\Search\Typesense\Services;

use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use Typesense\Client as TypesenseClient;

/**
 * Create and retrieve a typesense client
 */
class ClientManager
{

    use Injectable;

    protected static array $clients = [];

    /**
     * Get the client based on environment configuration
     */
    public function getConfiguredClient(array $clientOptions = []): TypesenseClient
    {
        $apiKey = Environment::getEnv('TYPESENSE_API_KEY') ?? '';
        return $this->getConfiguredClientForApiKey($apiKey, $clientOptions);
    }

    /**
     * Get the client based on environment configuration with a specific API key
     */
    public function getConfiguredClientForApiKey(string $apiKey, array $clientOptions = []): TypesenseClient
    {
        return $this->getClient(array_merge(
            [
                'api_key' => $apiKey,
                'nodes' => $this->getNodesFromConfiguration()
            ],
            $clientOptions
        ));
    }

    /**
     * Return a Typesense node using the TYPESENSE_SERVER environment value
     * Can return multiple nodes if the TYPESENSE_SERVER contains multiple server values
     */
    protected function getNodesFromConfiguration(): array
    {
        $servers = trim(Environment::getEnv('TYPESENSE_SERVER') ?? '');
        if($servers === '') {
            // none
            return [];
        }

        return $this->getNodesFromServers($servers);
    }

    /**
     * Given a string of server(s) possibly separated by a "," return the nodes for Typesense
     * @return list<array{host: mixed, port: mixed, protocol: mixed}>
     */
    protected function getNodesFromServers(string $servers): array {
        $nodes = [];
        $urls = explode(",", $servers);
        foreach($urls as $url) {
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
        }

        return $nodes;
    }

    /**
     * Get the Typesense Client instance for the params provided
     * If one exists in the runtime cache of clients, use that
     */
    final public function getClient(array $params): TypesenseClient
    {
        ksort($params);
        $key = hash('sha256', json_encode($params));
        if(isset(static::$clients[$key]) && (static::$clients[$key] instanceof TypesenseClient)) {
            Logger::log("Found existing TypesenseClient with these params", "INFO");
            return static::$clients[$key];
        } else {
            Logger::log("Create a new TypesenseClient with these params", "INFO");
            static::$clients[$key] = new TypesenseClient($params);
            return static::$clients[$key];
        }
    }

    /**
     * Get server nodes either from the servers parameter or from configuration if none provided
     */
    public function getServerNodes(string $servers = ''): array
    {
        if($servers !== '') {
            return $this->getNodesFromServers($servers);
        } else {
            return $this->getNodesFromConfiguration();
        }
    }

}
