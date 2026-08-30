export class Dialog {
    // Currently active cancel callback – set by confirm/prompt, cleared on close
    static #activeCancel = null;

    /** Ids for aria-labelledby, since a dialog is named by the words in it. */
    static #counter = 0;

    /**
     * Everything inside the card a keyboard can reach, in the order it will.
     */
    static #focusable(card) {
        const selector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

        return [...card.querySelectorAll(selector)].filter((element) => !element.disabled);
    }

    /**
     * Makes a dialog behave like one.
     *
     * Without this the card is decoration: Tab walks straight out of it into
     * the page behind, which is still there and still clickable, and on close
     * the focus somebody had is gone - they are returned to the top of the
     * document to find their way back to whatever they were deleting. So the
     * card says what it is, keeps Tab inside itself while it is open, and
     * hands focus back where it came from afterwards.
     *
     * @returns {() => void} call it when the dialog closes
     */
    static #trap(card, message_element) {
        const returnTo = document.activeElement;

        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-modal', 'true');

        if (message_element) {
            const id = 'DialogMessage' + (++Dialog.#counter);
            message_element.id = id;
            card.setAttribute('aria-labelledby', id);
        }

        const onTab = (event) => {
            if (event.key !== 'Tab') return;

            const focusable = Dialog.#focusable(card);

            if (focusable.length === 0) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', onTab);

        return () => {
            document.removeEventListener('keydown', onTab);

            // Back where they were. A dialog opened from a post's delete
            // button should leave them on that button, not at the top of a
            // feed they now have to scroll through again.
            if (returnTo && typeof returnTo.focus === 'function' && returnTo.isConnected) {
                returnTo.focus();
            }
        };
    }

    /**
     * Show a confirmation dialog with OK / Cancel buttons.
     * The buttons can be labelled with the answers themselves rather than OK
     * and Cancel - a question whose two answers are in different languages has
     * to say each one in its own.
     *
     * @param {string} message
     * @param {{ confirmText?: string, cancelText?: string }} labels
     * @returns {Promise<boolean>} – resolves true for OK, false for Cancel/Escape
     */
    static confirm(message, { confirmText = 'OK', cancelText = 'Cancel' } = {}) {
        Dialog.#activeCancel?.();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'ConfirmDialogOverlay';

            const card = document.createElement('div');
            card.className = 'ConfirmDialogCard';

            const text = document.createElement('div');
            text.className = 'ConfirmDialogMessage';
            text.textContent = message;
            card.appendWithSpace(text);

            const actions = document.createElement('div');
            actions.className = 'ConfirmDialogActions';

            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'Button ConfirmDialogCancelButton';
            cancelButton.textContent = cancelText;

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'Button ConfirmDialogConfirmButton';
            confirmButton.textContent = confirmText;

            actions.appendWithSpace(cancelButton);
            actions.appendWithSpace(confirmButton);
            card.appendWithSpace(actions);
            overlay.appendWithSpace(card);
            document.body.appendWithSpace(overlay);

            const release = Dialog.#trap(card, text);

            const finish = (confirmed) => {
                Dialog.#activeCancel = null;
                document.removeEventListener('keydown', onKeydown);
                release();
                overlay.remove();
                resolve(confirmed);
            };

            Dialog.#activeCancel = () => finish(false);

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish(false);
                }
            };

            cancelButton.addEventListener('click', () => finish(false));
            confirmButton.addEventListener('click', () => finish(true));
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    finish(false);
                }
            });
            document.addEventListener('keydown', onKeydown);

            cancelButton.focus();
        });
    }

    /**
     * Show a message with a single OK button.
     * @param {string} message
     * @returns {Promise<void>} – resolves when dismissed
     */
    static alert(message) {
        Dialog.#activeCancel?.();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'ConfirmDialogOverlay';

            const card = document.createElement('div');
            card.className = 'ConfirmDialogCard';

            const text = document.createElement('div');
            text.className = 'ConfirmDialogMessage';
            text.textContent = message;
            card.appendWithSpace(text);

            const actions = document.createElement('div');
            actions.className = 'ConfirmDialogActions';

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'Button ConfirmDialogConfirmButton';
            confirmButton.textContent = 'OK';

            actions.appendWithSpace(confirmButton);
            card.appendWithSpace(actions);
            overlay.appendWithSpace(card);
            document.body.appendWithSpace(overlay);

            const release = Dialog.#trap(card, text);

            const finish = () => {
                Dialog.#activeCancel = null;
                document.removeEventListener('keydown', onKeydown);
                release();
                overlay.remove();
                resolve();
            };

            Dialog.#activeCancel = finish;

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish();
                }
            };

            confirmButton.addEventListener('click', finish);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    finish();
                }
            });
            document.addEventListener('keydown', onKeydown);

            confirmButton.focus();
        });
    }

    /**
     * Show a prompt dialog with a textarea and a confirm button.
     * @param {string} message
     * @param {object} [options]
     * @param {string} [options.placeholder] – placeholder text for the textarea
     * @param {string} [options.confirmLabel='OK'] – confirm button label
     * @returns {Promise<string|null>} – resolves with the input value, or null for Cancel/Escape
     */
    static prompt(message, options = {}) {
        Dialog.#activeCancel?.();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'ConfirmDialogOverlay';

            const card = document.createElement('div');
            card.className = 'ConfirmDialogCard';

            const text = document.createElement('div');
            text.className = 'ConfirmDialogMessage';
            text.textContent = message;
            card.appendWithSpace(text);

            const input = document.createElement('textarea');
            input.className = 'ConfirmDialogInput';
            input.rows = 3;

            if (options.placeholder) {
                input.placeholder = options.placeholder;
            }

            card.appendWithSpace(input);

            const actions = document.createElement('div');
            actions.className = 'ConfirmDialogActions';

            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'Button ConfirmDialogCancelButton';
            cancelButton.textContent = 'Cancel';

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'Button ConfirmDialogConfirmButton';
            confirmButton.textContent = options.confirmLabel || 'OK';
            // Off until the box has something in it - a rule about the input,
            // not a wait, so it does not throb.
            confirmButton.disabled = true;

            actions.appendWithSpace(cancelButton);
            actions.appendWithSpace(confirmButton);
            card.appendWithSpace(actions);
            overlay.appendWithSpace(card);
            document.body.appendWithSpace(overlay);

            const release = Dialog.#trap(card, text);

            // The question above the box is the box's label. A placeholder is
            // not one: it is gone the moment anybody types.
            input.setAttribute('aria-labelledby', text.id);

            const finish = (value) => {
                Dialog.#activeCancel = null;
                document.removeEventListener('keydown', onKeydown);
                release();
                overlay.remove();
                resolve(value);
            };

            Dialog.#activeCancel = () => finish(null);

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish(null);
                }
            };

            input.addEventListener('input', () => {
                confirmButton.disabled = input.value.trim() === '';
            });

            cancelButton.addEventListener('click', () => finish(null));
            confirmButton.addEventListener('click', () => {
                const value = input.value.trim();
                if (value !== '') {
                    finish(value);
                }
            });
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    finish(null);
                }
            });
            document.addEventListener('keydown', onKeydown);

            input.focus();
        });
    }
}
