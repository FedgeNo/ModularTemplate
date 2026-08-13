<?php

declare(strict_types=1);

class VerificationResendButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> type = 'button';
    }

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = (string) (Strings::for(self::class)['label'] ?? '');

        return parent::toDOM();
    }
}
