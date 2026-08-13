<?php

declare(strict_types=1);

/**
 * A date, written the way the reader's language writes one.
 *
 * PHP's date() only ever says January, and there is no ext-intl here to ask
 * instead - so the month names and the order they go in come from the locale,
 * the same place every other word does. A language decides three separate
 * things and this keeps them separate: what the months are called, how a date
 * is arranged, and whether a clock has twelve hours or twenty-four.
 *
 * Everything is rendered in UTC, because the server has no way to know the
 * viewer's timezone and a date that read differently either side of midnight
 * depending on which renderer built it would be worse than one that is plainly
 * UTC everywhere. The client twin (scripts/DateFormat.js) does the same.
 *
 * Not an HTMLObject - it renders nothing, it hands a string to whatever does.
 */
class DateFormat
{
    /** "August 11, 2026" - the full date. */
    public static function long(int $timestamp): string
    {
        return self::date($timestamp, 'long', 'months');
    }

    /** "Aug 11, 2026" - the same date where there is less room for it. */
    public static function short(int $timestamp): string
    {
        return self::date($timestamp, 'short', 'shortMonths');
    }

    /** "August 11, 2026 3:04 PM". */
    public static function longWithTime(int $timestamp): string
    {
        return str_replace(
            ['{date}', '{time}'],
            [self::long($timestamp), self::time($timestamp)],
            (string) (self::words()['dateAndTime'] ?? '')
        );
    }

    /** "3:04 PM", or "15:04" where the language keeps a twenty-four hour clock. */
    public static function time(int $timestamp): string
    {
        $words = self::words();
        $twelve = (int) ($words['clock'] ?? 12) === 12;
        $hour = (int) gmdate('G', $timestamp);

        return str_replace(
            ['{hour}', '{minute}', '{meridiem}'],
            [
                $twelve ? (string) (($hour % 12) ?: 12) : gmdate('H', $timestamp),
                gmdate('i', $timestamp),
                (string) ($words[$hour < 12 ? 'am' : 'pm'] ?? ''),
            ],
            (string) ($words['time'] ?? '')
        );
    }

    private static function date(int $timestamp, string $pattern, string $month_list): string
    {
        $words = self::words();

        return str_replace(
            ['{month}', '{day}', '{year}'],
            [
                (string) ($words[$month_list][(int) gmdate('n', $timestamp)] ?? ''),
                gmdate('j', $timestamp),
                gmdate('Y', $timestamp),
            ],
            (string) ($words[$pattern] ?? '')
        );
    }

    /** @return array<string, mixed> */
    private static function words(): array
    {
        return Strings::for(self::class);
    }
}
