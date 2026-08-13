<?php

declare(strict_types=1);

/**
 * The parts of the site that only matter to somebody not using a mouse, or not
 * looking at the screen.
 *
 * These are about getting things done rather than about markup being tidy: can
 * you reach the content without pressing Tab thirty times, can you tell there
 * is a message waiting, does the search box say what it is. Each of these
 * failed before it was written.
 */
class AccessibilityTest extends DatabaseTestCase
{
    private function xpathOver(HTMLObject $object): \DOMXPath
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        HTMLObject::currentDocument() -> appendChild($object -> toDOM());

        return new \DOMXPath(HTMLObject::currentDocument());
    }

    /**
     * Somebody arriving by keyboard should not have to walk the whole
     * navigation on every page to reach the first post.
     */
    public function testTheWayPastTheNavigationIsTheFirstThingOnThePage(): void
    {
        $xpath = $this -> xpathOver(new SkipLink());
        $link = $xpath -> query('//a') -> item(0);

        $this -> assertSame('#' . SkipLink::TARGET, $link -> getAttribute('href'));
        $this -> assertSame('Skip to Content', trim($link -> textContent));
    }

    /**
     * And what it lands on has to be able to hold focus, or the browser moves
     * the view and leaves the focus where it was - the next Tab carries on
     * from the navigation, which is the thing being skipped.
     */
    public function testWhatItLandsOnCanHoldFocus(): void
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $markup = (string) new Page(['title' => 'Test']);

        $document = new \DOMDocument();
        @$document -> loadHTML($markup);
        $xpath = new \DOMXPath($document);

        $main = $xpath -> query('//main') -> item(0);

        $this -> assertNotNull($main);
        $this -> assertSame(SkipLink::TARGET, $main -> getAttribute('id'));
        $this -> assertSame('-1', $main -> getAttribute('tabindex'));

        // Before the navigation, or it is not a way past it.
        $links = $xpath -> query('//a[contains(@class, "SkipLink")]');
        $navigation = $xpath -> query('//nav');

        $this -> assertSame(1, $links -> length);
        $this -> assertSame(1, $navigation -> length);

        // Ordered by where each one sits in the markup, since a way past the
        // navigation that comes after it is no way past anything.
        $order = [];

        foreach ($xpath -> query('//a[contains(@class, "SkipLink")] | //nav') as $element) {
            $order[] = $element -> nodeName;
        }

        $this -> assertSame('a,nav', implode(',', $order));
    }

    /** The search box, which had a placeholder and nothing else. */
    public function testTheSearchBoxSaysWhatItIs(): void
    {
        $search = new SearchInput();
        $search -> placeholder = 'Search posts';

        $input = $this -> xpathOver($search) -> query('//input') -> item(0);

        $this -> assertSame('Search posts', $input -> getAttribute('aria-label'));
        $this -> assertSame('search', $input -> getAttribute('type'));
    }

    /**
     * The mobile menu is a checkbox behind a label of three empty bars, so
     * nothing about it says "menu" unless the checkbox does.
     */
    public function testTheMenuControlSaysItIsTheMenu(): void
    {
        $xpath = $this -> xpathOver(new MainNavigation());
        $toggle = $xpath -> query('//input[@id="NavToggle"]') -> item(0);

        $this -> assertNotNull($toggle);
        $this -> assertSame('Menu', $toggle -> getAttribute('aria-label'));
    }
}
