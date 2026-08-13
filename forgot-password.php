<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$page = new Page(['title' => (string) (Strings::for('PageTitle')['forgotPassword'] ?? '')]);
$page -> addContent(new PasswordResetRequestForm());
$page -> send();
