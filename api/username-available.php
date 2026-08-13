<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// The same normalisation sign-up applies, so this answers for the name that
// would actually be stored rather than for what was typed.
$username = User::normaliseUsername((string) ($payload['username'] ?? ''));

if ($username === '') {
    JSONResponse::success(['username' => '', 'available' => false]) -> send();
}

// This endpoint answers "does this account exist" for anyone who asks, which
// is the same question the sign-up form itself answers by rejecting a taken
// name - but here it can be asked in a tight loop. The limit is what keeps it
// from being a comfortable way to enumerate the whole user list.
$rate_key = 'username-available:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 120, 600)) {
    JSONResponse::error('Too many checks. Please wait a moment.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

$taken = DB::row('
SELECT `userId`
    FROM `Users`
    WHERE `slug` = ?
', 'User', 's', $username);

$available = $taken === null;

JSONResponse::success(['username' => $username, 'available' => $available]) -> send();
