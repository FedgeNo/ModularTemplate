<?php

declare(strict_types=1);

/**
 * The IANA top-level-domain allowlist (src/tlds.txt, the published
 * tlds-alpha-by-domain.txt): exists() answers whether a label is a registered
 * TLD. Refreshed by re-fetching
 * https://data.iana.org/TLD/tlds-alpha-by-domain.txt over src/tlds.txt.
 */
final class TLDList
{
    /** @var array<string, true>|null uppercased TLD => true, loaded once per request */
    private static ?array $tlds = null;

    /**
     * Whether $label is a registered TLD. Compared uppercased against the
     * ASCII (A-label) list, so a punycode "xn--..." label matches while a
     * raw-unicode one does not.
     */
    public static function exists(string $label): bool
    {
        if (self::$tlds === null) {
            self::$tlds = [];

            foreach (file(__DIR__ . '/../tlds.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);

                // Skip the file's leading "# Version ..." comment line.
                if ($line === '' || $line[0] === '#') {
                    continue;
                }

                self::$tlds[strtoupper($line)] = true;
            }
        }

        return isset(self::$tlds[strtoupper(trim($label))]);
    }
}
