<?php

declare(strict_types=1);

/** A stable, line-oriented representation used to compare two DOM trees. */
final class DOMCanonicalForm
{
    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'poster', 'formaction'];

    /** @return list<string> */
    public static function lines(\DOMNode $node, int $depth = 0): array
    {
        if ($node instanceof \DOMText) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $node -> textContent));

            return $text === '' ? [] : [str_repeat('  ', $depth) . '#text ' . self::quote($text)];
        }

        if (!$node instanceof \DOMElement) {
            return [];
        }

        $attributes = [];

        foreach ($node -> attributes as $attribute) {
            $attributes[$attribute -> name] = in_array($attribute -> name, self::URL_ATTRIBUTES, true)
                ? self::route($attribute -> value)
                : $attribute -> value;
        }

        ksort($attributes);

        $written = [];

        foreach ($attributes as $name => $value) {
            $written[] = $name . '=' . self::quote($value);
        }

        $lines = [str_repeat('  ', $depth) . strtolower($node -> tagName)
            . ($written === [] ? '' : ' ' . implode(' ', $written))];

        foreach ($node -> childNodes as $child) {
            array_push($lines, ...self::lines($child, $depth + 1));
        }

        return $lines;
    }

    private static function route(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        return ($parts['path'] ?? '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    private static function quote(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
