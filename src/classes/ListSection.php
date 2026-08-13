<?php

declare(strict_types=1);

/**
 * A titled <section> over a list: an optional <h2> and the list itself, which
 * loads its own items.
 *
 * A section never queries and is never handed rows to render - it holds the
 * list object and lets it fill itself. An empty one shows nothing at all, so it
 * takes no visual space while still standing as a mount point the client can
 * populate later.
 */
abstract class ListSection extends Section
{
    /** The <h2> text; blank renders no heading. */
    protected string $heading = '';

    /** Loads and renders the items. */
    protected ItemLoader $list;

    /**
     * Whether the heading stands even with nothing under it. True suits a list
     * the client populates later and needs a stable <h2> to retitle.
     */
    protected bool $headsEmptyList = false;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> list = $this -> list();
    }

    /**
     * The list this section is a heading over.
     */
    abstract protected function list(): ItemLoader;

    public function hasItems(): bool
    {
        return $this -> list -> hasItems();
    }

    public function toDOM(): \DOMElement
    {
        if (($this -> list -> hasItems() || $this -> headsEmptyList) && $this -> heading !== '') {
            $heading = new Heading2();
            $heading -> contents[] = $this -> heading;
            $this -> contents[] = $heading;
        }

        $this -> contents[] = $this -> list;

        return parent::toDOM();
    }
}
