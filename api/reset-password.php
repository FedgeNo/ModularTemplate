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

$token = (string) ($payload['token'] ?? '');
$new_password = (string) ($payload['newPassword'] ?? '');
$confirm_password = (string) ($payload['confirmPassword'] ?? '');

// The token is 256 bits and expires in an hour, so guessing one isn't the
// threat this stops - it just refuses to be a free oracle for anyone grinding
// tokens at the endpoint. Recorded before the token is looked at so a wrong
// guess costs an attempt; a legitimate visitor spends one attempt total.
$rate_key = 'reset-password:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 10, 900)) {
    JSONResponse::error('Too many password reset attempts. Please try again later.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

$user_id = $token !== '' ? PasswordReset::verify($token) : null;

if ($user_id === null) {
    JSONResponse::error('That password reset link is invalid or has expired.', 422) -> send();
}

// Gathered rather than answered one at a time - see api/change-password.php.
$refused = [];

if (strlen($new_password) < 8) {
    $refused['newPassword'] = 'Use at least 8 characters.';
} elseif (strlen($new_password) > 72) {
    // bcrypt (password_hash's default) only uses the first 72 bytes and rejects longer input outright.
    $refused['newPassword'] = 'Use at most 72 characters.';
}

if ($new_password !== $confirm_password) {
    $refused['confirmPassword'] = 'This does not match the new password.';
}

if ($refused !== []) {
    JSONResponse::fieldErrors($refused) -> send();
}

$user = User::load($user_id);

// The account can vanish between the token being issued and used (deleted,
// admin-removed) - the token points at nobody, same outcome as an invalid one.
if ($user === null) {
    JSONResponse::error('That password reset link is invalid or has expired.', 422) -> send();
}

// A no-op, same treatment as resubmitting your current email in
// change-email.php: nothing to do, and the token stays valid so a follow-up
// attempt with an actually-different password still works.
if ($user -> verifyPassword($new_password)) {
    JSONResponse::success(['reset' => false]) -> send();
}

PasswordReset::consume($token, $new_password);

JSONResponse::success(['reset' => true]) -> send();
