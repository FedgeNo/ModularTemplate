<?php

declare(strict_types=1);

// This script is reached whenever DB::connection() fails. If .env
// already exists, that means an ESTABLISHED install whose database is down
// or misconfigured - not a fresh one. Never show the setup wizard in that
// state: it writes .env, so exposing it during an outage would let any
// visitor point the site at their own database. Show a maintenance page
// instead. (userId is dropped from the in-memory session because rendering
// a page for a logged-in user needs the database; session_abort() closes
// the session without saving, so nobody actually gets logged out by this.)
if (is_file(__DIR__ . '/../.env')) {
    unset($_SESSION['userId']);
    session_abort();
    http_response_code(503);

    $page = new Page(['title' => 'Site Unavailable']);
    $page -> addContent(new Paragraph('The site can\'t reach its database right now. Please try again in a few minutes.'));
    $page -> addContent(new Notice('If you run this site: the database connection using the credentials in .env is failing - check that the database server is running and that those credentials are still valid. This page is shown instead of the setup wizard precisely so a database outage can\'t be used to reconfigure the site.'));
    $page -> send();
    exit;
}

// A leftover session from before a reinstall would make the page try
// (and fail) to load its user from the not-yet-configured database.
unset($_SESSION['userId']);

$environment_errors = [];

foreach (EnvironmentChecker::checks() as $result) {
    if (!$result['ok']) {
        $environment_errors[] = $result['message'];
    }
}

$errors = [];
$success = false;

