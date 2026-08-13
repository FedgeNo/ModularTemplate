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

$username = User::normaliseUsername((string) ($payload['username'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$display_name = mb_substr(trim((string) ($payload['displayName'] ?? '')), 0, 50);

// Keyed by the input each is about, so all of them are answered at once and
// each lands under its own box rather than in one sentence at the corner of
// the screen. Anything with no box of its own has no key - see below.
$errors = [];

if ($username === '') {
    $errors['username'] = 'Please choose a username.';
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors['email'] = 'Please give a valid email address.';
}

if (strlen($password) < 8) {
    $errors['password'] = 'Use at least 8 characters.';
} elseif (strlen($password) > 72) {
    // bcrypt (password_hash's default) only uses the first 72 bytes and rejects longer input outright.
    $errors['password'] = 'Use at most 72 characters.';
}

$rate_key = 'signup:' . (ServerURL::clientIP() ?? 'unknown');

if ($errors === []) {
    if (RateLimiter::tooManyAttempts($rate_key, 5, 3600)) {
        $errors[] = 'Too many signups from your network. Please try again later.';
    } else {
        // Count every well-formed attempt, not just a successful signup -
        // otherwise a probe for which emails already exist (via the
        // "already taken" response) never counts toward the limit and
        // account enumeration is unthrottled.
        RateLimiter::recordAttempt($rate_key);
    }
}

if ($errors === []) {
    $stmt = DB::run('
SELECT `userId`
    FROM `Users`
    WHERE `slug` = ? OR `email` = ?
', 'ss', $username, $email);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0 || EmailChangeRevert::isReserved($email)) {
        // Deliberately not said of one or the other: which of the two is taken
        // is what an account-enumeration probe is asking, and the rate limit
        // above only slows that down rather than answering it differently.
        $errors['username'] = 'That username or email is already taken.';
    }
}

if ($errors !== []) {
    // The ones keyed by a field go under those fields; the rest - a rate
    // limit - have no box to sit under and stay in the summary, which is what
    // the client shows when nothing could be marked up.
    $named = array_filter($errors, static fn (string $key): bool => !ctype_digit($key), ARRAY_FILTER_USE_KEY);

    JSONResponse::fieldErrors($named, implode(' ', $errors)) -> send();
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$unverified = 0;

DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `verified`)
    VALUES (?, ?, ?, ?, ?)
', 'ssssi', $username, $email, $hash, $display_name, $unverified);
$new_user_id = (int) mysqli_insert_id(DB::connection());

$user = new User();
$user -> userId = $new_user_id;
$user -> slug = $username;
$user -> email = $email;
$user -> title = $display_name;
$user -> verified = $unverified;

Auth::login($user);

$auto_verified = EmailVerification::sendFor($user);

JSONResponse::success(['signedUp' => true, 'verified' => $auto_verified]) -> send();
