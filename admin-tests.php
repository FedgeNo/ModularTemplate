<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

// Same gate as Admin Settings: the primary admin (userId 1) only, not general
// moderators.
if (Auth::id() !== 1) {
    require __DIR__ . '/404.php';
    exit;
}

// Runs the whole suite on load, which takes a few seconds - kept on its own
// page (linked from Admin Settings) so it never slows that page down.
$page = new Page(['title' => (string) (Strings::for('PageTitle')['adminTests'] ?? '')]);

$page -> addContent(new TestResults());

$page -> send();
