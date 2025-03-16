<?php

namespace NSWDPC\Search\Typesense\Services;

use ElliotSawyer\SilverstripeTypesense\Typesense;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;

/**
 * InstantSearch service
 */
abstract class InstantSearch {

    use Configurable;
    use Injectable;

    /**
     * Provide the instant search service
     */
    public static function provide(string $id, array $spec) {
        static::addRequirements();
        static::addLocalRequirement($id,$spec);
    }

    /**
     * Create a configuration array
     */
    public static function createConfiguration(string $searchOnlyApiKey, string $queryBy, array $nodes = []): array {
        if($nodes === []) {
            $server = Typesense::parse_typesense_server();
            if (!$server) {
                throw new \Exception(
                    _t(static::class.'.EXCEPTION_schemeformat', 'TYPESENSE_SERVER must be in scheme://host:port format')
                );
            }
            $host = $server['host'] ?? '';
            $port = $server['port'] ?? 8081;
            $scheme = $server['scheme'] ?? 'https';
            $nodes = [];
            if ($host && $port && $scheme) {
                $nodes[] = [
                    'host' => $host,
                    'port' => $port,
                    'protocol' => $scheme,
                ];
            }
        }
        $server = [
            'apiKey' => $searchOnlyApiKey,
            'nodes' => $nodes
        ];
        $serverExtra = [];// support extra args to server entry
        if($serverExtra !== []) {
            $server = array_merge($serverExtra, $server);
        }
        $additionalSearchParameters = [
            'query_by' => $queryBy
        ];
        return [
            'server' => $server,
            'additionalSearchParameters' => $additionalSearchParameters
        ];
    }

    /**
     * Add the remote requirement
     */
    protected static function addRequirements(): void {
        Requirements::javascript(
            "https://cdn.jsdelivr.net/npm/instantsearch.js@4.78.0/dist/instantsearch.production.min.js",
            [
                "integrity" => "sha256-TgmjIYtuCzPUUGlHWyK8k1xVPlvPHUAGU7gLz8jwIi0=",
                "crossorigin" => "anonymous"
            ]
        );

        Requirements::javascript(
            "https://cdn.jsdelivr.net/npm/typesense-instantsearch-adapter@2.8.0/dist/typesense-instantsearch-adapter.min.js",
            [
                "integrity" => "sha256-+m17/NOBXYx2y7qalsYPe3dKBQXgQHR10ixO7HJ4IO0=",
                "crossorigin" => "anonymous"
            ]
        );
    }

    /**
     * Add the local requirement
     */
    protected static function addLocalRequirement(string $id, array $spec): void {

        $data = [
            'Configuration' => DBField::create_field('HTMLFragment', json_encode($spec['Configuration'])),
            'CollectionName' => DBField::create_field(DBVarchar::class, $spec['CollectionName']),
            'Searchbox' => DBField::create_field(DBVarchar::class, $spec['Searchbox']),
            'Hitbox' => DBField::create_field(DBVarchar::class, $spec['Hitbox'])
        ];

        if(!$id) {
            $id = bin2hex(random_bytes(2));
        }

        $script = ArrayData::create($data)->renderWith('NSWDPC/Search/Typesense/Services/InstantSearch');
        Requirements::customScript(
            $script,
            "typesense-local-{$id}"
        );
    }

}
