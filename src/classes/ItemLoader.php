<?php

declare(strict_types=1);

abstract class ItemLoader extends HTMLObject
{
    protected const HYDRATION_EXCLUSIONS = ['items'];

    public const PAGE_SIZE = 20;

    public int $offset = 0;
    public array $items = [];
    public bool $hasMore = false;

    /**
     * Whether this list pages on scroll. False where sections stand one above
     * another and a list that grows forever buries everything beneath it.
     */
    public bool $scrolls = true;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> items = $this -> rows();
        $this -> hasMore = count($this -> items) > static::PAGE_SIZE;
        $this -> items = $this -> arrange(array_slice($this -> items, 0, static::PAGE_SIZE));
    }

    protected function rows(): array
    {
        return [];
    }

    protected function arrange(array $items): array
    {
        return $items;
    }

    protected function dataAttributes(): array
    {
        return [];
    }

    public function hasItems(): bool
    {
        return $this -> items !== [];
    }

    public function toJSON(): array
    {
        $this -> markRendered();

        return [
            'items' => $this -> items,
            'hasMore' => $this -> hasMore,
        ];
    }

    public function toDOM(): \DOMElement
    {
        foreach ($this -> dataAttributes() as $name => $value) {
            $this -> attributes[$name] = $value;
        }

        // A list with no next page carries no scroll config, so the client
        // never binds a scroller that would only ever fetch an empty page -
        // nor does one that has been told not to page at all.
        if (!$this -> hasMore || !$this -> scrolls) {
            unset($this -> attributes['data-infinite-scroll']);
        }

        return parent::toDOM();
    }
}
