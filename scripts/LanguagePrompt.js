import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Choosing which language to read the site in - from the offer made to
 * somebody whose browser is set to one this site speaks, and from the selector
 * in their settings.
 *
 * Both do the same thing, because they are the same decision: tell the server,
 * then load the page again so it arrives in the language just chosen. A page
 * already built cannot be re-translated in place - every string on it came
 * from the server - so the reload is the point rather than a shortcut.
 *
 * The offer is asked as a real dialog, which is what makes it answerable by
 * somebody on a keyboard: it holds focus, Escape answers it, and the page
 * behind it is not still tabbable underneath. The server renders the words -
 * they are in a language this page is not in, so they cannot come from the
 * strings the browser loaded - and this hands them to the dialog.
 *
 * Declining is an answer too. It is recorded the same way, so the offer is
 * made once and not on every page after.
 */
export class LanguagePrompt {
    static init() {
        document.addEventListener('change', async (event) => {
            const select = event.target.closest('.LanguageSelect');

            if (select) {
                await LanguagePrompt.choose(select.value);
            }
        });

        const offer = document.querySelector('.LanguagePrompt');

        if (offer) {
            LanguagePrompt.ask(offer);
        }
    }

    static async ask(offer) {
        const locale = offer.dataset.locale;
        const words = (selector) => offer.querySelector(selector)?.textContent ?? '';

        const question = words('.LanguagePromptQuestion');
        const accept = words('.LanguagePromptAccept');
        const decline = words('.LanguagePromptDecline');

        offer.remove();

        if (await Dialog.confirm(question, { confirmText: accept, cancelText: decline })) {
            await LanguagePrompt.choose(locale);

            return;
        }

        // Staying put changes nothing on the page, so nothing is fetched again -
        // but it is still an answer, and recording it is what stops the asking.
        await LanguagePrompt.remember(document.documentElement.lang);
    }

    /** Tells the server, and says whether it took. */
    static async remember(locale) {
        if (!locale) {
            return false;
        }

        return await Api.post('/api/set-language', { locale }) !== null;
    }

    static async choose(locale) {
        if (await LanguagePrompt.remember(locale)) {
            window.location.reload();
        }
    }
}

ReadyHandler.add(LanguagePrompt.init);
