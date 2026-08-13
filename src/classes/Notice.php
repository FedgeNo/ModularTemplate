<?php

declare(strict_types=1);

/**
 * A muted, informational line of text - empty states ("No reports."),
 * can't-do-that notices, and the like.
 */
class Notice extends Paragraph
{
    public ?string $class = 'Notice';
    public array $mixins = ['muted'];
}
