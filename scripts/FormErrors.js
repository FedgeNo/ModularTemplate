/**
 * Puts a server's refusal under the input it is about.
 *
 * A toast says "that is already your email address" at the edge of the screen
 * and leaves somebody to work out which of five boxes it means - awkward
 * looking at it, hopeless not looking at it. Here the reason sits under its
 * own box, tied to it by aria-describedby so it is read out on reaching the
 * box, and the box is marked invalid so the eye lands on which one before
 * reading why.
 *
 * All of them at once, too. An endpoint that checks one thing, refuses, and
 * makes somebody press the button again to be told the next thing wastes five
 * round trips on one form; these arrive together.
 *
 * The markup mirrors InputField::errorElement() on the server, which renders
 * the same thing for a refusal known before the page is drawn.
 */
export class FormErrors {
    /** Takes down whatever the last attempt said. */
    static clear(form) {
        if (!form) return;

        form.querySelectorAll('.FieldError').forEach((error) => error.remove());
        form.querySelectorAll('[aria-invalid="true"]').forEach((field) => {
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');
        });
    }

    /**
     * Marks each named field with its reason.
     *
     * @param {Element} form
     * @param {Object<string, string>} fields reason by field name
     * @returns {boolean} whether any of them was found to mark
     */
    static show(form, fields) {
        if (!form || !fields) return false;

        FormErrors.clear(form);

        let first = null;

        for (const [name, reason] of Object.entries(fields)) {
            const field = form.querySelector('[name="' + name + '"]');

            if (!field) continue;

            const id = name + 'Error';

            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', id);

            const error = document.createElement('p');
            error.className = 'FieldError';
            error.id = id;
            error.textContent = reason;

            // After the input, inside whatever wraps the two - which is where
            // the server puts it, and what keeps it with its own field rather
            // than at the foot of the form.
            field.parentNode?.insertBefore(error, field.nextSibling);

            if (first === null) first = field;
        }

        // Straight to the first thing to fix, so nobody has to go looking for
        // where the form went wrong.
        first?.focus();

        return first !== null;
    }
}
