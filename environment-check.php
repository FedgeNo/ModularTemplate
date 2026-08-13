<?php

declare(strict_types=1);

/**
 * Internal-use endpoint for bin/install.php's live environment checks:
 * reports every fact PHP actually resolves under the web SAPI that the CLI
 * SAPI can't reliably speak for - loaded extensions that can differ on
 * split-package distros, the temp dir and writing Unix user an FPM pool
 * commonly sets independently. Raw facts only, no pass/fail judgment -
 * EnvironmentChecker (which already has that logic for the CLI-native case)
 * applies the same thresholds to whichever source (ini_get() directly, or
 * this) is live. Deliberately DB-independent (must work even pre-install,
 * before .env exists) and exposes nothing sensitive - none of this is secret.
 */

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Every legitimate caller (EnvironmentChecker::liveFacts()) reaches this over
// loopback, by design - it's how bin/install.php and the setup wizard probe
// the web SAPI's own view of the environment. Nothing here is secret, but
// there's no reason for it to answer a request that didn't come from this
// machine either.
//
// REMOTE_ADDR alone can't tell that apart: behind a TLS-terminating reverse
// proxy (see ServerURL::isHTTPS()), every request - including from real
// external visitors - reaches the web server over loopback. So REMOTE_ADDR
// is 127.0.0.1 for EVERYONE, not just a direct probe. The proxy adds
// X-Forwarded-For to whatever it forwards; a genuine direct loopback call
// (curl straight to http://127.0.0.1/environment-check, bypassing the proxy
// entirely) never has that header at all. Requiring both is what actually
// restricts this to a direct local call.
if (
    !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
) {
    JSONResponse::error('Not found', 404) -> send();
}

// The Unix uid the web server actually runs as. get_current_user() returns the
// SCRIPT's owner, not the process user, so instead create a file and read its
// owner - a file is owned by the effective uid of the process that made it, so
// this is the real web-server uid. The CLI installer (running as root under
// sudo) maps it to a name to set file ownership correctly.
$web_server_uid = null;
$uid_probe = sys_get_temp_dir() . '/app-uid-probe-' . bin2hex(random_bytes(6));

if (@file_put_contents($uid_probe, '') !== false) {
    $probe_owner = @fileowner($uid_probe);

    if ($probe_owner !== false) {
        $web_server_uid = $probe_owner;
    }

    @unlink($uid_probe);
}

JSONResponse::success([
    'phpVersion' => PHP_VERSION,
    'webServerUid' => $web_server_uid,
    // Apache sets this itself; nginx (fronting PHP-FPM) passes it through via
    // fastcgi_params. A genuine live signal of what's actually serving this
    // request - stronger evidence than a process-name check (pgrep just
    // shows something with that name is running, not that it's the one
    // handling traffic, and says nothing when a reverse proxy fronts it).
    'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? null,
    'extensions' => [
        'mysqli' => extension_loaded('mysqli'),
        'curl' => extension_loaded('curl'),
        'dom' => extension_loaded('dom'),
        'libxml' => extension_loaded('libxml'),
        'mbstring' => extension_loaded('mbstring'),
    ],
    'tempDir' => sys_get_temp_dir(),
    'tempDirWritable' => is_writable(sys_get_temp_dir()),
    'currentUser' => get_current_user(),
]) -> send();
