<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// This endpoint sends email but reads no POST fields, so without an
// explicit method requirement a plain GET link would trigger it - and
// init.php's centralized CSRF check only covers POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

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
