<?php

declare(strict_types=1);

class URL
{
    /**
     * Whether a user-supplied link is postable: an http(s) URL whose host is
     * a real registrable hostname - a dotted name ending in an IANA TLD (see
     * TLDList). A bare IP (private OR public), localhost, and any
     * single-label or fake-TLD host are all refused, so a post can only ever
     * link to a named site, never an address.
     *
     * Purely a shape check on the URL as written - it does no DNS lookup and
     * says nothing about whether the host is actually safe to fetch. That's
     * SafeHTTPFetcher's job (resolve the name, pin the IP, re-check every
     * redirect) at the moment a link preview is actually requested; baking a
     * point-in-time DNS answer into this post-time gate would only go stale
     * (a name that's clean today can repoint at an internal address
     * tomorrow) without adding any real protection SafeHTTPFetcher doesn't
     * already provide at the time it matters.
     */
    public static function isValidHTTPURL(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        // parse_url keeps the brackets on an IPv6 literal host ([::1]); strip
        // them so isValidHostname sees the bare address and rejects it as an
        // IP, same as a bracketless IPv4 literal.
        $host = trim($parts['host'], '[]');

        return self::isValidHostname($host);
    }

    /**
     * Whether $host is a real registrable hostname: a dotted name whose final
     * label is an IANA TLD (see TLDList). A bare IP (private OR public),
     * "localhost", and any single-label or fake-TLD name are all false, so
     * only a named site validates - never an address. Compared uppercased
     * against the ASCII TLD list, so a punycode "xn--..." host validates while
     * a raw-unicode one does not (links are expected in ASCII/punycode form).
     */
    public static function isValidHostname(string $host): bool
    {
        $host = rtrim(strtolower(trim($host)), '.');

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        $last_dot = strrpos($host, '.');

        return $last_dot !== false && TLDList::exists(substr($host, $last_dot + 1));
    }
}
