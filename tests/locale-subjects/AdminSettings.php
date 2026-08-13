<?php

declare(strict_types=1);

/**
 * Converted Admin Settings classes - see tests/NoEnglishInClassesTest.php for
 * what this list is for. Every form here reads its current values through
 * Settings::get(), which degrades to the shipped defaults when no database is
 * reachable, so all of them are constructible from this plain suite.
 *
 * @return array<string, callable(): HTMLObject>
 */
return [
    MailSettingsForm::class => static fn (): HTMLObject => new MailSettingsForm(),
    AboutSettingsForm::class => static fn (): HTMLObject => new AboutSettingsForm(),
    TermsSettingsForm::class => static fn (): HTMLObject => new TermsSettingsForm(),
    PrivacySettingsForm::class => static fn (): HTMLObject => new PrivacySettingsForm(),
    TestSuitePanel::class => static fn (): HTMLObject => new TestSuitePanel(),
];
