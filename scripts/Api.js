import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { csrf_headers } from '/scripts/utils.js';
import { FormErrors } from '/scripts/FormErrors.js';

/**
 * The one way this site talks to its own server.
 *
 * Nothing calls fetch directly. Everything that did was reimplementing the
 * same four things - the CSRF header, the ok check, the unwrapping of
 * data.response, and staying quiet when a request is deliberately aborted -
 * and each copy was a chance to get one of them subtly wrong.
 *
 * Two ways in. post() is what almost everything wants: the answer, or null,
 * with a toast raised on the caller's behalf. request() is for the few that
 * need the status code itself - a scroller telling a rate limit apart from a
 * real failure, a diagnostic panel whose whole job is reporting which refusal
 * came back - and it never toasts, because a caller reading the status is
 * handling the outcome itself.
 */
export class Api {
    /**
     * A POST, reported in full.
     *
     * Never throws and never toasts. An aborted request is not a failure worth
     * reporting - a typeahead cancels one on every keystroke - so it comes
     * back marked as aborted and otherwise empty.
     *
     * @param {string} path – API path, e.g. '/api/create-post'
     * @param {*} [payload] – JSON-serialisable body, or FormData
     * @param {{ signal?: AbortSignal, keepalive?: boolean }} [options] –
     *   `keepalive` keeps the request alive past the page that started it,
     *   which is the only way a hangup sent while somebody closes the tab
     *   actually leaves the browser
     * @returns {Promise<{ ok: boolean, status: number, data: ?object, error: ?string, aborted: boolean }>}
     */
    static async request(path, payload, { signal, keepalive } = {}) {
        let response;

        try {
            // FormData goes as it is. Encoding it as JSON would throw the
            // files away, and setting a Content-Type would override the
            // multipart boundary the browser generates - which is the one
            // header that must be left alone.
            const is_form_data = payload instanceof FormData;

            response = await fetch(ClientConfig.siteURL() + path, {
                method: 'POST',
                headers: csrf_headers(
                    payload !== undefined && !is_form_data
                        ? { 'Content-Type': 'application/json' }
                        : undefined
                ),
                body: payload === undefined ? undefined : (is_form_data ? payload : JSON.stringify(payload)),
                signal,
                keepalive,
            });
        } catch (error) {
            const aborted = error.name === 'AbortError';

            return {
                ok: false,
                status: 0,
                data: null,
                error: aborted ? null : 'Network error. Please check your connection and try again.',
                aborted,
            };
        }

        let body = null;

        try {
            body = await response.json();
        } catch (_) {
            // Not JSON, which for this server means something went wrong
            // upstream of the endpoint - reported below as a plain failure.
        }

        return {
            ok: response.ok && body !== null,
            status: response.status,
            data: body,
            error: body?.error ?? (response.ok ? null : 'Something went wrong. Please try again.'),
            aborted: false,
        };
    }

    /**
     * A POST, answered.
     *
     * @param {string} path – API path, e.g. '/api/create-post'
     * @param {*} [payload] – JSON-serialisable body, or FormData
     * @param {{ signal?: AbortSignal, form?: Element, quiet?: boolean, keepalive?: boolean }} [options] –
     *   `form` writes a refusal that names its fields under those fields
     *   instead of throwing it at the corner of the screen; `quiet` says
     *   nothing at all, for a call whose failure is not the reader's business
     *   - a background token refresh, a seen-marker, a typeahead
     * @returns {Promise<object|null>} – the response, or null on any failure
     */
    static async post(path, payload, { signal, form, quiet, keepalive } = {}) {
        const result = await Api.request(path, payload, { signal, keepalive });

        if (result.ok) {
            // Whatever the last attempt complained about is answered.
            if (form) FormErrors.clear(form);

            return result.data.response;
        }

        // An abort is somebody changing their mind, not a fault.
        if (result.aborted || quiet) {
            return null;
        }

        // A refusal that names the inputs it is about belongs on those inputs.
        // The toast is the fallback for everything else - and for a named field
        // this form does not actually have, which would otherwise be a
        // complaint nobody could see.
        if (form && result.data?.fields && FormErrors.show(form, result.data.fields)) {
            return null;
        }

        Toast.show(result.error || 'Something went wrong. Please try again.');

        return null;
    }
}
