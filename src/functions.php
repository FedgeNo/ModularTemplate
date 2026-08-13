<?php

declare(strict_types=1);

/**
 * Standalone helpers shared across the whole codebase - web requests, CLI
 * scripts, and the tests alike. Loaded explicitly by every bootstrap
 * (src/init.php, bin/run-tests.php, and each bin/ script that registers its
 * own autoloader), because functions have no class name for an autoloader to
 * find them by.
 *
 * Look here before writing a helper, and move one here on sight when it turns
 * up buried in a class it doesn't belong to.
 */

function truncate(string $str, int $len = 50): string
{
    if (mb_strlen($str) <= $len) {
        return $str;
    }

    $cut = mb_substr($str, 0, $len);
    $last_space = mb_strrpos($cut, ' ');

    // Back up to the last word boundary so the limit doesn't slice a word in
    // half - unless there's no space to back up to (a single long word), where
    // the hard cut stands.
    if ($last_space !== false) {
        $cut = mb_substr($cut, 0, $last_space);
    }

    return rtrim($cut) . '…';
}

/**
 * JSON safe to embed as the text of a <script> element.
 *
 * DOMDocument HTML-escapes text node content (&, <, >) regardless of the
 * parent tag. Browsers don't decode entities inside <script> (it's a "raw
 * text" element), so that escaping would corrupt the JSON. Encoding these
 * characters as JSON \uXXXX escapes first keeps them out of DOMDocument's
 * escaping pass while still round-tripping correctly through JSON.parse().
 */
function safe_json_for_script(mixed $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);

    return str_replace(['&', '<', '>'], ['\\u0026', '\\u003C', '\\u003E'], $json);
}

/** The canonical absolute URL of the request being served. */
function current_url(): string
{
    return ServerURL::absolute($_SERVER['REQUEST_URI'] ?? '/');
}
