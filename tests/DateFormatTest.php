<?php

declare(strict_types=1);

/**
 * A date, written the way each language writes one.
 *
 * The strings here are also asserted by tests/js/DateFormatTest.js against the
 * client twin. Both sides read one locale entry and must produce one string:
 * a page can hold dates from both renderers at once, and a date that differed
 * between them would be visible in a single column.
 *
 * The template ships English only, so the other-language cases inject a
 * throwaway locale table instead of reading a second file.
 */
class DateFormatTest extends TestCase
{
    /** 11 August 2026, 15:04 UTC - a date whose day, month and hour all differ
     *  in shape between the languages here. */
    private static function moment(): int
    {
        return (int) gmmktime(15, 4, 0, 8, 11, 2026);
    }

    private static function inLocale(string $locale, callable $work): string
    {
        Strings::useLocale($locale);

        try {
            return $work();
        } finally {
            Strings::useLocale(null);
        }
    }

    /** A German-shaped locale: day first, dotted, on a twenty-four hour clock. */
    private static function inInjectedGermanShape(callable $work): string
    {
        $tables = new \ReflectionProperty(Strings::class, 'tables');
        $tables -> setAccessible(true);
        $locale = new \ReflectionProperty(Strings::class, 'locale');
        $locale -> setAccessible(true);

        $tables -> setValue(null, ['xx' => ['DateFormat' => [
            'months' => [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
            'shortMonths' => [1 => 'Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.'],
            'long' => '{day}. {month} {year}',
            'short' => '{day}. {month} {year}',
            'time' => '{hour}:{minute}',
            'dateAndTime' => '{date} {time}',
            'clock' => 24,
        ]]]);
        $locale -> setValue(null, 'xx');

        try {
            return $work();
        } finally {
            $tables -> setValue(null, []);
            $locale -> setValue(null, null);
        }
    }

    public function testEachLanguageArrangesADateItsOwnWay(): void
    {
        $this -> assertSame('August 11, 2026', self::inLocale('en', static fn (): string => DateFormat::long(self::moment())));
        $this -> assertSame('11. August 2026', self::inInjectedGermanShape(static fn (): string => DateFormat::long(self::moment())));
    }

    public function testTheShortFormIsTheSameDateWithLessOfIt(): void
    {
        $this -> assertSame('Aug 11, 2026', self::inLocale('en', static fn (): string => DateFormat::short(self::moment())));
        $this -> assertSame('11. Aug. 2026', self::inInjectedGermanShape(static fn (): string => DateFormat::short(self::moment())));
    }

    /**
     * English keeps a twelve-hour clock and says which half of the day it is;
     * a twenty-four hour language has no word for that, so its phrasing leaves
     * {meridiem} out entirely rather than filling it with something. A
     * meridiem surviving into a 24-hour rendering is the failure this catches.
     */
    public function testTheClockIsTheLanguagesToDecide(): void
    {
        $this -> assertSame('August 11, 2026 3:04 PM', self::inLocale('en', static fn (): string => DateFormat::longWithTime(self::moment())));
        $this -> assertSame('11. August 2026 15:04', self::inInjectedGermanShape(static fn (): string => DateFormat::longWithTime(self::moment())));
    }

    /**
     * A single-digit day is written as one digit. Every other date here has a
     * two-digit day, which a renderer that zero-padded would render correctly
     * by accident.
     */
    public function testASingleDigitDayIsNotPadded(): void
    {
        $moment = (int) gmmktime(9, 4, 0, 8, 5, 2026);

        $this -> assertSame('August 5, 2026', self::inLocale('en', static fn (): string => DateFormat::long($moment)));
        $this -> assertSame('5. August 2026', self::inInjectedGermanShape(static fn (): string => DateFormat::long($moment)));

        // The hour is not padded on a twelve-hour clock either.
        $this -> assertSame('9:04 AM', self::inLocale('en', static fn (): string => DateFormat::time($moment)));
    }

    /** Noon and midnight are where a twelve-hour clock goes wrong. */
    public function testMiddayAndMidnightReadAsTwelve(): void
    {
        $midnight = gmmktime(0, 30, 0, 7, 30, 2026);
        $midday = gmmktime(12, 5, 0, 7, 30, 2026);

        $this -> assertSame('12:30 AM', self::inLocale('en', static fn (): string => DateFormat::time((int) $midnight)));
        $this -> assertSame('12:05 PM', self::inLocale('en', static fn (): string => DateFormat::time((int) $midday)));

        // The same two on a twenty-four hour clock, which zero-pads instead.
        $this -> assertSame('00:30', self::inInjectedGermanShape(static fn (): string => DateFormat::time((int) $midnight)));
        $this -> assertSame('12:05', self::inInjectedGermanShape(static fn (): string => DateFormat::time((int) $midday)));
    }

    /** Every month is named, in every language - a gap renders a date with a hole in it. */
    public function testNoMonthGoesUnnamed(): void
    {
        foreach (Strings::available() as $locale) {
            for ($month = 1; $month <= 12; $month++) {
                $moment = (int) gmmktime(12, 0, 0, $month, 15, 2026);

                foreach (['long', 'short'] as $shape) {
                    $written = self::inLocale($locale, static fn (): string => DateFormat::$shape($moment));

                    $this -> assertTrue(
                        !str_contains($written, '{') && trim($written) !== '',
                        $locale . ' month ' . $month . ' (' . $shape . ') came out as "' . $written . '"'
                    );
                }
            }
        }
    }
}
