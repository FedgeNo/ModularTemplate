<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Logging out happens via AJAX now (api/logout.php), so a direct request
// here - GET or POST - just bounces home without touching the session. A
// GET must never log anyone out on its own: that would let a third-party
// page force-log-out a victim with something as simple as an <img> tag.
header('Location: ' . ServerURL::absolute('/'));
exit;
