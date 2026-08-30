<?php

declare(strict_types=1);

/**
 * The About page's links out to the site's Terms of Service and Privacy
 * Policy - the one reachable place linking to both.
 */
class SitePolicyLinks extends Div
{
    public ?string $class = 'SitePolicyLinks';

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $terms_link = new Anchor(ServerURL::absolute('/terms'), (string) ($words['terms'] ?? ''));
        $terms_link -> class = 'Button';
        $this -> contents[] = $terms_link;

        $privacy_link = new Anchor(ServerURL::absolute('/privacy'), (string) ($words['privacy'] ?? ''));
        $privacy_link -> class = 'Button';
        $this -> contents[] = $privacy_link;

        return parent::toDOM();
    }
}
