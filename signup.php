<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$page = new Page(['title' => (string) (Strings::for('PageTitle')['signup'] ?? '')]);

$page -> addContent(new SignupForm());

$page -> send();
