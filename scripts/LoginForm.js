import { ClientConfig } from '/scripts/ClientConfig.js';
import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class LoginForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.LoginForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const data = await Api.post('/api/login', {
                identifier: form.querySelector('[name="identifier"]').value,
                password: form.querySelector('[name="password"]').value,
            }, { form });

            if (!data) {
                Working.stop(submit_button);
                return;
            }

            window.location = ClientConfig.siteURL() + '/';
        });
    }
}

ReadyHandler.add(LoginForm.init);
