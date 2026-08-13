import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class PasswordResetForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.PasswordResetForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const data = await Api.post('/api/reset-password', {
                token: form.querySelector('[name="token"]').value,
                newPassword: form.querySelector('[name="newPassword"]').value,
                confirmPassword: form.querySelector('[name="confirmPassword"]').value,
            }, { form });

            if (!data) {
                Working.stop(submit_button);
                return;
            }

            if (!data.reset) {
                Working.stop(submit_button);
                Toast.show('That\'s already your password - nothing was changed.');
                return;
            }

            const notice = document.createElement('p');
            notice.textContent = 'Your password has been reset. You can now log in.';

            const login_link = document.createElement('a');
            login_link.href = ClientConfig.siteURL() + '/login';
            login_link.textContent = 'Log In';

            form.replaceWith(notice, login_link);
        });
    }
}

ReadyHandler.add(PasswordResetForm.init);
