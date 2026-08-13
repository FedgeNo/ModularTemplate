<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$mail_from_address = trim((string) ($payload['mailFromAddress'] ?? ''));
$mail_from_name = trim((string) ($payload['mailFromName'] ?? ''));
$smtp_host = trim((string) ($payload['smtpHost'] ?? ''));
$smtp_port = trim((string) ($payload['smtpPort'] ?? ''));
$smtp_username = trim((string) ($payload['smtpUsername'] ?? ''));
$smtp_password = (string) ($payload['smtpPassword'] ?? '');
$smtp_encryption = (string) ($payload['smtpEncryption'] ?? 'tls');

// Exactly the three transports sendViaSMTP() understands - anything else
// silently degrades to a plaintext AUTH, so reject it rather than store it.
if (!in_array($smtp_encryption, ['tls', 'ssl', 'none'], true)) {
    JSONResponse::fieldError('smtpEncryption', 'Choose one of the three listed transports.') -> send();
}

// Blank leaves the stored address unchanged - not write-only like the
// password, but a blank address would break every subsequent email, so it
// must never silently apply (see MailSettingsForm's docblock).
if ($mail_from_address !== '') {
    if (filter_var($mail_from_address, FILTER_VALIDATE_EMAIL) === false) {
        JSONResponse::fieldError('mailFromAddress', 'That is not a valid email address.') -> send();
    }

    Settings::set(Mailer::FROM_ADDRESS_SETTING, $mail_from_address);
}

// Only what the payload actually carried. Blank is a real answer for these -
// clearing the SMTP host is how an admin goes back to PHP's mail() - so a
// field that simply wasn't sent must not be read as one that was cleared, or a
// form that forgets to send something erases it as the side effect of saving
// something else.
$given = static fn (string $field): bool => array_key_exists($field, $payload);

if ($given('mailFromName')) {
    Settings::set(Mailer::FROM_NAME_SETTING, $mail_from_name);
}

if ($given('smtpHost')) {
    Settings::set(Mailer::SMTP_HOST_SETTING, $smtp_host);
}

if ($given('smtpPort')) {
    Settings::set(Mailer::SMTP_PORT_SETTING, $smtp_port);
}

if ($given('smtpUsername')) {
    Settings::set(Mailer::SMTP_USERNAME_SETTING, $smtp_username);
}

if ($given('smtpEncryption')) {
    Settings::set(Mailer::SMTP_ENCRYPTION_SETTING, $smtp_encryption);
}

// Write-only: a blank field keeps
// the stored password rather than clearing it.
if ($smtp_password !== '') {
    Settings::set(Mailer::SMTP_PASSWORD_SETTING, $smtp_password);
}

JSONResponse::success(['saved' => true]) -> send();