if ($environment_errors === [] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['CSRFToken'] ?? null;

    if (!CSRF::verify(is_string($csrf_token) ? $csrf_token : null)) {
        $errors[] = 'Your session expired or the form was tampered with. Please reload and try again.';
    } else {
        $site_url = trim((string) ($_POST['siteURL'] ?? ''));
        $site_title = trim((string) ($_POST['siteTitle'] ?? ''));
        $mail_from_address = trim((string) ($_POST['mailFromAddress'] ?? ''));
        $db_host = trim((string) ($_POST['DBHost'] ?? ''));
        $db_port = trim((string) ($_POST['DBPort'] ?? ''));
        $db_database = trim((string) ($_POST['DBDatabase'] ?? ''));
        $admin_username = (string) ($_POST['adminUsername'] ?? '');
        $admin_password = (string) ($_POST['adminPassword'] ?? '');
        $server_name_confirmed = ($_POST['serverNameConfirmed'] ?? '') === '1';

        if ($site_url === '' || filter_var($site_url, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'A valid site URL is required.';
        } elseif (!str_starts_with($site_url, 'https://')) {
            // HTTPS is a requirement, not a preference - the site refuses to
            // serve over plain HTTP, so an http URL would produce an install
            // that doesn't work. Make the admin sort TLS out now, not later.
            $errors[] = 'The site URL must be an https:// URL - this app requires HTTPS and will not serve over plain HTTP. For a real domain use Let\'s Encrypt (certbot); for localhost use a locally-trusted certificate (mkcert) or your distribution\'s self-signed default. See README.md\'s HTTPS section, then enter the https:// URL here.';
        } else {
            // The string prefix check above only proves the URL *says*
            // https:// - test for real: connect to the site's own hostname
            // (never 127.0.0.1 - a VirtualHost setup routes by the Host
            // header/SNI, so loopback may not reach this site at all) and
            // see what actually happens. Same live proofs bin/install.php
            // runs, just triggered by the form instead of a terminal prompt.
            $site_host = (string) (parse_url($site_url, PHP_URL_HOST) ?: '');
            $site_port = parse_url($site_url, PHP_URL_PORT);
            $server_name_value = $site_host . ($site_port !== null ? ':' . $site_port : '');

            $https_serving = EnvironmentChecker::httpsServing($server_name_value);

            if ($https_serving === false) {
                $errors[] = 'The site URL is https://, but a real HTTPS connection to ' . $server_name_value . ' failed at the TLS handshake itself - something is listening on the port without actually serving TLS. Check that your web server\'s SSL certificate/key are configured and mod_ssl (or equivalent) is loaded, then resubmit.';
            }

            // By default a web server builds its HTTPS-redirect target from
            // whatever Host header the request arrived with, not a fixed
            // configured name - anyone can forge one and get redirected
            // somewhere of their choosing. "ServerName <host>" +
            // "UseCanonicalName On" fix that; prove it live with a forged
            // Host header rather than just asking, falling back to the
            // confirmation checkbox only when that live test is inconclusive.
            $spoof_test = EnvironmentChecker::hostHeaderSpoofable($site_host);

            if ($spoof_test === true) {
                $errors[] = 'The HTTPS redirect can be spoofed via a forged Host header (confirmed live: a request with a fake Host header got redirected to that same fake host) - anyone can 301 a victim to a domain of their choosing. Set "ServerName ' . $server_name_value . '" and "UseCanonicalName On" in your web server\'s config (httpd.conf\'s top level if you\'re not using a <VirtualHost>, or inside the vhost if you are), then resubmit. See README.md\'s HTTPS section.';
            } elseif ($spoof_test === null && !$server_name_confirmed) {
                $errors[] = 'Could not confirm live that "ServerName ' . $server_name_value . '" and "UseCanonicalName On" are set in your web server\'s config (inconclusive - DNS may not point here yet, or the web server isn\'t reachable this way). Check the confirmation box above once you\'ve set them, then resubmit. See README.md\'s HTTPS section.';
            }
        }

        if ($site_title === '') {
            $errors[] = 'A site title is required.';
        }

        if ($mail_from_address === '' || filter_var($mail_from_address, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'A valid mail from address is required.';
        }

        if (!preg_match('/^[A-Za-z0-9_.:-]+$/', $db_host)) {
            $errors[] = 'Database host contains invalid characters.';
        }

        if (!preg_match('/^[0-9]{1,5}$/', $db_port) || (int) $db_port < 1 || (int) $db_port > 65535) {
            $errors[] = 'Database port must be a number between 1 and 65535.';
        }

        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db_database)) {
            $errors[] = 'Database name may only contain letters, numbers, and underscores.';
        }

        if ($admin_username === '') {
            $errors[] = 'A database admin username is required.';
        }

        $admin_connection = null;

        if ($errors === []) {
            try {
                $admin_connection = mysqli_connect($db_host, $admin_username, $admin_password, null, (int) $db_port);
            } catch (\mysqli_sql_exception $exception) {
                $errors[] = 'Could not connect with the database admin credentials: ' . $exception -> getMessage();
            }
        }

        $runtime_account = null;

        if ($errors === []) {
            try {
                $initial_settings = [
                    Mailer::FROM_ADDRESS_SETTING => $mail_from_address,
                ];

                $runtime_account = Installer::provisionDatabase($admin_connection, $db_database, $initial_settings);
                mysqli_close($admin_connection);
            } catch (\mysqli_sql_exception $exception) {
                $errors[] = 'Database setup failed: ' . $exception -> getMessage();
            } catch (\RuntimeException $exception) {
                $errors[] = $exception -> getMessage();
            }
        }

        // Prove the runtime account actually works before writing .env and
        // declaring success - a reinstall where the account pre-existed, a
        // grant that didn't apply, etc. would otherwise leave the site unable
        // to connect while the wizard reports "Setup Complete".
        if ($errors === [] && $runtime_account !== null) {
            try {
                $runtime_check = mysqli_connect($db_host, $runtime_account['username'], $runtime_account['password'], $db_database, (int) $db_port);
                mysqli_close($runtime_check);
            } catch (\mysqli_sql_exception $exception) {
                $errors[] = 'The runtime database account was created but a test connection with it failed: ' . $exception -> getMessage();
            }
        }

        if ($errors === []) {
            $env_contents = Installer::envContents([
                'DB_HOST' => $db_host,
                'DB_PORT' => $db_port,
                'DB_DATABASE' => $db_database,
                'DB_USERNAME' => $runtime_account['username'],
                'DB_PASSWORD' => $runtime_account['password'],
                'SITE_URL' => $site_url,
                'SITE_TITLE' => $site_title,
            ]);

            if (file_put_contents(__DIR__ . '/../.env', $env_contents) === false) {
                $errors[] = 'Could not write .env - check that the web server user can write to the project root.';
            } else {
                // Lock down .env - it holds DB_PASSWORD. It's written by
                // the web-server user here, so 0600 keeps it readable by the app
                // while denying group/other any read or write.
                @chmod(__DIR__ . '/../.env', 0600);
                $success = true;
            }
        }
    }
}

if ($success) {
    $page = new Page(['title' => 'Setup Complete']);

    $page -> addContent(new Paragraph('Setup finished - the database, a least-privilege runtime account, and .env are all in place. Two small steps remain:'));
    $page -> addContent(new SetupNextSteps());
} elseif ($environment_errors !== []) {
    $page = new Page(['title' => 'Set Up']);

    $page -> addContent(new Paragraph('Welcome! Before setup can continue, this server is missing some prerequisites:'));
    $page -> addContent(new ErrorList($environment_errors));
    $page -> addContent(new Notice('Fix these on the server, then reload this page to re-check.'));
} else {
    $page = new Page(['title' => 'Set Up']);

    if ($errors !== []) {
        $page -> addContent(new ErrorList($errors));
    }

    $page -> addContent(new Paragraph('Welcome! All environment checks passed. Submitting this form creates the database (if it doesn\'t exist yet), a least-privilege runtime database account with a random password, and the schema, then writes it all to .env. The admin credentials are used once for provisioning and are never stored.'));
    $page -> addContent(new SetupForm());
}

$page -> send();
