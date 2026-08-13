/**
 * Static helper for reading a single cookie.
 *
 *  Cookie.get('CSRF-TOKEN') → token string or null
 */
export class Cookie {
    /**
     * Read a cookie value by name. Returns null if the cookie is not set.
     * @param {string} name
     * @returns {string|null}
     */
    static get(name) {
        const match = document.cookie.match(
            new RegExp(
                '(?:^|; )' +
                    name.replace(/([.$?*|{}()[\]\\\/+^])/g, '\\$1') +
                    '=([^;]*)'
            )
        );
        return match ? decodeURIComponent(match[1]) : null;
    }
}
