<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$theme = (string) ($payload['theme'] ?? '');

// Asked of the same list that builds the menu, rather than a copy of it - two
// lists would drift the first time a theme was added to only one of them.
if (!ThemeSelector::offers($theme)) {
    JSONResponse::error('Invalid theme', 422) -> send();
}

DB::run('
UPDATE `Users`
    SET `theme` = ?
    WHERE `userId` = ?
', 'si', $theme, $current_user -> userId);

JSONResponse::success(['theme' => $theme]) -> send();
