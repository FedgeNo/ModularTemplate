import { Cookie } from '/scripts/Cookie.js';

export class ClientConfig {
    /** Parsed once - the cookie doesn't change within a page. */
    static #cached = null;

    /** @returns {string|null} */
    static _getCookie(name) {
        return Cookie.get(name);
    }

    /**
     * Return the full configuration object.
     * @returns {{
     *   currentUserId: number|null,
     *   currentUserUsername: string|null,
     *   currentUserCanModerate: boolean,
     *   siteURL: string,
     *   locale: string,
     *   serverTime: number
     * }}
     */
    static all() {
        if (ClientConfig.#cached !== null) {
            return ClientConfig.#cached;
        }

        const raw = this._getCookie('APP-CONFIG');

        if (!raw) {
            return ClientConfig.#defaults();
        }

        try {
            // The defaults sit underneath rather than beside: a page whose
            // cookie was written before a value existed still answers for it,
            // which is otherwise a key that reads undefined until the next
            // navigation rewrites the cookie.
            ClientConfig.#cached = { ...ClientConfig.#defaults(), ...JSON.parse(raw) };
        } catch (e) {
            console.error('Invalid APP-CONFIG cookie:', e);

            return ClientConfig.#defaults();
        }

        return ClientConfig.#cached;
    }

    /**
     * What a page says about itself when its cookie cannot: one that was saved,
     * one served before a value was added, one whose cookie will not parse.
     */
    static #defaults() {
        return {
            currentUserId: null,
            currentUserUsername: null,
            currentUserCanModerate: false,
            siteURL: window.location.origin,
            locale: 'en',
            serverTime: Date.now(),
        };
    }

    /**
     * Get a single config value by key.
     * @param {string} key
     * @returns {*}
     */
    static get(key) {
        return this.all()[key];
    }

    /**
     * Convenience: the base URL of the site, e.g. 'https://example.com'.
     * @returns {string}
     */
    static siteURL() {
        return this.get('siteURL');
    }
}
