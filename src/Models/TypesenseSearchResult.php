<?php

namespace NSWDPC\Search\Typesense\Models;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;

/**
 * This is a single Typesense search result
 * It can be referenced in templates as TypesenseSearchResult.{FieldName}
 *
 * A TypesenseSearchResult returns all fields required for rendering a result without having
 * to pick up data from the local database
 * Title - the result title
 * Date - the result date, if any
 * Link - the result link
 * ImageURL - the result image, if any
 * ImageAlt - the result image alt text, if any
 * Abstract - the result abstract
 * Highlight - if the result returned a highlight, this will be set
 * Labels -  all the labels/tags for the result, if any
 * Label -  the primary label or category of the result
 * Context -  some extra context for the result, usually a one liner
 */
class TypesenseSearchResult extends ViewableData {

    use Injectable;

    protected array $data = [];

    protected string $highlight = '';

    /**
     * Store data in this object
     */
    public function  __construct(array $data = []) {
        $this->data = $data;
    }

    public function getField($name) {
        return $this->data[$name] ?? null;
    }

    public function toArray() {
        return $this->data;
    }

    /**
     * Set a highlight text for the result
     */
    public function setHighlight(string $highlight): static {
        $this->highlight = $highlight;
    }

    /**
     * Return a highlight text for the result
     */
    public function Highlight(): string {
        return $this->highlight;
    }

    /**
     * The result title
     */
    public function Title(): ?string {
        return $this->getField('Title');
    }

    /**
     * The date stored must be a unix timestamp
     */
    public function Date(string $format = ''): ?string {
        return $this->getField('Date');
    }

    public function Link(): ?string {
        return $this->getField('Link');
    }

    public function ImageURL(): ?string {
        return $this->getField('ImageURL');
    }

    public function ImageAlt(): ?string {
        return $this->getField('ImageAlt');
    }

    public function Label(): ?string {
        return $this->getField('Label');
    }

    public function Labels(): ArrayList {
        $list = ArrayList::create();
        $labels = $this->getField('Labels');
        if(is_array($labels)) {
            foreach($labels as $label) {
                $list->push(
                    ArrayData::create([
                        'Title' => $label
                    ])
                );
            }
        }
        return $list;
    }

    public function Abstract(): ?string {
        return $this->getField('Abstract');
    }

    public function Context(): ?string {
        return $this->getField('Context');
    }

}
