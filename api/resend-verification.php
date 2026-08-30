<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

if ($current_user -> verified) {
    JSONResponse::error('Already verified', 422) -> send();
}

$rate_key = 'resend-verification:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 3, 900)) {
    JSONResponse::error('Too many verification emails sent. Please try again later.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

EmailVerification::sendFor($current_user);

JSONResponse::success(['sent' => true]) -> send();
