<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode(API_REQUEST_BODY, true);
$payload = is_array($payload) ? $payload : [];
$new_email = trim((string) ($payload['newEmail'] ?? ''));
$current_password = (string) ($payload['currentPassword'] ?? '');

// Throttle current-password guessing here too - see change-password.php.
// Separate from (and evaluated before) the change-email send limit below,
// which caps verification emails rather than password guesses; this shares the
// per-user password-verify key with the other password-confirming endpoints.
$password_rate_key = 'password-verify:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($password_rate_key, 10, 900)) {
    JSONResponse::error('Too many attempts. Please try again later.', 429) -> send();
}

// Gathered rather than answered one at a time - see api/change-password.php.
$refused = [];

if (!$current_user -> verifyPassword($current_password)) {
    RateLimiter::recordAttempt($password_rate_key);

    $refused['currentPassword'] = 'That is not your current password.';
}

if ($new_email === '' || filter_var($new_email, FILTER_VALIDATE_EMAIL) === false) {
    $refused['newEmail'] = 'Please give a valid email address.';
}

if ($refused !== []) {
    JSONResponse::fieldErrors($refused) -> send();
}

if (strcasecmp($new_email, (string) $current_user -> email) === 0) {
    JSONResponse::success(['changed' => false]) -> send();
}

// Each change sends a verification email - cap it so the endpoint can't be
// used to pump out mail.
$rate_key = 'change-email:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 5, 3600)) {
    JSONResponse::error('Too many email changes in a short time. Please try again later.', 429) -> send();
}

$taken_stmt = DB::run('
SELECT `userId`
    FROM `Users`
    WHERE `email` = ? AND `userId` != ?
', 'si', $new_email, $current_user -> userId);
mysqli_stmt_store_result($taken_stmt);

if (mysqli_stmt_num_rows($taken_stmt) > 0 || EmailChangeRevert::isReserved($new_email)) {
    JSONResponse::fieldError('newEmail', 'That address is already in use.') -> send();
}

RateLimiter::recordAttempt($rate_key);

// Captured before the overwrite - EmailChangeRevert needs it to know what to
// revert back to, and it's the one place a "wasn't you?" notice can reach the
// real owner if the new address belongs to whoever just hijacked the account.
$previous_email = (string) $current_user -> email;

// The new address is unverified until its owner proves it - the account drops
// back behind the verification gate until then.
$unverified = 0;

// The address and both recovery tokens land together. Mail is sent only after
// the transaction commits, so SMTP never holds the database row lock open.
try {
    [$verify_token, $revert_token] = DB::transaction(function () use ($new_email, $unverified, $current_user, $previous_email): array {
        DB::run('
UPDATE `Users`
    SET `email` = ?, `verified` = ?
    WHERE `userId` = ?
', 'sii', $new_email, $unverified, $current_user -> userId);

        return [
            EmailVerification::create((int) $current_user -> userId),
            EmailChangeRevert::create((int) $current_user -> userId, $previous_email),
        ];
    });
} catch (\mysqli_sql_exception $exception) {
    // The uniqueness check above has a TOCTOU gap: another account (or
    // another request from this same account) can claim this exact email
    // between the check and this UPDATE. `email` is UNIQUE - 1062 is
    // MySQL's duplicate-key error; anything else is a real failure, not a
    // race.
    if ($exception -> getCode() !== 1062) {
        throw $exception;
    }

    JSONResponse::fieldError('newEmail', 'That address is already in use.') -> send();
}

Auth::clearUserCache();
$updated_user = Auth::user();

// Sends the verification link to the new address. If the mailer is down this
// verifies the user directly and notifies the admin instead (sendMail's own
// failure handling), so nobody gets stranded behind a gate no email can clear.
EmailVerification::sendMail($updated_user, $verify_token);

// Sends a "wasn't you?" notice to the OLD address with a revert link - the
// real owner may know nothing about this change if the new address is one an
// attacker (who already has the password) controls.
EmailChangeRevert::sendMail($updated_user, $previous_email, $revert_token);

JSONResponse::success(['changed' => true]) -> send();
