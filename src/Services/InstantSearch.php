<?php

namespace NSWDPC\Search\Typesense\Services;

use ElliotSawyer\SilverstripeTypesense\Typesense;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
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
    public static function provide(array $config): DBHTMLText {
        static::addRequirements();
        $nodes = $config['nodes'] ?? [];
        if(!is_array($nodes) || count($nodes) == 0) {
            $nodes = static::getServerNodes();
        }
        $tag = static::addLocalRequirement($config);
        return $tag;
    }

    /**
     * Get configured server node(s). Used if nodes are not passed to configuration
     */
    public static function getServerNodes(): array {
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
        return $nodes;
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
        Requirements::javascript(
            'nswdpc/silverstripe-search-typesense:client/static/js/instantsearch.js'
        );
        Requirements::css(
            'nswdpc/silverstripe-search-typesense:client/static/css/instantsearch.css'
        );
    }

    /**
     * Add the local requirement
     */
    protected static function addLocalRequirement(array $config): DBHTMLText {
        return DBField::create_field(
            'HTMLFragment',
            "<div data-instantsearch=\"" . htmlspecialchars(json_encode($config)) . "\"></div>"
        );
    }

}
