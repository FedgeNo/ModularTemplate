<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// No login required. Somebody signed out is exactly who the prompt asks, and
// their answer is kept for as long as their session lasts; a member's is
// written to their row as well, so it follows them to the next browser.
$payload = json_decode(API_REQUEST_BODY, true);
$payload = is_array($payload) ? $payload : [];

// Checked against the languages this installation actually has, which is the
// same list the selector is built from - so nothing can be chosen that has no
// words behind it.
if (!Strings::choose((string) ($payload['locale'] ?? ''))) {
    JSONResponse::error('That is not a language this site has.', 422) -> send();
}

JSONResponse::success(['locale' => Strings::locale()]) -> send();
