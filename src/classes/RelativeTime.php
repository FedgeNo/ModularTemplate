<?php

declare(strict_types=1);

/**
 * A timestamp, written out until the browser can say how long ago it was.
 *
 * What this renders is the absolute date; RelativeTime.js replaces it with "3h
 * ago" on load and once a minute after, since how long ago something was
 * depends on when somebody looks. A reader without JavaScript keeps this.
 */
class RelativeTime extends Time
{
    /** Which shape DateFormat writes the fallback in. */
    public const FULL = 'longWithTime';
    public const DATE_ONLY = 'short';

    public string $createdAt;
    public string $fallbackFormat;

    public function __construct(string $created_at, string $fallback_format = self::FULL)
    {
        parent::__construct();

        $this -> class = 'RelativeTime';
        $this -> createdAt = $created_at;
        $this -> fallbackFormat = $fallback_format;
    }

    public function toDOM(): \DOMElement
    {
        $moment = (int) strtotime($this -> createdAt);

        $this -> datetime = date(DATE_ATOM, $moment);
        $this -> contents[] = $this -> fallbackFormat === self::DATE_ONLY
            ? DateFormat::short($moment)
            : DateFormat::longWithTime($moment);

        return parent::toDOM();
    }
}
