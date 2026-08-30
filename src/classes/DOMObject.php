<?php

declare(strict_types=1);

// The shared DOM-building core beneath both element hierarchies: an object that
// renders itself into a shared DOMDocument as a <tagName> element carrying
// attributes and children - nested DOMObjects (which render themselves), or
// strings (which become text nodes). HTMLObject layers the HTML-specific pieces
// on top (a CSS class, a one-shot render guard, CData content); XMLObject uses
// this almost as-is. Document (the base of HTMLDocument and XMLDocument) also
// extends this, so a document is itself the element it renders.
//
// AllowDynamicProperties because an object is routinely hydrated straight off a
// query (mysqli_fetch_object sets a property per column before the constructor
// runs), so a class needn't pre-declare every column a query might select.
#[\AllowDynamicProperties]
abstract class DOMObject
{
    protected const HYDRATION_EXCLUSIONS = ['tagName', 'attributes', 'contents'];

    protected static \DOMDocument $document;

    public static function currentDocument(): \DOMDocument
    {
        return self::$document;
    }

    public string $tagName;
    public array $attributes = [];
    public array $contents = [];

    /**
     * Seeds declared data properties from an array or object: a key naming a
     * property this class declares is copied on, any other key ignored, and
     * never the element's own structure or identity - its tag, attributes,
     * contents, CSS class, one-shot render flag, list of items, or
     * content type.
     * Handing it a wider source (a whole User, a page) therefore only ever
     * transfers data properties, never changes what the object is or how it
     * renders. mysqli_fetch_object sets the columns before calling this with no
     * argument, so a DB-hydrated object ($properties null) keeps the values it
     * loaded with.
     */
    public function __construct(array|object|null $properties = null)
    {
        if ($properties !== null) {
            foreach (is_array($properties) ? $properties : get_object_vars($properties) as $name => $value) {
                if (in_array($name, self::hydrationExclusions(), true)) {
                    continue;
                }

                if (property_exists($this, $name)) {
                    $this -> $name = $value;
                }
            }
        }
    }

    /**
     * Combines the properties each level of this object's hierarchy owns and
     * keeps out of generic hydration. A redeclared constant ordinarily hides
     * its parent's value; reflecting each declaring class lets a descendant
     * contribute exclusions without having to repeat the inherited ones.
     */
    private static function hydrationExclusions(): array
    {
        static $cache = [];

        if (isset($cache[static::class])) {
            return $cache[static::class];
        }

        $exclusions = [];

        for ($class = new \ReflectionClass(static::class); $class !== false; $class = $class -> getParentClass()) {
            $constant = $class -> getReflectionConstant('HYDRATION_EXCLUSIONS');

            if ($constant !== false && $constant -> getDeclaringClass() -> getName() === $class -> getName()) {
                $exclusions = array_merge($exclusions, $constant -> getValue());
            }
        }

        return $cache[static::class] = array_values(array_unique($exclusions));
    }

    public function addContents(array $items): void
    {
        foreach ($items as $item) {
            $this -> contents[] = $item;
        }
    }

    public function toDOM(): \DOMElement
    {
        $element = self::$document -> createElement($this -> tagName);

        $this -> applyAttributes($element);

        foreach ($this -> contents as $item) {
            $node = $this -> contentToNode($item);

            if ($node !== null) {
                $element -> appendChild($node);
            }
        }

        return $element;
    }

    /**
     * Writes the attributes onto the element, leaving out any whose value is
     * null.
     *
     * A null means the caller had nothing to put there and should not have set
     * the attribute at all. Handing it to setAttribute() is a TypeError, and
     * because a document is built by one recursive descent, that takes down
     * the entire page over a single missing string - a video with no poster
     * blanking the feed it appears in. Dropping it renders the page instead,
     * and the warning is what stops that being silent: it reaches the error
     * log and the admin's notifications like any other server fault.
     */
    protected function applyAttributes(\DOMElement $element): void
    {
        foreach ($this -> attributes as $name => $value) {
            if ($value === null) {
                trigger_error(static::class . ' left the "' . $name . '" attribute null', E_USER_WARNING);

                continue;
            }

            $element -> setAttribute($name, $value);
        }
    }

    protected function contentToNode($item): ?\DOMNode
    {
        if ($item instanceof DOMObject) {
            return $item -> toDOM();
        } elseif (is_string($item)) {
            return self::$document -> createTextNode($item);
        } elseif ($item instanceof \DOMNode) {
            return self::$document -> importNode($item, true);
        }

        return null;
    }
}
