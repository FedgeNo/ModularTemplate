<?php

declare(strict_types=1);

/**
 * How to build each converted piece of page furniture - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 */

return [
    MainNavigation::class => static fn (): HTMLObject => new MainNavigation(),
    LogoutForm::class => static fn (): HTMLObject => new LogoutForm(),
    ThemeSelector::class => static fn (): HTMLObject => new ThemeSelector(),
];
