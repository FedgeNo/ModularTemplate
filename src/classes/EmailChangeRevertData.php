<?php

declare(strict_types=1);

/**
 * The columns EmailChangeRevert::consume() reads off an EmailChangeReverts
 * row - just data, no rendering.
 */
class EmailChangeRevertData
{
    public ?int $userId = null;
    public ?string $previousEmail = null;
}
