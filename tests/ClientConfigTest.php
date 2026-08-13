<?php

declare(strict_types=1);

/**
 * The client configuration travels in the page as a JSON data block, so the
 * escaping that keeps it a data block - and the override channel a single page
 * uses to add to it - are what these cover.
 */
class ClientConfigTest extends TestCase
{
    /**
     * A `</script>` inside a value would end the block early and turn the rest
     * of the JSON into markup. Browsers don't decode entities inside a script
     * element, so the escaping has to happen in the JSON itself.
     */
    public function testAValueCannotCloseTheScriptBlock(): void
    {
        $encoded = safe_json_for_script(['title' => '</script><img src=x onerror=alert(1)>']);

        $this -> assertFalse(str_contains($encoded, '</script>'));
        $this -> assertFalse(str_contains($encoded, '<'));
        $this -> assertFalse(str_contains($encoded, '>'));

        // Still the same string once parsed - the escaping is JSON's own, not
        // a rewrite of the value.
        $this -> assertSame('</script><img src=x onerror=alert(1)>', json_decode($encoded, true)['title']);
    }

    public function testAmpersandsSurviveTheRoundTrip(): void
    {
        $encoded = safe_json_for_script(['title' => 'this & that']);

        $this -> assertFalse(str_contains($encoded, '&'));
        $this -> assertSame('this & that', json_decode($encoded, true)['title']);
    }
}
