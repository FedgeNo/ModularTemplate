import { Api } from '/scripts/Api.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class PasswordResetRequestForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.PasswordResetRequestForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const data = await Api.post('/api/forgot-password', {
                email: form.querySelector('[name="email"]').value,
            });

            Working.stop(submit_button);

            if (!data) return;

            const notice = document.createElement('p');
            notice.textContent = 'If that email address is on file, a password reset link has been sent. If you don\'t see it, check your junk/spam folder.';
            form.replaceWith(notice);
        });
    }
}

ReadyHandler.add(PasswordResetRequestForm.init);
