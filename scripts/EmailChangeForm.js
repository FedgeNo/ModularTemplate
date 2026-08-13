import { ClientConfig } from '/scripts/ClientConfig.js';
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

/**
 * Changing the address the site writes to, which asks for the password too.
 *
 * Api is handed the form, so "that address is already in use" lands on the
 * address box and a wrong password lands on the password box - both at once
 * where both are wrong.
 */
export class EmailChangeForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.EmailChangeForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const data = await Api.post('/api/change-email', {
                    newEmail: form.querySelector('[name="newEmail"]').value,
                    currentPassword: form.querySelector('[name="currentPassword"]').value,
                }, { form });

                if (!data) return;

                if (!data.changed) {
                    Toast.show('That is already your email address.');
                    return;
                }

                window.location = ClientConfig.siteURL() + '/check-inbox';
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(EmailChangeForm.init);
