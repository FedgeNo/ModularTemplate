<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// No login required - the token itself is the proof of authorization (same
// as password reset), since the whole point is recovering an account whose
// current email may belong to whoever changed it.
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');

$page = new Page(['title' => (string) (Strings::for('PageTitle')['revertEmail'] ?? '')]);

// Only a deliberate POST (carrying the CSRF token init.php verifies) reverts.
// A GET renders a confirmation button instead: the revert link is mailed to
// the account's pre-change address, and email security scanners (SafeLinks,
// Mimecast, Gmail prefetch) fetch every link automatically, so a GET-side
// revert would let a blind scanner fetch silently undo a legitimate change
// and sign the user out of every device.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($token !== '' && EmailChangeRevert::consume($token)) {
        $page -> addContent(new Paragraph('Your email address has been reverted and every device has been signed out of your account.'));
        $page -> addContent(new Paragraph('If you\'re not sure how this happened, change your password as soon as you log back in.'));
        $page -> addContent(new Anchor(ServerURL::absolute('/login'), 'Log In'));
        $page -> addContent(new Anchor(ServerURL::absolute('/forgot-password'), 'Forgot Password?'));
    } else {
        $page -> addContent(new Paragraph('That revert link is invalid or has expired.'));
    }

    $page -> send();
    exit;
}

if ($token === '') {
    $page -> addContent(new Paragraph('That revert link is invalid or has expired.'));
    $page -> send();
    exit;
}

$page -> addContent(new Paragraph('Revert the recent email address change on your account? This also signs every device out of your account.'));
$page -> addContent(new EmailRevertForm($token));

$page -> send();
