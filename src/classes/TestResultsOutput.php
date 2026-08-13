<?php

declare(strict_types=1);

/**
 * A suite's console output, kept as it was printed - a runner's alignment is
 * how it is read.
 */
class TestResultsOutput extends Preformatted
{
    public ?string $class = 'TestResultsOutput';

    public function __construct(string $output)
    {
        parent::__construct($output !== '' ? $output : '(no output)');
    }
}
