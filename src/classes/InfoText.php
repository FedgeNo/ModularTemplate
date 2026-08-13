<?php

declare(strict_types=1);

/**
 * Splits an admin-authored plain-text site-info block (about/terms/privacy)
 * into Paragraph elements: blank-line-separated chunks become <p> elements,
 * everything entity-escaped by the normal text-node handling - the admin
 * writes plain text, not HTML.
 */
class InfoText
{
    /** @return Paragraph[] */
    public static function paragraphs(string $text): array
    {
        $chunks = preg_split('/\R\s*\R/', trim($text));
        $paragraphs = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);

            if ($chunk !== '') {
                $paragraphs[] = new Paragraph($chunk);
            }
        }

        return $paragraphs;
    }
}
