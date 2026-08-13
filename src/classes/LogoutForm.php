<?php

declare(strict_types=1);

/**
 * Log out as a POST form, not a plain link - logout changes state, so it must
 * be CSRF-protected (init.php verifies the token on every POST). A bare
 * GET link would let a third-party page force-log-out a victim. Styled to sit
 * inline among the account dropdown's other links (see LogoutButton in CSS).
 */
class LogoutForm extends Form
{
    public ?string $class = 'LogoutForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/logout');
        $this -> method = 'POST';

        $button = new Button();
        $button -> type = 'submit';
        $button -> class = 'LogoutButton';
        $button -> contents[] = (string) (Strings::for(self::class)['submit'] ?? '');
        // Overwrite, not append - toDOM() can run more than once on the same
        // instance now (MainNavigation reuses it for both the desktop
        // account dropdown and the mobile nav panel), and contents[] is
        // instance state that would otherwise accumulate a duplicate button
        // on every additional call.
        $this -> contents = [$button];

        return parent::toDOM();
    }
}
