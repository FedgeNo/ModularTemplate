<?php

declare(strict_types=1);

/**
 * The page shown when the site is installed but its configuration could not be
 * read, which is the one failure the rest of the app cannot report on its own:
 * with no .env every Config value is a placeholder, so the database credentials
 * are wrong and the canonical-host redirect points at a domain nobody owns.
 * Every request would quietly leave for somewhere else, and the cause - a file
 * mode - is invisible from the outside.
 *
 * Deliberately built from nothing: no Page, no HTMLObject, no database. Those
 * are exactly what is unavailable here, and a diagnostic that needs the broken
 * thing to work is no diagnostic.
 */
class ConfigurationError
{
    /**
     * Why the configuration is unusable, in one sentence for the page - the
     * file being unreadable and the file lacking a site address are different
     * mistakes with different fixes, and "something is wrong with .env" would
     * send someone looking in the wrong place.
     */
    public static function reason(): ?string
    {
        if (Env::unreadable()) {
            return 'The configuration file exists but the web server cannot read it, so every '
                . 'setting is at its built-in placeholder - including the site\'s own address and '
                . 'its database credentials.';
        }

        if ((string) Config::get('siteURL') === '') {
            return 'The configuration file names no site address (SITE_URL), which is what every '
                . 'link, redirect and federated identity this site publishes is built from.';
        }

        return null;
    }

    public static function send(string $reason): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 60');

        $path = Env::path();
        $rows = [
            'Configuration file' => is_string(realpath($path)) ? (string) realpath($path) : $path,
            'Owned by' => self::owner($path),
            'Mode' => self::mode($path),
            'Read attempted as' => self::currentUser(),
        ];

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Configuration unreadable</title>';
        echo '<style>body{font:16px/1.5 system-ui,sans-serif;margin:0;padding:2rem;color:#111;background:#fff}';
        echo 'main{max-width:44rem;margin:0 auto}h1{font-size:1.4rem}';
        echo 'dl{display:grid;grid-template-columns:auto 1fr;gap:0.25rem 1rem;margin:1.5rem 0}';
        echo 'dt{font-weight:600}dd{margin:0;font-family:ui-monospace,monospace;overflow-wrap:anywhere}';
        echo 'code{font-family:ui-monospace,monospace;background:#f2f2f2;padding:0.1em 0.3em;border-radius:3px}';
        echo '@media(prefers-color-scheme:dark){body{color:#eee;background:#161616}code{background:#2a2a2a}}</style>';
        echo '</head><body><main>';
        echo '<h1>This site is not configured</h1>';
        echo '<p>' . htmlspecialchars($reason, ENT_QUOTES) . ' Nothing is served until this is '
            . 'fixed, rather than sending every visitor somewhere that isn\'t this site.</p>';
        echo '<dl>';

        foreach ($rows as $label => $value) {
            echo '<dt>' . htmlspecialchars($label, ENT_QUOTES) . '</dt>';
            echo '<dd>' . htmlspecialchars($value, ENT_QUOTES) . '</dd>';
        }

        echo '</dl>';
        echo '<p>The file has to be readable by the account the web server runs as. Re-running '
            . '<code>sudo php bin/install.php</code> restores its ownership and mode.</p>';
        echo '</main></body></html>';
    }

    private static function owner(string $path): string
    {
        $uid = @fileowner($path);
        $gid = @filegroup($path);

        if ($uid === false || $gid === false) {
            return 'unknown';
        }

        return self::userName($uid) . ':' . self::groupName($gid);
    }

    private static function mode(string $path): string
    {
        $perms = @fileperms($path);

        return $perms === false ? 'unknown' : substr(sprintf('%o', $perms), -4);
    }

    /**
     * The account this request is running as. Whether it matches the file's
     * owner above is the entire diagnosis, so this falls back to /proc rather
     * than giving up when the POSIX extension isn't built in - which is the
     * common case, and would leave the page unable to answer its one question.
     */
    private static function currentUser(): string
    {
        if (function_exists('posix_geteuid')) {
            return self::userName(posix_geteuid());
        }

        $status = @file_get_contents('/proc/self/status');

        // "Uid:\treal\teffective\tsaved\tfilesystem" - the effective one is
        // what the read above was actually attempted with.
        if (is_string($status) && preg_match('/^Uid:\s+\d+\s+(\d+)/m', $status, $match) === 1) {
            return self::userName((int) $match[1]);
        }

        return 'unknown';
    }

    private static function userName(int $uid): string
    {
        if (function_exists('posix_getpwuid')) {
            $entry = posix_getpwuid($uid);

            if (is_array($entry) && isset($entry['name'])) {
                return (string) $entry['name'];
            }
        }

        return self::nameFromDatabase('/etc/passwd', $uid) ?? '#' . $uid;
    }

    private static function groupName(int $gid): string
    {
        if (function_exists('posix_getgrgid')) {
            $entry = posix_getgrgid($gid);

            if (is_array($entry) && isset($entry['name'])) {
                return (string) $entry['name'];
            }
        }

        return self::nameFromDatabase('/etc/group', $gid) ?? '#' . $gid;
    }

    /**
     * The name for an id out of /etc/passwd or /etc/group, both of which are
     * world-readable and share a layout: name:password:id:... A bare number
     * tells whoever is reading this page nothing they can act on, and these
     * files are the same source the POSIX calls above would consult.
     */
    private static function nameFromDatabase(string $path, int $id): ?string
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $fields = explode(':', $line);

            if (isset($fields[2]) && (int) $fields[2] === $id && $fields[0] !== '') {
                return $fields[0];
            }
        }

        return null;
    }
}
