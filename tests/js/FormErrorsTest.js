import { TestCase } from './TestCase.js';
import { FormErrors } from '../../scripts/FormErrors.js';

/**
 * A refusal put under the input it is about.
 *
 * The point is not tidier markup: it is that somebody told "that is already
 * your email address" by a toast at the corner of the screen has to work out
 * which of five boxes it means, and somebody not looking at the screen cannot.
 */
function formWith(names) {
    document.body.replaceChildren();

    const form = document.createElement('form');

    for (const name of names) {
        const wrapper = document.createElement('div');
        const input = document.createElement('input');
        input.name = name;
        wrapper.appendChild(input);
        form.appendChild(wrapper);
    }

    document.body.appendChild(form);

    return form;
}

export default {
    suite: 'FormErrors',
    tests: {
        'the reason goes under its own input, tied to it'() {
            const form = formWith(['email']);

            FormErrors.show(form, { email: 'Please give a valid email address.' });

            const input = form.querySelector('[name="email"]');
            const error = form.querySelector('.FieldError');

            TestCase.assertNotNull(error);
            TestCase.assertEquals('Please give a valid email address.', error.textContent);
            TestCase.assertEquals('true', input.getAttribute('aria-invalid'));
            TestCase.assertEquals(error.id, input.getAttribute('aria-describedby'));
            TestCase.assertTrue(input.nextSibling === error, 'directly after the input');
        },
        'every bad field is marked at once, not one per attempt'() {
            const form = formWith(['currentPassword', 'newPassword', 'confirmPassword']);

            FormErrors.show(form, {
                currentPassword: 'That is not your current password.',
                newPassword: 'Use at least 8 characters.',
                confirmPassword: 'This does not match the new password.',
            });

            TestCase.assertEquals(3, form.querySelectorAll('.FieldError').length);
            TestCase.assertEquals(3, form.querySelectorAll('[aria-invalid="true"]').length);
        },
        'focus lands on the first thing to fix'() {
            const form = formWith(['username', 'email', 'password']);

            FormErrors.show(form, { email: 'Taken.', password: 'Too short.' });

            TestCase.assertTrue(document.activeElement === form.querySelector('[name="email"]'));
        },
        'a second attempt clears what the first said'() {
            const form = formWith(['email', 'password']);

            FormErrors.show(form, { email: 'Taken.', password: 'Too short.' });
            FormErrors.show(form, { password: 'Still too short.' });

            TestCase.assertEquals(1, form.querySelectorAll('.FieldError').length);
            TestCase.assertNull(form.querySelector('[name="email"]').getAttribute('aria-invalid'));
        },
        'a field this form does not have is left for the toast'() {
            const form = formWith(['email']);

            const marked = FormErrors.show(form, { somethingElse: 'Nowhere to put this.' });

            TestCase.assertFalse(marked, 'says it marked nothing, so the caller can fall back');
            TestCase.assertEquals(0, form.querySelectorAll('.FieldError').length);
        },
        'clearing takes down the marks and the reasons'() {
            const form = formWith(['email']);

            FormErrors.show(form, { email: 'Taken.' });
            FormErrors.clear(form);

            TestCase.assertEquals(0, form.querySelectorAll('.FieldError').length);
            TestCase.assertNull(form.querySelector('[name="email"]').getAttribute('aria-describedby'));
        },
    },
};
