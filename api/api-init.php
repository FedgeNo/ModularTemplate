<?php

declare(strict_types=1);

// Every api/*.php requires THIS instead of src/init.php directly. It flags the
// request as an API request up front, then hands off to the normal init. This
// is how init.php decides to answer failures (server error, CSRF, not-logged-
// in, ...) with JSON rather than an HTML error page - a reliable flag set at
// the actual API entry point.
const IS_API_REQUEST = true;

require __DIR__ . '/../src/init.php';
