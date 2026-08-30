<?php

declare(strict_types=1);

/**
 * The check-your-inbox notice shown to unverified accounts: the instruction
 * line plus its actions (resend the email, or log out).
 */
class VerificationNotice extends Div
{
    public ?string $class = 'VerificationNotice';

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new Paragraph((string) (Strings::for(self::class)['instructions'] ?? ''));

        $actions = new VerificationNoticeActions();

        $actions -> addContent(new VerificationResendButton());

        $actions -> addContent(new LogoutForm());

        $this -> contents[] = $actions;

        return parent::toDOM();
    }
}
