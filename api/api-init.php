<?php

declare(strict_types=1);

// Every api/*.php requires THIS instead of src/init.php directly. It flags the
// request as an API request up front, then hands off to the normal init. This
// is how init.php decides to answer failures (server error, CSRF, not-logged-
// in, ...) with JSON rather than an HTML error page - a reliable flag set at
// the actual API entry point.
const IS_API_REQUEST = true;

require __DIR__ . '/../src/init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

// Read the request once and cap it before endpoint code can accidentally pull
// an unbounded body into memory. Ordinary template endpoints accept one flat
// JSON object; an endpoint that deliberately needs nested JSON can opt out by
// defining API_ALLOWS_NESTED_JSON before requiring this file.
define('API_REQUEST_BODY', (string) file_get_contents('php://input', false, null, 0, APIRequestBody::MAX_BYTES + 1));

if (strlen(API_REQUEST_BODY) > APIRequestBody::MAX_BYTES) {
    JSONResponse::error('Payload too large.', 413) -> send();
}

if (!defined('API_ALLOWS_NESTED_JSON') && !APIRequestBody::hasOnlyTopLevelFields(API_REQUEST_BODY)) {
    JSONResponse::error('Nested payload values are not allowed.', 400) -> send();
}
