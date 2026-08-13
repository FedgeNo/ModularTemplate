<?php

declare(strict_types=1);

/**
 * A button whose words change, that does not change size when they do.
 *
 * A button that swaps between fixed wordings still carries every one of them
 * from the start, hidden but measured, so its width is settled before anybody
 * presses anything - that is what ToggleButton is for and what the tests here
 * hold.
 */
class ToggleButtonTest extends TestCase
{
    private function xpathOver(HTMLObject $object): \DOMXPath
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        HTMLObject::currentDocument() -> appendChild($object -> toDOM());

        return new \DOMXPath(HTMLObject::currentDocument());
    }

    /** A toggle with two wordings, showing whichever the state says. */
    private function followButton(bool $following): ToggleButton
    {
        return new class($following) extends ToggleButton {
            protected array $labels = ['Follow', 'Unfollow'];

            public function __construct(bool $following)
            {
                parent::__construct();

                $this -> showing = $following ? 'Unfollow' : 'Follow';
            }
        };
    }

    /** @return string[] */
    private function labelsOf(HTMLObject $button): array
    {
        $labels = [];

        foreach ($this -> xpathOver($button) -> query('//span[contains(@class, "ToggleButtonLabel")]') as $span) {
            $labels[] = $span -> textContent;
        }

        return $labels;
    }

    private function showingIn(HTMLObject $button): array
    {
        $showing = [];

        foreach ($this -> xpathOver($button) -> query('//span[contains(@class, "ToggleButtonLabel") and not(contains(@class, "Inactive"))]') as $span) {
            $showing[] = $span -> textContent;
        }

        return $showing;
    }

    /** Every wording is present, exactly one of them showing. */
    public function testAPairCarriesBothWordingsAndShowsOne(): void
    {
        $this -> assertSame(['Follow', 'Unfollow'], $this -> labelsOf($this -> followButton(false)));
        $this -> assertSame(['Follow'], $this -> showingIn($this -> followButton(false)));
        $this -> assertSame(['Unfollow'], $this -> showingIn($this -> followButton(true)));
    }

    /** With no state set, the first wording is the default. */
    public function testTheFirstWordingIsTheDefault(): void
    {
        $button = new class() extends ToggleButton {
            protected array $labels = ['Show', 'Hide'];
        };

        $this -> assertSame(['Show'], $this -> showingIn($button));
    }

    /** The identity the CSS and the click handlers key on, unchanged by all this. */
    public function testTheButtonKeepsItsSharedIdentity(): void
    {
        $classes = (string) $this -> xpathOver($this -> followButton(true))
            -> query('//button') -> item(0) -> getAttribute('class');

        foreach (['Button', 'ToggleButton'] as $name) {
            $this -> assertTrue(str_contains($classes, $name), $name . ' is on the button');
        }
    }
}
