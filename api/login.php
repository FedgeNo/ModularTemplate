<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$rate_key = 'login:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 10, 900)) {
    JSONResponse::error('Too many login attempts. Please try again later.', 429) -> send();
}

$identifier = trim((string) ($payload['identifier'] ?? ''));
$password = (string) ($payload['password'] ?? '');

if ($identifier === '' || $password === '') {
    // Named, since an empty box is not a secret. The refusal further down -
    // that the pair did not match - deliberately names neither: which half was
    // wrong is exactly what somebody guessing wants to know.
    $refused = [];

    if (trim($identifier) === '') {
        $refused['identifier'] = 'Please give your username or email.';
    }

    if ($password === '') {
        $refused['password'] = 'Please give your password.';
    }

    JSONResponse::fieldErrors($refused, 'Username/email and password are required.') -> send();
}

// A second limit keyed on the account, not the address - the IP limit alone
// never trips for a guessing attack spread across many machines that all
// target one account. Keyed on the account the identifier resolves to, so the
// username and email forms of one account share a single budget rather than
// getting a lockout each; falls back to the normalized identifier when nothing
// matches, so a nonexistent identifier is still throttled and the lockout
// behaves the same whether or not the account exists (no enumeration). The
// fallback is truncated to fit RateLimitAttempts.rateKey's varchar(255) - an
// oversized identifier would otherwise make the INSERT throw a data-truncation
// error (a 500) instead of counting the attempt.
$account_id = Auth::userIdForIdentifier($identifier);
$account_rate_key = $account_id !== null
    ? 'login-user:' . $account_id
    : substr('login-user:' . strtolower($identifier), 0, 255);

if (RateLimiter::tooManyAttempts($account_rate_key, 10, 900)) {
    JSONResponse::error('Too many login attempts for this account. Please try again later.', 429) -> send();
}

$user = Auth::verifyCredentials($identifier, $password);

if ($user === null) {
    // Only failed attempts count toward the limits, so legitimate logins
    // never eat into them.
    RateLimiter::recordAttempt($rate_key);
    RateLimiter::recordAttempt($account_rate_key);

    JSONResponse::error('Incorrect username/email or password.', 422) -> send();
}

if ($user -> banned) {
    // Correct password but banned: show the reason a moderator gave, rather
    // than logging them in.
    $message = $user -> banReason !== null && $user -> banReason !== ''
        ? 'Your account has been banned. Reason: ' . $user -> banReason
        : 'Your account has been banned.';

    JSONResponse::error($message, 403) -> send();
}

Auth::login($user);

JSONResponse::success(['loggedIn' => true]) -> send();
