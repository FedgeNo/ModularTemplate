<?php

declare(strict_types=1);

/**
 * The environment prerequisites the app needs regardless of database
 * configuration - shared between `bin/install.php` (CLI, stops at the first
 * failure) and the web setup wizard (which shows every current failure at
 * once, since there's no terminal to re-run against).
 */
class EnvironmentChecker
{
    /**
     * @return array<string, array{ok: bool, message: string, warn?: bool}>
     */
    public static function checks(): array
    {
        return [
            'PHP version' => self::checkPHPVersion(),
            'PHP extensions' => self::checkExtensions(),
            'Temp directory' => self::checkTempDirectory(),
            'SELinux' => self::checkSELinux(),
        ];
    }

    /**
     * The Unix uid the web server actually runs as, from the live web-SAPI probe
     * (environment-check.php derives it from a file the web process creates,
     * since get_current_user() reports the script's owner, not the process
     * user). Null when the web server can't be reached (e.g. a non-default
     * VirtualHost on 127.0.0.1) or it reports no uid.
     */
    public static function webServerUid(): ?int
    {
        $live = self::liveFacts();
        $uid = $live['webServerUid'] ?? null;

        return is_int($uid) ? $uid : null;
    }

    /**
     * Which web server is actually serving this site, confirmed live via the
     * SERVER_SOFTWARE the web SAPI itself reports (Apache sets it directly;
     * nginx passes it through to PHP-FPM via fastcgi_params) - never guessed
     * from a process-name check, which only shows something with that name
     * is running, not that it's the one handling this site's traffic (and
     * says nothing at all when a reverse proxy fronts the actual server).
     * Returns 'apache', 'nginx', or null when the probe didn't reach the
     * site or the string didn't recognizably say either - callers must not
     * take Apache/nginx-specific action (editing its config, restarting it,
     * picking a certbot plugin for it) on anything less than this or an
     * explicit prompt to the person running the installer.
     */
    public static function webServerSoftware(): ?string
    {
        $live = self::liveFacts();
        $software = strtolower((string) ($live['serverSoftware'] ?? ''));

        if ($software === '') {
            return null;
        }

        if (str_contains($software, 'nginx')) {
            return 'nginx';
        }

        if (str_contains($software, 'apache')) {
            return 'apache';
        }

        return null;
    }

    /**
     * Whether a real HTTPS connection to the site's actual configured
     * hostname (not 127.0.0.1 - a named-vhost setup may not route the
     * loopback address to this site at all, and testing the real hostname is
     * what actually matters for "does https:// on this domain really work")
     * succeeds. Proves SITE_URL's https:// prefix isn't just a string nobody's
     * backed with a working certificate/vhost, the way the naive check (does
     * the string start with "https://"?) never could. SSL verification is
     * off: this confirms *something* answers with TLS at all, not that the
     * certificate is CA-trusted (a self-signed/mkcert dev cert is fine here).
     *
     * Returns true if an HTTPS response comes back, false if a connection
     * happens but TLS itself demonstrably fails (a real, provable problem -
     * something's listening on the port but isn't actually serving TLS), and
     * null if nothing could be reached there at all (inconclusive - DNS not
     * pointed here yet, a firewall, or the web server simply isn't up yet
     * during install).
     */
    public static function httpsServing(string $host): ?bool
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://' . $host . '/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);

        $body = curl_exec($curl);
        $errno = curl_errno($curl);
        curl_close($curl);

        if ($body !== false) {
            return true;
        }

