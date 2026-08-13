<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// A GET must never log anyone out on its own: that would let a third-party
// page force-log-out a victim (a plain GET link/image), and init.php's
// centralized CSRF check only covers POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::logout();

JSONResponse::success(['loggedOut' => true]) -> send();
