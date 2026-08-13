import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class SiteInfoSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.SiteInfoSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const field = form.querySelector('textarea');
            const field_name = field.name;
            const path = '/api/' + field_name.replace(/Text$/, '') + '-settings';
            const data = await Api.post(path, { [field_name]: field.value });
            Working.stop(submit_button);
            if (data) Toast.show('Settings saved.');
        });
    }
}

ReadyHandler.add(SiteInfoSettingsForm.init);

