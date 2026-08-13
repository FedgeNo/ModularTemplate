<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

// Site-wide settings are admin-only (the primary admin, userId 1) - the same
// gate as every other admin-only action.
if (Auth::id() !== 1) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => (string) (Strings::for('PageTitle')['adminSettings'] ?? '')]);

$page -> addContent(new SettingsSection('Tests', new TestSuitePanel()));

$page -> addContent(new SettingsSection('Mail', new MailSettingsForm()));

$page -> addContent(new SettingsSection('About', new AboutSettingsForm()));

$page -> addContent(new SettingsSection('Terms of Service', new TermsSettingsForm()));

$page -> addContent(new SettingsSection('Privacy Policy', new PrivacySettingsForm()));

$page -> send();
