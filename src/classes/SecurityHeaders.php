<?php

declare(strict_types=1);

class SecurityHeaders
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }

        return self::$nonce;
    }

    public static function send(): void
    {
        $is_https = ServerURL::isHTTPS();
        $nonce = self::nonce();

        $csp = implode('; ', [
            'default-src \'self\'',
            'script-src \'self\' \'nonce-' . $nonce . '\' https://cdn.jsdelivr.net https://challenges.cloudflare.com https://www.google.com https://www.gstatic.com',
            // 'unsafe-inline' is here for emoji-picker-element, which builds its
            // own <style> element and sets textContent on it inside its shadow
            // root (picker.js: attachShadow, then createElement('style')). CSP
            // reaches into shadow DOM, so without this the emoji picker renders
            // completely unstyled. It can't be narrowed to a nonce either -
            // third-party library code has no way to carry ours.
            //
            // The site's own markup needs none of it: no page renders a <style>
            // block, and the handful of inline style attributes it does emit
            // (AvatarInitial's hue, two display:none) could become classes. JS
            // setting el.style.* is CSSOM and isn't governed by this at all, so
            // Leaflet's tile positioning doesn't depend on it.
            'style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://fonts.googleapis.com',
            // Map tiles come from a configurable (admin-set) provider host, and
            // Leaflet's marker icons from the jsDelivr CDN - both are <img> loads
            // from hosts not known here (this runs before the DB is up, so the
            // configured tile host can't be read). Tiles are non-executable
            // images, so allowing any HTTPS image source is a contained widening.
            // blob: is the composer's attachment previews - object URLs over
            // files still sitting in the browser's own memory.
            'img-src \'self\' data: blob: https:',
            'font-src \'self\' https://cdn.jsdelivr.net https://fonts.gstatic.com',
            'media-src \'self\'',
            'frame-src https://challenges.cloudflare.com https://www.google.com',
            // The socket is this host's own, on its own port - the client builds
            // that address from window.location.hostname and can reach nowhere
            // else. Naming the host rather than wildcarding it keeps connect-src
            // from being a way to talk to somebody else's server on that port.
            'connect-src \'self\' https://cdn.jsdelivr.net https://challenges.cloudflare.com https://www.google.com https://www.gstatic.com wss://' . ServerURL::host() . ':' . Config::get('WSPort'),
            'object-src \'none\'',
            'base-uri \'self\'',
            'form-action \'self\'',
            'frame-ancestors \'none\'',
        ]);

        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if ($is_https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
