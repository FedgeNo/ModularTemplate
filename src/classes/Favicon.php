<?php

declare(strict_types=1);

/**
 * The site's browser-tab icon. One place decides where every page's
 * <link rel="icon"> points, so replacing the icon (or later making it
 * admin-uploadable) touches only this class.
 */
class Favicon
{
    public static function URL(): string
    {
        return ServerURL::absolute('/favicon.ico');
    }
}
