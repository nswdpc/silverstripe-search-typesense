<?php

namespace NSWDPC\Search\Typesense\Models;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\ModelData;

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
 * Abstract - the result abstract, the summary text for the result
 * Highlight - if the result returned a highlight, this will be set
 * Labels -  all the labels/tags for the result, if any
 * Label -  the primary label or category of the result
 * Context -  some extra context for the result, usually a one liner
 */
class TypesenseSearchResult extends ModelData
{
    use Injectable;

    protected string $highlight = '';

    /**
     * Store data in this object
     */
    public function __construct(protected array $data = [])
    {
    }

    /**
     * Set custom data value
     */
    #[\Override]
    public function __set(string $property, mixed $value): void
    {
        $this->data[$property] = $value;
    }

    /**
     * Allows templates to request values from this instance's data
     */
    #[\Override]
    public function __get(string $property): mixed
    {
        return $this->data[$property] ?? null;
    }

    #[\Override]
    public function __isset(string $property): bool
    {
        return array_key_exists($property, $this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Set a highlight text for the result
     */
    public function setHighlight(string $highlight): static
    {
        $this->highlight = $highlight;
        return $this;
    }

    /**
     * Helper method to return an array of labels as an ArrayList
     */
    public function LabelList(): ?ArrayList
    {
        $labels = $this->Labels;
        if (is_array($labels)) {
            // remove empty values
            $labels = array_filter($labels);
            $list = ArrayList::create();
            foreach ($labels as $label) {
                if (is_string($label) && $label !== "") {
                    $list->push(ArrayData::create([
                        'Name' => $label,
                        'Title' => $label
                    ]));
                } elseif (is_array($label)) {
                    // label has some metadata like name, link, title
                    $list->push(ArrayData::create([
                        'Name' => $label['Name'] ?? '',
                        'Link' => $label['Link'] ?? '',
                        'Title' => $label['Title'] ?? '',
                    ]));
                }
            }

            return $list;
        } else {
            return null;
        }
    }

    /**
     * Return a highlight text for the result
     */
    public function Highlight(): string
    {
        return $this->highlight;
    }

}
