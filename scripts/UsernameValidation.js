// UsernameValidation.js
import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class UsernameValidation {
    // The pending availability-check timer per input - the handler is delegated
    // on document, and a timer id is this module's own bookkeeping.
    static #debounceIds = new WeakMap();

    static init() {
        const sanitize = (input) => {
            input.value = input.value.toLowerCase().replace(/[^a-z0-9_]/g, '').slice(0, 32);
        };

        document.addEventListener('input', (event) => {
            const input = event.target.closest('.SignupForm [name="username"]');
            if (!input) return;
            sanitize(input);
            UsernameValidation.#checkAvailability(input);
        });

        document.addEventListener('change', (event) => {
            const input = event.target.closest('.SignupForm [name="username"]');
            if (!input) return;
            sanitize(input);
        });
    }

    static #checkAvailability(input) {
        const status = input.closest('.SignupForm').querySelector('.UsernameAvailability');
        if (!status) return;
        clearTimeout(UsernameValidation.#debounceIds.get(input));
        const requested = input.value;
        if (requested === '') {
            status.textContent = '';
            status.classList.remove('Error', 'muted');
            return;
        }
        const debounce_id = setTimeout(async () => {
            input.availabilityAbortController?.abort();
            const controller = new AbortController();
            input.availabilityAbortController = controller;
            // Quiet: this asks again on every keystroke and cancels the one
            // before it, so a failure just leaves the hint blank.
            const data = await Api.post('/api/username-available', { username: requested }, {
                signal: controller.signal,
                quiet: true,
            });

            if (!data) return;
            if (input.value !== requested) return;

            status.classList.toggle('Error', !data.available);
            status.classList.toggle('muted', data.available);
            status.textContent = data.available
                ? `${data.username} is available.`
                : `${data.username} is already taken.`;
        }, 300);
        UsernameValidation.#debounceIds.set(input, debounce_id);
    }
}

ReadyHandler.add(UsernameValidation.init);
