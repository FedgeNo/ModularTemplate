<?php

declare(strict_types=1);

/**
 * Uptime-monitoring endpoint at /health: exercises PHP and a real database
 * round trip. Deliberately does NOT go through src/init.php - init's
 * connection failure path renders the setup/maintenance page with a 200,
 * which would make a monitor see a dead database as healthy. No details are
 * exposed either way - just healthy or not.
 */

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

try {
    mysqli_query(DB::connection(), '
SELECT 1
');
} catch (\Throwable $exception) {
    JSONResponse::error('unhealthy', 503) -> send();
}

JSONResponse::success(['ok' => true]) -> send();
