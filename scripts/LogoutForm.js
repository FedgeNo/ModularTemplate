import { ClientConfig } from '/scripts/ClientConfig.js';
import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class LogoutForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.LogoutForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            // Left working on the way out: the page is about to be replaced,
            // and a button springing back first reads as a press that failed.
            if (await Api.post('/api/logout', new FormData(form)) === null) {
                Working.stop(submit_button);

                return;
            }

            window.location = ClientConfig.siteURL() + '/';
        });
    }
}

ReadyHandler.add(LogoutForm.init);
