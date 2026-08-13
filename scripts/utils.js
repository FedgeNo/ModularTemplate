import { Cookie } from '/scripts/Cookie.js';

// ----------------------------------------------------------------
// Date parsing
// ----------------------------------------------------------------
export function parse_server_date(dateString) {
    const normalized = dateString.includes('T')
        ? dateString
        : dateString.replace(' ', 'T');
    return new Date(
        /Z|[+-]\d\d:\d\d$/.test(normalized)
            ? normalized
            : normalized + 'Z'
    );
}

// ----------------------------------------------------------------
// CSRF headers
// ----------------------------------------------------------------
export function csrf_headers(extra = {}) {
    const token = Cookie.get('CSRF-TOKEN');
    return Object.assign({ 'X-CSRF-Token': token || '' }, extra);
}

// ----------------------------------------------------------------
// Text
// ----------------------------------------------------------------

/**
 * The same cut as PHP's truncate() in src/functions.php. A post can reach the
 * page from either renderer, so the two have to land on the same character or
 * the writing changes length when the page is reloaded.
 *
 * Counted in code points, which is what mb_substr counts; backing up to the
 * last space is also what keeps the cut out of the middle of an emoji, since
 * anything long enough to truncate has a space to retreat to.
 */
export function truncate(text, length = 50) {
    const characters = Array.from(text);

    if (characters.length <= length) return text;

    let cut = characters.slice(0, length).join('');
    const last_space = cut.lastIndexOf(' ');

    if (last_space !== -1) cut = cut.slice(0, last_space);

    return cut.replace(/\s+$/, '') + '…';
}

// ----------------------------------------------------------------
// DOM helpers
// ----------------------------------------------------------------
export function list_item(child) {
    const item = document.createElement('li');
    item.appendWithSpace(child);
    return item;
}

/**
 * The list inside a container, built over the empty-state notice standing
 * there if this is the first item to arrive. A list with nothing in it isn't
 * rendered at all - only the notice saying so - so there is nothing to append
 * to until something asks for one.
 *
 * Null when the container holds neither, which is how a caller tells that this
 * list isn't on the page at all rather than being empty.
 */
export function list_in(container, classes) {
    if (!container) return null;

    const existing = container.querySelector('.' + classes.split(' ')[0]);

    if (existing) return existing;

    const notice = container.querySelector('.Notice');

    if (!notice) return null;

    const list = document.createElement('ul');
    list.className = classes;
    notice.replaceWith(list);

    return list;
}

/**
 * Keeps the browser chrome's own colour in step with the active theme -
 * read from the live --paper token, so every theme (and Match System) is
 * covered without a second list of colours anywhere.
 */
export function sync_theme_color() {
    const paper = getComputedStyle(document.documentElement).getPropertyValue('--paper').trim();

    if (paper === '') return;

    let meta = document.querySelector('meta[name="theme-color"]');

    if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'theme-color';
        document.head.appendChild(meta);
    }

    meta.content = paper;
}
