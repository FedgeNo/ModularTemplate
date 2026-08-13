<?php

declare(strict_types=1);

/**
 * The column PasswordReset::verify() reads off a PasswordResets row.
 */
class PasswordResetData
{
    public ?int $userId = null;
}
