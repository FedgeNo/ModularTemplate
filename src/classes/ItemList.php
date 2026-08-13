<?php

declare(strict_types=1);

/**
 * A <ul> whose children are wrapped in <li> at render time, so the markup is
 * valid <ul><li> without a subclass having to build the <li>s itself. A child
 * that's already a ListItem is left alone.
 *
 * Whatever a subclass loaded is poured in here, so a list of its own is a
 * rows() and nothing else. A plain one is the inner <ul> a ListSection builds
 * around its items.
 */
class ItemList extends UnorderedList
{
    /** Takes the list's place when it comes back empty; blank renders neither. */
    protected string $emptyNotice = '';

    public function toDOM(): \DOMElement
    {
        $this -> addContents($this -> items);

        // "No blocked servers" is not a blocked server, so it is not a row -
        // and with no rows there is no list to put one in. The notice stands
        // where the list would, and the client builds a list over it if
        // something ever arrives to go in one (list_in, utils.js).
        if ($this -> contents === [] && $this -> emptyNotice !== '') {
            $this -> markRendered();

            return (new Notice($this -> emptyNotice)) -> toDOM();
        }

        $this -> contents = array_map(static function ($item): ListItem {
            if ($item instanceof ListItem) {
                return $item;
            }

            $list_item = new ListItem();
            $list_item -> addContent($item);

            return $list_item;
        }, $this -> contents);

        return parent::toDOM();
    }
}
