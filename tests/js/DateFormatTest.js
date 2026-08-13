import { TestCase } from './TestCase.js';
import { DateFormat } from '../../scripts/DateFormat.js';
import { Strings } from '../../scripts/Strings.js';

/**
 * The client twin of DateFormat.php, asserted against the same strings
 * tests/DateFormatTest.php asserts server-side.
 *
 * A feed holds cards from both renderers at once, so the two have to agree
 * exactly - not merely both be plausible dates. Whichever side drifts, one of
 * the two suites goes red.
 *
 * The locale entries are inlined rather than fetched: this runs headless with
 * no server, and a test that built its expectation from the same file it is
 * checking would pass whatever that file said.
 */
const LOCALES = {
    de: {
        months: { 8: 'August' },
        shortMonths: { 8: 'Aug.' },
        long: '{day}. {month} {year}',
        short: '{day}. {month} {year}',
        time: '{hour}:{minute}',
        dateAndTime: '{date} {time}',
        clock: 24,
    },
    es: {
        months: { 8: 'agosto' },
        shortMonths: { 8: 'ago' },
        long: '{day} de {month} de {year}',
        short: '{day} {month} {year}',
        time: '{hour}:{minute}',
        dateAndTime: '{date} {time}',
        clock: 24,
    },
    fr: {
        months: { 8: 'août' },
        shortMonths: { 8: 'août' },
        long: '{day} {month} {year}',
        short: '{day} {month} {year}',
        time: '{hour}:{minute}',
        dateAndTime: '{date} {time}',
        clock: 24,
    },
};

/** 11 August 2026, 15:04 UTC - the same instant the PHP test uses. */
const MOMENT = new Date(Date.UTC(2026, 7, 11, 15, 4, 0));

function inLocale(locale, work) {
    Strings.useLocale(locale === 'en' ? {} : { DateFormat: LOCALES[locale] }, locale);

    try {
        return work();
    } finally {
        Strings.useLocale({}, 'en');
    }
}

export default {
    suite: 'DateFormat',
    tests: {
        'each language arranges a date its own way'() {
            const expected = {
                en: 'August 11, 2026',
                de: '11. August 2026',
                es: '11 de agosto de 2026',
                fr: '11 août 2026',
            };

            for (const [locale, written] of Object.entries(expected)) {
                TestCase.assertEquals(written, inLocale(locale, () => DateFormat.long(MOMENT)), locale);
            }
        },
        'the short form is the same date with less of it'() {
            const expected = {
                en: 'Aug 11, 2026',
                de: '11. Aug. 2026',
                es: '11 ago 2026',
                fr: '11 août 2026',
            };

            for (const [locale, written] of Object.entries(expected)) {
                TestCase.assertEquals(written, inLocale(locale, () => DateFormat.short(MOMENT)), locale);
            }
        },
        // A meridiem surviving into a 24-hour rendering is the failure here.
        'the clock is the language\'s to decide'() {
            const expected = {
                en: 'August 11, 2026 3:04 PM',
                de: '11. August 2026 15:04',
                es: '11 de agosto de 2026 15:04',
                fr: '11 août 2026 15:04',
            };

            for (const [locale, written] of Object.entries(expected)) {
                TestCase.assertEquals(written, inLocale(locale, () => DateFormat.longWithTime(MOMENT)), locale);
            }
        },
        // Every other date here has a two-digit day, which a renderer that
        // zero-padded would render correctly by accident.
        'a single-digit day is not padded'() {
            const moment = new Date(Date.UTC(2026, 7, 5, 9, 4, 0));

            TestCase.assertEquals('August 5, 2026', inLocale('en', () => DateFormat.long(moment)));
            TestCase.assertEquals('5. August 2026', inLocale('de', () => DateFormat.long(moment)));
            TestCase.assertEquals('5 de agosto de 2026', inLocale('es', () => DateFormat.long(moment)));

            // The hour is not padded on a twelve-hour clock either.
            TestCase.assertEquals('9:04 AM', inLocale('en', () => DateFormat.time(moment)));
        },
        'midday and midnight read as twelve'() {
            const midnight = new Date(Date.UTC(2026, 6, 30, 0, 30, 0));
            const midday = new Date(Date.UTC(2026, 6, 30, 12, 5, 0));

            TestCase.assertEquals('12:30 AM', inLocale('en', () => DateFormat.time(midnight)));
            TestCase.assertEquals('12:05 PM', inLocale('en', () => DateFormat.time(midday)));

            // The same two on a twenty-four hour clock, which zero-pads instead.
            TestCase.assertEquals('00:30', inLocale('de', () => DateFormat.time(midnight)));
            TestCase.assertEquals('12:05', inLocale('de', () => DateFormat.time(midday)));
        },
        // The English the module carries is what a reader gets when the table
        // has not loaded, so it has to be the English the locale file says.
        'the built-in English is a whole date, not a half-built pattern'() {
            const written = inLocale('en', () => DateFormat.longWithTime(MOMENT));

            TestCase.assertTrue(!written.includes('{'), 'no token survived: ' + written);
            TestCase.assertTrue(!written.includes('undefined'), 'nothing came back missing: ' + written);
        },
    }
};
