<?php

declare(strict_types=1);

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'app'),
    'username' => Env::get('DB_USERNAME', 'app'),
    'password' => Env::get('DB_PASSWORD', 'change-me'),
    // The mail "from" address and the SMTP relay (host/port/username/password/
    // encryption) live in the Settings DB table, editable live from the Admin
    // Settings page (see Mailer's *_SETTING constants). mailFromName falls
    // back to the site's own title, the only name it could honestly sign mail
    // with (a missing display name is cosmetic either way); the "from" address
    // has no config.php key at all - a missing one isn't safe to silently
    // paper over with a fake one (see Mailer::send()). Left empty, Mailer's
    // SMTP relay falls back to PHP's mail() (the local sendmail handoff, which
    // on a typical VPS lands straight in spam folders).
    'mailFromName' => Env::get('MAIL_FROM_NAME', '') ?: Env::get('SITE_TITLE', ''),
    // No default: a placeholder here would be a real address belonging to
    // somebody else, and the canonical-host redirect would send every visitor
    // to it. Empty is a misconfiguration, caught in init.php rather than
    // papered over.
    'siteURL' => Env::get('SITE_URL', ''),
    // No default, unlike every other key here: a site's title is its own name,
    // and there is no name that would be right to hand someone else's site.
    // Both install paths require one and bin/install.php fails without it, so
    // the empty string is a misconfiguration rather than a state to design for.
    'siteTitle' => Env::get('SITE_TITLE', ''),
];
