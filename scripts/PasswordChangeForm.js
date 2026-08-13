import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

/**
 * Changing your own password.
 *
 * Api is handed the form, so a refusal that names the boxes it is about -
 * which of the three was wrong, and all of them at once - is written under
 * those boxes instead of thrown at the corner of the screen.
 */
export class PasswordChangeForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.PasswordChangeForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const data = await Api.post('/api/change-password', {
                    currentPassword: form.querySelector('[name="currentPassword"]').value,
                    newPassword: form.querySelector('[name="newPassword"]').value,
                    confirmPassword: form.querySelector('[name="confirmPassword"]').value,
                }, { form });

                if (!data) return;

                form.reset();
                Toast.show('Password changed!');
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(PasswordChangeForm.init);
