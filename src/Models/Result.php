<?php

namespace NSWDPC\Search\Typesense\Models;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\View\ViewableData;

/**
 * Represents a single result.
 * This class can be overridden i your project to provide custom result handling (e.g get values based on property requested)
 * You can specify a template to render the result, or by default it will render into this class' path
 */
class Result extends ViewableData {

    use Configurable;

    use Injectable;

    private static string $template = "";

    protected array $result;

    /**
     * Create a result using a hit from the search results returned from Typesense
     */
    public function  __construct(array $result) {
        $this->result = $result;
    }

    public function forTemplate() {
        $templates = [];
        $template = self::config()->get('template');
        if($template !== "") {
            $templates[] = $template;
        }
        $templates[] = self::class;
        return $this->renderWith(array_filter($templates));
    }

    public function __set($name, $value) {
        $this->result[$name] = $value;
        return $this;
    }

    public function __get($name) {
        return $this->result[$name] ?? null;
    }

    public function __isset($name) {
        return array_key_exists($name, $this->result);
    }
}
