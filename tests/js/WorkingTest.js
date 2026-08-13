import { TestCase } from './TestCase.js';
import { Working } from '../../scripts/Working.js';

/**
 * A control waiting on the server.
 *
 * Disabling alone dims a button slightly and then nothing happens, which on
 * anything slow - a translation is a round trip to a model - reads as a press
 * that did not land. The thing worth holding to account is the giving back:
 * whatever went wrong in between, a control must never be left disabled and
 * pulsing at nothing.
 */
export default {
    suite: 'Working',
    tests: {
        'a working control is disabled, marked, and says so to assistive tech'() {
            const button = document.createElement('button');

            Working.start(button);

            TestCase.assertTrue(button.disabled);
            TestCase.assertTrue(button.classList.contains('Working'));
            TestCase.assertEquals('true', button.getAttribute('aria-busy'));
        },
        'stopping gives back everything starting took'() {
            const button = document.createElement('button');

            Working.start(button);
            Working.stop(button);

            TestCase.assertFalse(button.disabled);
            TestCase.assertFalse(button.classList.contains('Working'));
            TestCase.assertNull(button.getAttribute('aria-busy'));
        },
        'stopping one that never started leaves it alone'() {
            const button = document.createElement('button');

            Working.stop(button);

            TestCase.assertFalse(button.disabled);
            TestCase.assertFalse(button.classList.contains('Working'));
        },
        'a control that is not there is not an error'() {
            // Several callers hold a button that only some pages render, and
            // guard the call with `if (this.draftButton)` - or forget to.
            Working.start(null);
            Working.stop(undefined);

            TestCase.assertTrue(true);
        },
    },
};
