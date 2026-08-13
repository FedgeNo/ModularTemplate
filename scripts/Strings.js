import { ClientConfig } from '/scripts/ClientConfig.js';

/**
 * The words the client twins say, which are the same words the server says.
 *
 * Fetched once from /locales/{locale}.json - the same file the server reads,
 * not a copy built for the browser. A second hand-kept set of every string
 * would be the easiest drift there is between the two renderers, and they
 * already work hard not to drift.
 *
 * load() is awaited by main.js before any twin renders, so for() can stay
 * synchronous - a renderer that had to await its own words would have to be
 * async all the way up.
 */
export class Strings {
    static #table = {};
    static #locale = 'en';

    static async load() {
        const locale = ClientConfig.get('locale') || 'en';

        try {
            const response = await fetch('/locales/' + locale + '.json');

            Strings.useLocale(await response.json(), locale);
        } catch {
            // No words is not a reason to render nothing: a twin asking for a
            // string it has not got falls back the same way a missing key
            // does, which is to what the caller passes as the English. The
            // locale still stands, so counted phrasings that fell back to their
            // English are at least counted this language's way.
            Strings.useLocale({}, locale);
        }
    }

    /**
     * The words and the language they are, for whatever is not asking a
     * browser - the same name as the server's Strings::useLocale() because it
     * is the same call, the way every twin here shares its object's name.
     *
     * Both together, because they are one fact: a table of Polish with the
     * language still set to English picks singulars for counts Polish has three
     * other forms for, and reads as a bug in the translation rather than in the
     * call that installed it.
     */
    static useLocale(table, locale = 'en') {
        Strings.#table = table || {};
        Strings.#locale = locale || 'en';
    }

    /**
     * What one class says. The caller passes the English it was written with,
     * which is what comes back when this locale has no words for that class -
     * the same fall-back the server does, for the same reason.
     */
    static for(name, english = {}) {
        return Strings.#merge(english, Strings.#table[name] || {});
    }

    /**
     * One of a set of phrasings keyed by CLDR category, chosen by how many of
     * the thing there are - the client's Strings::plural().
     *
     * The category comes from Intl.PluralRules, which is the browser's own copy
     * of the same CLDR data the locale files' @plural closures encode. That rule
     * is a PHP closure and cannot be serialized into the table the browser
     * fetches, and asking the browser is better than shipping a second copy
     * anyway: there is only one place per language for it to be wrong.
     *
     * Unlike the server's, this substitutes {count} - every caller here wants
     * it, and a phrasing that leaves the number out simply has no token to fill.
     */
    static plural(forms, count) {
        const category = new Intl.PluralRules(Strings.#locale).select(count);

        return (forms[category] || forms.other || '').replace('{count}', String(count));
    }

    /**
     * What array_replace_recursive does on the server, because a sentence is a
     * list of named pieces and a locale may have translated one of them and not
     * another. Replacing the entry wholesale would drop the pieces nobody got
     * to yet, so a half-translated sentence would lose its English words here
     * while still reading correctly server-side.
     */
    static #merge(base, over) {
        const merged = { ...base };

        for (const [key, value] of Object.entries(over)) {
            merged[key] = Strings.#isTable(value) && Strings.#isTable(base[key])
                ? Strings.#merge(base[key], value)
                : value;
        }

        return merged;
    }

    static #isTable(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }
}
