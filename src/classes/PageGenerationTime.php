<?php

declare(strict_types=1);

/**
 * What the page cost to build, at the foot of it, for the admin alone.
 *
 * Two numbers, because they answer different questions and each is misleading
 * without the other: how long the whole request took to assemble, and how many
 * statements went to the database doing it. A page that got slower is one or
 * the other, and the query count is the one that creeps - a listing grows a
 * query per row and nothing says so until somebody counts.
 *
 * Timed from the first line of init.php, so it covers the whole of building
 * the page rather than the tail of it. Rendered last, which is as close to the
 * true total as something that has to sit inside the page can get: what it
 * leaves out is only its own markup and the sending.
 */
class PageGenerationTime extends Paragraph
{
    public ?string $class = 'PageGenerationTime';

    /** Whether this reader is the one it is for. */
    public static function isForViewer(): bool
    {
        return Auth::id() === 1 && defined('APP_REQUEST_STARTED_AT');
    }

    public function toDOM(): \DOMElement
    {
        $milliseconds = (microtime(true) - APP_REQUEST_STARTED_AT) * 1000;
        $queries = DB::queryCount();

        $this -> contents[] = sprintf(
            'Built in %.1f ms, %d %s.',
            $milliseconds,
            $queries,
            $queries === 1 ? 'query' : 'queries'
        );

        return parent::toDOM();
    }
}
