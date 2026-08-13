<?php

declare(strict_types=1);

/**
 * Asks somebody whose browser is set to a language this site speaks whether
 * they would rather read it in that one.
 *
 * Written in the language being offered, not in the one they are looking at:
 * the whole point is to reach somebody who cannot read the page, and asking
 * them in the page's own language would be no help at all.
 *
 * Asked once. Answering either way is a choice, and a choice is what stops it
 * being asked again - saying no is an answer, not a postponement.
 */
class LanguagePrompt extends Div
{
    public ?string $class = 'LanguagePrompt';

    /**
     * The language to offer, or null where there is nothing to ask.
     *
     * Nothing to ask when they have already said, when their browser wants
     * what they are already reading, or when it wants something this site has
     * no words for.
     */
    public static function offer(): ?string
    {
        if (Strings::hasChosen()) {
            return null;
        }

        $wanted = Strings::preferred();

        return $wanted === null || $wanted === Strings::locale() ? null : $wanted;
    }

    public function toDOM(): \DOMElement
    {
        $offered = self::offer();

        if ($offered === null) {
            return parent::toDOM();
        }

        // In the language being offered. Strings::for() reads the current
        // locale, so the offer is fetched from the other one directly.
        $words = Strings::forLocale(self::class, $offered);

        $this -> attributes['data-locale'] = $offered;

        // Each language names itself in its own question rather than having its
        // name substituted in: "in het Nederlands" and "w języku polskim"
        // inflect the name, and no token can be dropped into both correctly.
        $question = new Paragraph();
        $question -> class = 'LanguagePromptQuestion';
        $question -> contents[] = (string) ($words['question'] ?? '');

        // Each button is written in the language it leads to, so the words on
        // it are the choice rather than a label for it. Yes is in the language
        // being offered; No stays in the one they are reading now, which is
        // the only thing on the prompt somebody who wants to stay put is
        // guaranteed to understand.
        $accept = new ButtonButton();
        $accept -> class .= ' LanguagePromptAccept';
        $accept -> contents[] = (string) ($words['accept'] ?? '');

        $decline = new ButtonButton();
        $decline -> class .= ' LanguagePromptDecline';
        $decline -> contents[] = (string) (Strings::for(self::class)['decline'] ?? '');

        $this -> addContent($question);
        $this -> addContent($accept);
        $this -> addContent($decline);

        return parent::toDOM();
    }
}
