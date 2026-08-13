<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$page = new Page(['title' => (string) (Strings::for('PageTitle')['login'] ?? '')]);

$page -> addContent(new LoginForm());

$page -> addContent(new Anchor(ServerURL::absolute('/forgot-password'), 'Forgot Password?'));

$page -> addContent(new Anchor(ServerURL::absolute('/signup'), 'Need an account? Sign Up'));

$page -> send();
