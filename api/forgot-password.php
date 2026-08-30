<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

$payload = json_decode(API_REQUEST_BODY, true);
$payload = is_array($payload) ? $payload : [];

$email = trim((string) ($payload['email'] ?? ''));

$rate_key = 'forgot-password:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 5, 900)) {
    JSONResponse::error('Too many password reset requests. Please try again later.', 429) -> send();
}

if ($email !== '') {
    RateLimiter::recordAttempt($rate_key);

    $user = DB::row('
SELECT *
    FROM `Users`
    WHERE `email` = ?
', 'User', 's', $email);

    if ($user !== null) {
        PasswordReset::sendFor($user);
    }
}

// Always the same response regardless of whether the email matched, to avoid
// leaking which emails have accounts.
JSONResponse::success(['sent' => true]) -> send();
