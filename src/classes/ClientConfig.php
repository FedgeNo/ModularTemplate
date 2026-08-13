<?php

declare(strict_types=1);

/**
 * Builds the client-side configuration and sends it as a JSON cookie, read back
 * by ClientConfig.js. Call ClientConfig::send() before the response body is sent
 * (Page::send() does). This is the only channel server-side values reach the
 * client by: a value every page wants is listed here, and a value one page
 * needs rides through Page::$clientConfig as an override.
 *
 * Configuration only - what the site is and who is signed in. The data of
 * whatever is being looked at (a conversation's keys, a list's next page)
 * belongs on the element that needs it or in the endpoint that serves it: the
 * cookie is sent back up on every request from then on, so anything put here
 * is paid for by every image, script and API call the page makes afterwards.
 */
class ClientConfig
{
    /** @param array<string, mixed> $overrides Additional or override values */
    public static function send(array $overrides = []): void
    {
        $current_user = Auth::user();

        $config = array_merge([
            'currentUserId' => $current_user ?-> userId,
            'currentUserUsername' => $current_user ?-> slug,
            'currentUserCanModerate' => Auth::canModerate(),
            'siteURL' => ServerURL::absolute(''),
            // Which language the page was rendered in, so the client twins can
            // fetch the same words rather than a second copy of them travelling
            // in here - the table is far too big for a cookie.
            'locale' => Strings::locale(),
            'serverTime' => time() * 1000,
        ], $overrides);

        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        setcookie(
            'APP-CONFIG',
            $json,
            [
                'expires'  => 0,               // session cookie
                'path'     => '/',
                'secure'   => true,
                'httponly' => false,
                'samesite' => 'Strict'
            ]
        );
    }
}