        // CURLE_SSL_CONNECT_ERROR (35) and friends mean a TCP connection was
        // established but the TLS handshake itself failed - real proof
        // something's listening on the port without actually serving TLS.
        // CURLE_COULDNT_CONNECT (7), CURLE_COULDNT_RESOLVE_HOST (6), and
        // timeouts mean nothing answered at all, which is inconclusive (DNS,
        // a firewall, or the web server just isn't up yet), not a
        // demonstrated failure.
        return in_array($errno, [CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM, CURLE_SSL_CIPHER, CURLE_SSL_PEER_CERTIFICATE], true)
            ? false
            : null;
    }

    /**
     * Proves - rather than asks about - whether Apache's HTTPS redirect
     * (.htaccess's `RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI}`) can
     * be spoofed via a forged Host header, which is exactly what "ServerName
     * <host>" + "UseCanonicalName On" exist to prevent. Connects to the
     * site's real hostname on the plain-HTTP port (where that redirect rule
     * lives - not 127.0.0.1, for the same VirtualHost-routing reason as
     * httpsServing()) but sends a deliberately forged Host header, then
     * checks whether the redirect's Location target reflects it.
     *
     * Returns true if the forged host leaks into the redirect (proven
     * vulnerable), false if it doesn't (proven safe - ServerName/
     * UseCanonicalName are genuinely working), and null if this can't be
     * tested at all (no reachable response, or no redirect to inspect -
     * e.g. HTTPS isn't enforced yet on this install) - callers fall back to
     * asking when null, since inconclusive isn't the same as safe.
     */
    public static function hostHeaderSpoofable(string $host): ?bool
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $spoofed_host = 'spoof-test-' . bin2hex(random_bytes(4)) . '.invalid';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'http://' . $host . '/',
            CURLOPT_HTTPHEADER => ['Host: ' . $spoofed_host],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);

        $headers = curl_exec($curl);
        curl_close($curl);

        if (!is_string($headers) || !preg_match('/^Location:\s*(\S+)/mi', $headers, $match)) {
            return null;
        }

        return str_contains($match[1], $spoofed_host);
    }

    /** @var array<string, mixed>|false|null null = not yet fetched, false = fetch failed */
    private static array|false|null $liveFactsCache = null;

    /**
     * Fetches every fact the web SAPI actually resolves - via a real HTTP
     * request to the site's own environment-check endpoint. Cached
     * per-process so the several CLI checks that each need this share one
     * HTTP round trip instead of one apiece. Returns null if the site isn't
     * reachable this way (e.g. the web server isn't running yet) - callers
     * treat that as inconclusive (not a failure) only while the site isn't
     * installed yet (see siteIsInstalled()); once it's installed, a
     * live-info failure means something that should work doesn't, and is
     * reported as one.
     *
     * @return array<string, mixed>|null
     */
    private static function liveFacts(): ?array
    {
        if (self::$liveFactsCache !== null) {
            return self::$liveFactsCache === false ? null : self::$liveFactsCache;
        }

        self::$liveFactsCache = self::fetchLiveFacts() ?? false;

        return self::$liveFactsCache === false ? null : self::$liveFactsCache;
    }

    /**
     * Whether .env exists - i.e. whether the site has actually been
     * installed and is expected to be reachable at all. Before that, a
     * live-info fetch failing is completely normal (there may be no web
     * server, vhost, or TLS set up yet) and every one of these checks stays
     * informational (ok=true) rather than flagging a problem that isn't one
     * yet. Once installed, the same failure means something that should
     * work doesn't - each check reports that as ok=false instead.
     */
    private static function siteIsInstalled(): bool
    {
        return is_file(__DIR__ . '/../../.env');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchLiveFacts(): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $site_url = (string) Config::get('siteURL', '');
        $host = strtolower((string) (parse_url($site_url, PHP_URL_HOST) ?? ''));
        $is_https = str_starts_with($site_url, 'https://');

        // Request the CONFIGURED host directly, with its real scheme - not a
        // bare "http://127.0.0.1/..." URL. A request whose Host header is
        // literally "127.0.0.1" doesn't match this site's own vhost, whose
        // ServerName is a hostname; on a box with more than one vhost
        // that falls through to the web server's default vhost - a COMPLETELY
        // different site - and this probe silently gets nothing useful back,
        // forever, on that box.
        $url = $host !== ''
            ? ($is_https ? 'https://' : 'http://') . $host . '/environment-check'
            : 'http://127.0.0.1/environment-check';

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ];

        // Pin the hostname back to the loopback interface so this doesn't
        // depend on the box reaching its own public IP (hairpin NAT often
        // can't) - the request still carries the right Host/SNI for the
        // vhost, and the self-signed-cert case is covered by VERIFYPEER
        // being off above.
        if ($host !== '' && $host !== '127.0.0.1' && $host !== 'localhost') {
            $options[CURLOPT_RESOLVE] = [$host . ':443:127.0.0.1', $host . ':80:127.0.0.1'];
        }

        $curl = curl_init();
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $status !== 200) {
            return null;
        }

        $data = json_decode($body, true);

        if (!is_array($data) || !is_array($data['response'] ?? null)) {
            return null;
        }

        return $data['response'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkPHPVersion(): array
    {
        if (PHP_VERSION_ID < 80100) {
            return ['ok' => false, 'message' => 'PHP 8.1 or newer is required - this is PHP ' . PHP_VERSION . '.'];
        }

        if (PHP_SAPI !== 'cli') {
            return ['ok' => true, 'message' => 'PHP ' . PHP_VERSION];
        }

        // CLI: on a host with multiple PHP versions installed, the "php"
        // command on PATH isn't guaranteed to be the same binary the web
        // server's SAPI runs. Confirm live where possible; this one is purely
        // informational either way (the CLI's own version already passed
        // above), never a failure - a real mismatch would show up as its own
        // extension/behavior failures elsewhere in this list.
        $live = self::liveFacts();

        if ($live === null || !isset($live['phpVersion'])) {
            return ['ok' => true, 'warn' => self::siteIsInstalled(), 'message' => 'PHP ' . PHP_VERSION . ' (CLI) - could not confirm the web server\'s version live'];
        }

        if (version_compare((string) $live['phpVersion'], '8.1.0', '<')) {
            return ['ok' => false, 'message' => 'The web server is running PHP ' . $live['phpVersion'] . ' (confirmed live), which is older than the required 8.1 - even though the CLI\'s PHP (' . PHP_VERSION . ') is fine. Upgrade the web server\'s PHP.'];
        }

        return ['ok' => true, 'message' => 'PHP ' . PHP_VERSION . ' (CLI), ' . $live['phpVersion'] . ' (confirmed live via the web server)'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkExtensions(): array
    {
        $required_extensions = [
            'mysqli' => 'the database layer',
            'curl' => 'the live environment checks and HTTPS probing',
            'dom' => 'HTML rendering',
            'libxml' => 'HTML parsing',
            'mbstring' => 'multibyte-safe text handling',
        ];

        if (PHP_SAPI !== 'cli') {
            $missing = [];

            foreach ($required_extensions as $extension => $used_for) {
                if (!extension_loaded($extension)) {
                    $missing[] = $extension . ' (' . $used_for . ')';
                }
            }

            if ($missing !== []) {
                return ['ok' => false, 'message' => 'Missing PHP extension(s): ' . implode(', ', $missing) . '. Install them (e.g. php-<name>) and restart PHP.'];
            }

            return ['ok' => true, 'message' => 'all required PHP extensions loaded (' . implode(', ', array_keys($required_extensions)) . ')'];
        }

        // CLI: check the CLI's own extensions first - the installer and any
        // background scripts (bin/*.php) run on the CLI and need them, so a
        // missing one here is a hard failure regardless of the web server (and
        // must be caught as a clean check, not a fatal deep in provisioning).
        $cli_missing = [];

        foreach ($required_extensions as $extension => $used_for) {
            if (!extension_loaded($extension)) {
                $cli_missing[] = $extension . ' (' . $used_for . ')';
            }
        }

        if ($cli_missing !== []) {
            return ['ok' => false, 'message' => 'The CLI is missing PHP extension(s): ' . implode(', ', $cli_missing) . ' - the installer and background scripts need them. Install them (e.g. php-<name>, or php-mysqlnd for mysqli) and re-run.'];
        }

        // Then confirm the WEB SAPI's extensions live, which a split-package
        // distro (separate php-cli/php-fpm packages, each with their own conf.d)
        // can load independently of the CLI's.
        $live = self::liveFacts();

        if ($live === null || !is_array($live['extensions'] ?? null)) {
            return ['ok' => true, 'warn' => self::siteIsInstalled(), 'message' => 'all required PHP extensions loaded on the CLI, but could not confirm the web server live - check via the web setup wizard directly.'];
        }

        $missing = [];

        foreach ($required_extensions as $extension => $used_for) {
            if (empty($live['extensions'][$extension])) {
                $missing[] = $extension . ' (' . $used_for . ')';
            }
        }

        if ($missing !== []) {
            return ['ok' => false, 'message' => 'The web server is missing PHP extension(s) (confirmed live): ' . implode(', ', $missing) . ' - even though the CLI has them all. Install them for the web SAPI (e.g. php-fpm-<name>) and reload PHP-FPM.'];
        }

        return ['ok' => true, 'message' => 'all required PHP extensions loaded (confirmed live via the web server)'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkTempDirectory(): array
    {
        if (PHP_SAPI !== 'cli') {
            if (!is_writable(sys_get_temp_dir())) {
                return ['ok' => false, 'message' => 'The system temp directory (' . sys_get_temp_dir() . ') is not writable.'];
            }

            return ['ok' => true, 'message' => 'temp directory writable (' . sys_get_temp_dir() . ')'];
        }

        // CLI: TMPDIR/sys_get_temp_dir() can resolve differently per FPM pool
        // env config. Confirm live.
        $live = self::liveFacts();

        if ($live === null || !isset($live['tempDir'])) {
            return ['ok' => true, 'warn' => self::siteIsInstalled(), 'message' => 'CLI temp directory (' . sys_get_temp_dir() . ') writable, but could not confirm the web server\'s live - check via the web setup wizard directly.'];
        }

        if (empty($live['tempDirWritable'])) {
            return ['ok' => false, 'message' => 'The web server\'s temp directory (' . $live['tempDir'] . ', confirmed live) is not writable, even though the CLI\'s (' . sys_get_temp_dir() . ') is fine.'];
        }

        return ['ok' => true, 'message' => 'temp directory writable (' . $live['tempDir'] . ', confirmed live via the web server)'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkSELinux(): array
    {
        if (trim((string) shell_exec('command -v getenforce 2>/dev/null')) === '') {
            return ['ok' => true, 'message' => 'SELinux not present, skipped'];
        }

        if (trim((string) shell_exec('getenforce 2>/dev/null')) !== 'Enforcing') {
            return ['ok' => true, 'message' => 'SELinux not enforcing'];
        }

        $bool_output = trim((string) shell_exec('getsebool httpd_can_network_connect 2>/dev/null'));

        if (str_contains($bool_output, '--> off')) {
            return ['ok' => false, 'message' => 'SELinux is enforcing and httpd_can_network_connect is off - the web server cannot make any outbound connection, which breaks outgoing mail (the SMTP relay). Fix with: setsebool -P httpd_can_network_connect on'];
        }

        return ['ok' => true, 'message' => 'SELinux enforcing, httpd_can_network_connect on'];
    }
}
