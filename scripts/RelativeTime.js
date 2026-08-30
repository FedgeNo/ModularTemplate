import { ClientConfig } from '/scripts/ClientConfig.js';
import { DateFormat } from '/scripts/DateFormat.js';
import { parse_server_date } from '/scripts/utils.js';
import { Strings } from '/scripts/Strings.js';
import { HTMLObject } from '/scripts/HTMLObjects.js';

/**
 * Single source of truth for relative‑time display and periodic refresh.
 *
 *  - RelativeTime.format(dateString)   → human‑readable string
 *  - RelativeTime.refresh(root)        → immediately update .RelativeTime elements
 *  - RelativeTime.init() / stop()      → manage the 60‑second refresh cycle
 *
 * Every word of it is said here rather than by RelativeTime.php, which renders
 * an absolute date and leaves it at that: what a timestamp reads as depends on
 * how long ago it was at the moment somebody looks, which is not a thing a page
 * rendered once can know. init() is registered by main.js rather than by this
 * module, so that registration happens after Strings.load() has resolved and
 * the first render already has the language's own words to say.
 */
export class RelativeTime extends HTMLObject {
    static tagName = 'time';
    static className = 'RelativeTime';
    static properties = { dateString: null };

    constructor(dateString = null) {
        super({ dateString });
    }

    toDOM() {
        this.attributes.datetime = parse_server_date(this.dateString)
            .toISOString()
            .replace(/\.\d{3}Z$/, '+00:00');
        this.addContent(RelativeTime.dateAndTime(this.dateString));

        return super.toDOM();
    }
    // Offset between server time and local time, computed once at module load.
    static #serverTimeOffset =
        (typeof ClientConfig.get('serverTime') === 'number'
            ? ClientConfig.get('serverTime')
            : 0) - Date.now();

    static #intervalId = null;
    static #running = false;

    // ----------------------------------------------------------------
    // Formatting
    // ----------------------------------------------------------------

    /**
     * The words are read here rather than held, because this is called for
     * every timestamp on the page and again every minute: the count decides
     * which phrasing of the four applies, so there is nothing to resolve once
     * and keep. Reading late is also what makes the locale reliable - main.js
     * awaits Strings.load() before the first refresh, and anything cached at
     * module scope would have been built before that finished.
     */
    static format(dateString) {
        const target = parse_server_date(dateString);
        const diffSeconds = Math.round(
            (RelativeTime.#correctedNow() - target.getTime()) / 1000
        );

        const words = Strings.for('RelativeTime', {
            justNow: 'just now',
            minutes: { one: '{count}m ago', other: '{count}m ago' },
            hours: { one: '{count}h ago', other: '{count}h ago' },
            days: { one: '{count}d ago', other: '{count}d ago' },
        });

        if (diffSeconds < 60) return words.justNow;

        const diffMinutes = Math.round(diffSeconds / 60);
        if (diffMinutes < 60) return Strings.plural(words.minutes, diffMinutes);

        const diffHours = Math.round(diffMinutes / 60);
        if (diffHours < 24) return Strings.plural(words.hours, diffHours);

        const diffDays = Math.round(diffHours / 24);
        if (diffDays < 7) return Strings.plural(words.days, diffDays);

        // Past a week there is nothing to phrase, only a date.
        return DateFormat.short(target);
    }

    /** A full date, the same string DateFormat::long() builds server-side. */
    static date(dateString) {
        return DateFormat.long(parse_server_date(dateString));
    }

    /** That date plus the time of day, matching DateFormat::longWithTime(). */
    static dateAndTime(dateString) {
        return DateFormat.longWithTime(parse_server_date(dateString));
    }

    // ----------------------------------------------------------------
    // DOM refresh
    // ----------------------------------------------------------------

    /**
     * Update every .RelativeTime element inside `root` (defaults to document).
     * Safe to call before start().
     */
    static refresh(root = document) {
        root.querySelectorAll('.RelativeTime').forEach((el) => {
            if (!el.hasAttribute('datetime')) return;
            el.textContent = RelativeTime.format(el.getAttribute('datetime'));
        });
    }

    // ----------------------------------------------------------------
    // Lifecycle (start / stop)
    // ----------------------------------------------------------------

    static init() {
        if (RelativeTime.#running) return;
        RelativeTime.#running = true;

        RelativeTime.refresh();   // initial render
        RelativeTime.#intervalId = setInterval(
            () => RelativeTime.refresh(),
            60000
        );
    }

    static stop() {
        if (RelativeTime.#intervalId !== null) {
            clearInterval(RelativeTime.#intervalId);
            RelativeTime.#intervalId = null;
        }
        RelativeTime.#running = false;
    }

    static #correctedNow() {
        return Date.now() + RelativeTime.#serverTimeOffset;
    }
}
