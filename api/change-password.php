<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode(API_REQUEST_BODY, true);
$payload = is_array($payload) ? $payload : [];
$current_password = (string) ($payload['currentPassword'] ?? '');
$new_password = (string) ($payload['newPassword'] ?? '');
$confirm_password = (string) ($payload['confirmPassword'] ?? '');

// Throttle current-password guessing: anyone holding a logged-in session (a
// hijacked cookie, a borrowed browser) could otherwise brute-force the
// account's real password through this verify path. Keyed per user and shared
// with the other password-confirming endpoints (change-email, delete-account)
// so the attempts can't be multiplied across them.
$password_rate_key = 'password-verify:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($password_rate_key, 10, 900)) {
    JSONResponse::error('Too many attempts. Please try again later.', 429) -> send();
}

// Gathered rather than answered one at a time: a form that refuses the first
// thing it finds makes somebody press the button once per mistake to discover
// the next one.
$refused = [];

if (!$current_user -> verifyPassword($current_password)) {
    RateLimiter::recordAttempt($password_rate_key);

    $refused['currentPassword'] = 'That is not your current password.';
}

if (strlen($new_password) < 8) {
    $refused['newPassword'] = 'Use at least 8 characters.';
// bcrypt (password_hash's default) only uses the first 72 bytes and rejects longer input outright.
} elseif (strlen($new_password) > 72) {
    $refused['newPassword'] = 'Use at most 72 characters.';
}

if ($new_password !== $confirm_password) {
    $refused['confirmPassword'] = 'This does not match the new password.';
}

if ($refused !== []) {
    JSONResponse::fieldErrors($refused) -> send();
}

$hash = password_hash($new_password, PASSWORD_DEFAULT);

DB::run('
UPDATE `Users`
    SET `passwordHash` = ?
    WHERE `userId` = ?
', 'si', $hash, $current_user -> userId);

// The old password's sessions die with it - any other browser (or thief)
// holding one gets logged out. This session proved the current password, so
// it adopts the new version and stays.
$_SESSION['sessionVersion'] = User::bumpSessionVersion((int) $current_user -> userId);
Auth::clearUserCache();

JSONResponse::success(['changed' => true]) -> send();
