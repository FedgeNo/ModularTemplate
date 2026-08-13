import { Api } from '/scripts/Api.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class SignupForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.SignupForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/signup', {
                username: form.querySelector('[name="username"]').value,
                email: form.querySelector('[name="email"]').value,
                displayName: form.querySelector('[name="displayName"]').value,
                password: form.querySelector('[name="password"]').value,
            }, { form });
            if (!data) {
                Working.stop(submit_button);
                return;
            }
            window.location = ClientConfig.siteURL() + (data.verified ? '/' : '/check-inbox');
        });
    }
}

ReadyHandler.add(SignupForm.init);
