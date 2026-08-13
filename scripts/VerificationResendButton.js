import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class VerificationResendButton {
    static init() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.VerificationResendButton');
            if (!button) return;

            Working.start(button);
            const result = await Api.post('/api/resend-verification');
            if (!result) {
                Working.stop(button);
                return;
            }
            button.textContent = 'Sent!';
        });
    }
}

ReadyHandler.add(VerificationResendButton.init);
