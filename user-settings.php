<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => (string) (Strings::for('PageTitle')['userSettings'] ?? '')]);

$page -> addContent(new SettingsSection('Change Password', new PasswordChangeForm()));

$page -> addContent(new SettingsSection('Change Email', new EmailChangeForm()));

$page -> addContent(new SettingsSection((string) (Strings::for('LanguageSelector')['legend'] ?? ''), new LanguageSelector()));

$page -> addContent(new SettingsSection('Theme', new ThemeSelector()));

$page -> send();
