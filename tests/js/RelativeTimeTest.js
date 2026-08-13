import { TestCase } from './TestCase.js';
import { RelativeTime } from '../../scripts/RelativeTime.js';
import { Strings } from '../../scripts/Strings.js';

/** A moment the given number of seconds in the past, as the server writes them. */
function ago(seconds) {
    return new Date(Date.now() - (seconds * 1000)).toISOString();
}

export default {
    suite: 'RelativeTime',
    tests: {
        'format() returns a string'() {
            TestCase.assertTrue(typeof RelativeTime.format('2025-01-01T00:00:00Z') === 'string');
        },
        'format() returns "just now" or similar for recent times'() {
            const result = RelativeTime.format(new Date().toISOString());
            TestCase.assertTrue(typeof result === 'string' && result.length > 0);
        },
        // Every one of these is built fresh on each refresh, so a locale loaded
        // after the module was imported still has to reach them - and the
        // "ago" marker leads the phrase in most languages, which is why they
        // are whole phrasings rather than a unit the code appends to.
        'each unit says what the locale says, not what English does'() {
            Strings.useLocale({
                RelativeTime: {
                    justNow: 'à l\'instant',
                    minutes: { one: 'il y a {count} min', other: 'il y a {count} min' },
                    hours: { one: 'il y a {count} h', other: 'il y a {count} h' },
                    days: { one: 'il y a {count} j', other: 'il y a {count} j' },
                },
            });

            try {
                TestCase.assertEquals('à l\'instant', RelativeTime.format(ago(5)));
                TestCase.assertEquals('il y a 5 min', RelativeTime.format(ago(5 * 60)));
                TestCase.assertEquals('il y a 3 h', RelativeTime.format(ago(3 * 3600)));
                TestCase.assertEquals('il y a 2 j', RelativeTime.format(ago(2 * 86400)));
            } finally {
                Strings.useLocale({}, 'en');
            }
        },
        'a locale that says nothing about time still reads in English'() {
            Strings.useLocale({}, 'en');

            TestCase.assertEquals('just now', RelativeTime.format(ago(5)));
            TestCase.assertEquals('5m ago', RelativeTime.format(ago(5 * 60)));
        },
        // Written for a language whose plural rule is not English's: Polish
        // picks "few" for 2-4 and "many" for 5 and up, and the phrase differs.
        'a count picks the form its own language asks for'() {
            Strings.useLocale({
                RelativeTime: {
                    hours: { one: '{count} godzinę temu', few: '{count} godziny temu', many: '{count} godzin temu', other: '{count} godziny temu' },
                },
            }, 'pl');

            try {
                TestCase.assertEquals('1 godzinę temu', RelativeTime.format(ago(3600)));
                TestCase.assertEquals('3 godziny temu', RelativeTime.format(ago(3 * 3600)));
                TestCase.assertEquals('7 godzin temu', RelativeTime.format(ago(7 * 3600)));
            } finally {
                Strings.useLocale({}, 'en');
            }
        },
        // These mirror DateFormat::long() and ::longWithTime(), which the server
        // renders in UTC. Formatting in the viewer's timezone instead would have
        // the same moment read as a different day either side of midnight,
        // depending only on which side built the element.
        'date() matches the server\'s full-date format, in UTC'() {
            TestCase.assertEquals('July 30, 2026', RelativeTime.date('2026-07-30 15:04:00'));
        },
        'date() does not drift across midnight in the viewer\'s timezone'() {
            TestCase.assertEquals('July 30, 2026', RelativeTime.date('2026-07-30 00:30:00'));
            TestCase.assertEquals('July 30, 2026', RelativeTime.date('2026-07-30 23:30:00'));
        },
        'dateAndTime() matches the server\'s date-and-time format, in UTC'() {
            TestCase.assertEquals('July 30, 2026 3:04 PM', RelativeTime.dateAndTime('2026-07-30 15:04:00'));
        },
        /**
         * A date is arranged differently per language, and the month is a word
         * the locale owns - so a locale that says nothing about dates must not
         * leave a half-built pattern, and one that does must be obeyed rather
         * than have English's order imposed on it.
         */
        'a date is written the way its language writes one'() {
            Strings.useLocale({
                DateFormat: {
                    months: { 7: 'juillet' },
                    shortMonths: { 7: 'juil.' },
                    long: '{day} {month} {year}',
                    short: '{day} {month} {year}',
                    time: '{hour}:{minute}',
                    dateAndTime: '{date} {time}',
                    clock: 24,
                },
            }, 'fr');

            try {
                TestCase.assertEquals('30 juillet 2026', RelativeTime.date('2026-07-30 15:04:00'));
                TestCase.assertEquals(
                    '30 juillet 2026 15:04',
                    RelativeTime.dateAndTime('2026-07-30 15:04:00'),
                    'a 24-hour clock leaves no meridiem behind'
                );
            } finally {
                Strings.useLocale({}, 'en');
            }
        },
        'a midnight hour reads as 12 on a twelve-hour clock'() {
            TestCase.assertEquals('July 30, 2026 12:30 AM', RelativeTime.dateAndTime('2026-07-30 00:30:00'));
            TestCase.assertEquals('July 30, 2026 12:05 PM', RelativeTime.dateAndTime('2026-07-30 12:05:00'));
        },
    }
};
