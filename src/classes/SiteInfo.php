<?php

declare(strict_types=1);

/**
 * The site's admin-editable info texts - the About overview, Terms of Service,
 * and Privacy Policy: plain text stored in Settings, with shipped defaults
 * until the admin writes their own. Rendered on /about/, /terms/, and
 * /privacy/ by InfoText.
 */
class SiteInfo
{
    public const ABOUT_SETTING = 'aboutText';
    public const TERMS_SETTING = 'termsText';
    public const PRIVACY_SETTING = 'privacyText';

    private const DEFAULT_ABOUT = 'A self-hosted web application.';

    private const DEFAULT_TERMS = 'You are responsible for what you post. Do not use this site to commit crimes or to post content that is illegal in the site\'s or your own jurisdiction. The site administrator may remove content or accounts at their discretion.';

    private const DEFAULT_PRIVACY = 'Your password is stored encrypted (hashed with a one-way algorithm) - nobody, including the site administrator, can read it.';

    public static function about(): string
    {
        $stored = (string) Settings::get(self::ABOUT_SETTING, '');

        return $stored !== '' ? $stored : self::DEFAULT_ABOUT;
    }

    /**
     * A one-line site description for meta tags and feeds: the opening
     * paragraph of the About text, whitespace-collapsed so it sits on a single
     * line. Tracks whatever the admin saved as the About text (falling back to
     * the shipped default) rather than a hardcoded tagline.
     */
    public static function description(): string
    {
        $first_paragraph = preg_split('/\R\s*\R/', trim(self::about()))[0];

        return trim(preg_replace('/\s+/', ' ', $first_paragraph));
    }

    public static function terms(): string
    {
        $stored = (string) Settings::get(self::TERMS_SETTING, '');

        return $stored !== '' ? $stored : self::DEFAULT_TERMS;
    }

    public static function privacy(): string
    {
        $stored = (string) Settings::get(self::PRIVACY_SETTING, '');

        return $stored !== '' ? $stored : self::DEFAULT_PRIVACY;
    }
}
