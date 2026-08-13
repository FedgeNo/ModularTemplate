<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => (string) (Strings::for('PageTitle')['checkInbox'] ?? '')]);

$page -> addContent(new VerificationNotice());

$page -> send();
