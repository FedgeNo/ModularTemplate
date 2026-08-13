// MailSettingsForm.js
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class MailSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.MailSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/mail-settings', {
                mailFromAddress: form.querySelector('[name="mailFromAddress"]').value,
                mailFromName: form.querySelector('[name="mailFromName"]').value,
                smtpHost: form.querySelector('[name="smtpHost"]').value,
                smtpPort: form.querySelector('[name="smtpPort"]').value,
                smtpUsername: form.querySelector('[name="smtpUsername"]').value,
                smtpPassword: form.querySelector('[name="smtpPassword"]').value,
                smtpEncryption: form.querySelector('[name="smtpEncryption"]').value,
            }, { form });
            Working.stop(submit_button);
            if (data) Toast.show('Settings saved.');
        });
    }
}

ReadyHandler.add(MailSettingsForm.init);
