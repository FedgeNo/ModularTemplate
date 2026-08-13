<?php

declare(strict_types=1);

/**
 * Converted account/auth form classes - see tests/NoEnglishInClassesTest.php
 * for what this list is for.
 *
 * @return array<string, callable(): HTMLObject>
 */
return [
    LoginForm::class => static fn (): HTMLObject => new LoginForm(),
    SignupForm::class => static fn (): HTMLObject => new SignupForm(),
    PasswordChangeForm::class => static fn (): HTMLObject => new PasswordChangeForm(),
    EmailChangeForm::class => static fn (): HTMLObject => new EmailChangeForm(),
    VerificationNotice::class => static fn (): HTMLObject => new VerificationNotice(),
    PasswordResetForm::class => static fn (): HTMLObject => new PasswordResetForm('example-token'),
    PasswordResetRequestForm::class => static fn (): HTMLObject => new PasswordResetRequestForm(),
    EmailRevertForm::class => static fn (): HTMLObject => new EmailRevertForm('example-token'),
    EmailVerifyForm::class => static fn (): HTMLObject => new EmailVerifyForm('example-token'),
    SetupForm::class => static fn (): HTMLObject => new SetupForm(),
];
