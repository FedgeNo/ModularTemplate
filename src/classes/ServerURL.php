<?php

declare(strict_types=1);

class ServerURL
{
    private static ?string $siteURL = null;
    private static ?string $host = null;

    public static function absolute(string $path): string
    {
        if (self::$siteURL === null) {
            self::$siteURL = rtrim((string) Config::get('siteURL'), '/');
        }

        return self::$siteURL . $path;
    }

    /**
     * The configured site's lowercased hostname - the source of truth for
     * whether a link in a post is internal (same host, opens in place) or
     * external (opens in a new tab). Taken from the config siteURL, never the
     * request's Host header (which a client controls). Port is deliberately
     * ignored: matching the host is the security-relevant part, and ignoring
     * the port sidesteps default-port normalization differences between PHP's
     * parse_url and JS's URL that would otherwise diverge the two renderers.
     */
    public static function host(): string
    {
        if (self::$host === null) {
            self::$host = strtolower((string) (parse_url((string) Config::get('siteURL'), PHP_URL_HOST) ?? ''));
        }

        return self::$host;
    }

    /**
     * Whether the current request arrived over HTTPS. $_SERVER['HTTPS'] alone
     * is always empty behind a TLS-terminating reverse proxy - PHP sees plain
     * HTTP on every request in that setup - so this also honors
     * X-Forwarded-Proto, same as the HTTPS-enforcement redirect in init.php.
     */
    public static function isHTTPS(): bool
    {
        // Some stacks explicitly set HTTPS to the string 'off' on a plain-HTTP
        // request (nginx `fastcgi_param HTTPS off;`, some Apache SetEnv setups)
        // rather than leaving it empty, so a bare non-empty check would wrongly
        // read that as HTTPS - hence the canonical `!== 'off'` guard.
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

        if ($https !== '' && $https !== 'off') {
            return true;
        }

        // X-Forwarded-Proto is only trusted from the reverse proxy, evidenced
        // by a loopback REMOTE_ADDR - the exact gate clientIP() applies to
        // X-Forwarded-For, and for the same reason: a directly-connected
        // client could otherwise send `X-Forwarded-Proto: https` on a plain
        // HTTP request to skip the HTTPS-enforcement redirect and get secure
        // cookies / HSTS set over plain HTTP. (A TLS-terminating Apache sets
        // HTTPS=on directly, handled above, so it never relies on this.)
        $behind_local_proxy = in_array($_SERVER['REMOTE_ADDR'] ?? null, ['127.0.0.1', '::1'], true);

        return $behind_local_proxy && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /**
     * The real client IP, for rate limiting, IP-based checks, and CAPTCHA
     * scoring - not every deployment sits behind the reverse proxy this app
     * supports, so X-Forwarded-For is only trusted when there's actual
     * evidence one is present.
     *
     * REMOTE_ADDR being loopback is that evidence, and it's the one signal
     * here that can't be forged over the network: a real TCP connection
     * can't claim a loopback source address unless it genuinely originated
     * on this machine (enforced by the kernel, below the application layer) -
     * unlike X-Forwarded-For, which is just another HTTP header any client
     * can set to anything. So: a loopback REMOTE_ADDR means whatever
     * connected to Apache directly is local - almost certainly the reverse
     * proxy - and its X-Forwarded-For is trusted (last hop, since that's the
     * one the proxy itself appended). A non-loopback REMOTE_ADDR means
     * there's no proxy in front at all (this deployment is reached
     * directly), and X-Forwarded-For there is ignored entirely - trusting it
     * would let anyone fake a fresh IP on every request to dodge rate limits
     * and bans.
     */
    public static function clientIP(): ?string
    {
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;
        $behind_local_proxy = in_array($remote_addr, ['127.0.0.1', '::1'], true);

        if ($behind_local_proxy && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $hops = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));

            return end($hops);
        }

        return $remote_addr;
    }
}
