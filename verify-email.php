<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// A bare GET only ever shows a confirmation button; the token is consumed by
// the POST that button makes (carrying the CSRF token init.php verifies). The
// verification link is fetched automatically by email security scanners
// (SafeLinks, Mimecast, Gmail prefetch), so a GET-side verify would let a
// blind scanner consume the token before the user opened the message.
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');

$page = new Page(['title' => (string) (Strings::for('PageTitle')['verifyEmail'] ?? '')]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $token !== '' ? EmailVerification::verify($token) : null;

    $page -> addContent(new Paragraph($user_id !== null
        ? 'Your email has been verified. You can now use ' . Config::get('siteTitle') . '.'
        : 'That verification link is invalid or has expired.'));
    $page -> addContent(new Anchor(ServerURL::absolute('/'), 'Continue'));

    $page -> send();
    exit;
}

if ($token === '') {
    $page -> addContent(new Paragraph('That verification link is invalid or has expired.'));
    $page -> send();
    exit;
}

$page -> addContent(new Paragraph('Confirm that you want to verify this email address.'));
$page -> addContent(new EmailVerifyForm($token));

$page -> send();
