<?php

namespace NSWDPC\Search\Typesense\Models;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;

/**
 * Represents a single result.
 * This class can be overridden in your project to provide custom result handling (e.g get values based on property requested)
 * You can specify a template to render the result, or by default it will render into this class' path
 */
class Result extends ViewableData {

    use Configurable;

    use Injectable;

    private static string $template = "";

    protected array $result;
    protected array $highlight;
    protected array $highlights;
    protected int $text_match;
    protected array $text_match_info;

    /**
     * Create a result using a hit from the search results returned from Typesense
     */
    public function  __construct(array $result, array $highlight, array $highlights, int $text_match, array $text_match_info) {
        $this->result = $result;
        $this->highlight = $highlight;
        $this->highlights = $highlights;
        $this->text_match = $text_match;
        $this->text_match_info = $text_match_info;
    }

    public function __set($name, $value) {
        $this->result[$name] = $value;
    }

    public function __get($name) {
        return $this->result[$name] ?? null;
    }

    public function __isset($name) {
        return array_key_exists($name, $this->result);
    }

    /**
     * Return a TypesenseSearchResult instance containing the search result data
     */
    public function TypesenseSearchResult(): ?TypesenseSearchResult {
        $result = null;
        if(isset($this->result['TypesenseSearchResultData'])) {
            $result = TypesenseSearchResult::create($this->result['TypesenseSearchResultData']);
        }
        return $result;
    }

    /**
     * Return a template name based on the ClassName field provided in the
     * array returned from the indexed object's getTypesenseSearchResult() value
     * ClassName is one of the 'default_collection_fields'
     * If your class is \My\App\Record the template should be located in a theme or project
     * at /templates/My/App/Record_TypesenseSearchResult.ss
     */
    public function getTemplateName(): ?string {
        $template = $this->ClassName;
        if(!$template) {
            return null;
        } else {
            return $template . "_TypesenseSearchResult";
        }
    }

    /**
     * Render a result using one of the custom templates
     * This allows customisation of the result on a per-template basis
     * The template ordering is:
     *  1. Class based template returned from getTemplateName()
     *  2. Configured template, if set
     *  3. Template based on this class name
     */
    public function forTemplate() {
        $templates = [];
        if($classBasedTemplate = $this->getTemplateName()) {
            $templates[] = $classBasedTemplate;
        }
        $template = self::config()->get('template');
        if($template !== "") {
            $templates[] = $template;
        }
        $templates[] = self::class;
        return $this->renderWith(array_filter($templates));
    }
}
