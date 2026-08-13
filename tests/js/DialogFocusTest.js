import { TestCase } from './TestCase.js';
import { Dialog } from '../../scripts/Dialog.js';

/**
 * Where the keyboard is while a dialog is open, and where it goes afterwards.
 *
 * A dialog that does not hold focus is decoration: Tab walks out of it into
 * the page behind, which is still there and still reachable. And one that does
 * not hand focus back leaves somebody who deleted a post at the top of the
 * document, hunting for where they were. Both were true here.
 */
export default {
    suite: 'Dialog focus',
    tests: {
        async 'it names itself as a dialog and by the words in it'() {
            document.body.replaceChildren();

            const pending = Dialog.confirm('Delete this post?');
            const card = document.querySelector('.ConfirmDialogCard');

            TestCase.assertEquals('dialog', card.getAttribute('role'));
            TestCase.assertEquals('true', card.getAttribute('aria-modal'));

            const named_by = document.getElementById(card.getAttribute('aria-labelledby'));

            TestCase.assertEquals('Delete this post?', named_by.textContent);

            card.querySelector('.ConfirmDialogCancelButton').click();
            await pending;
        },
        async 'focus goes back where it came from'() {
            document.body.replaceChildren();

            const opener = document.createElement('button');
            opener.textContent = 'Delete';
            document.body.appendChild(opener);
            opener.focus();

            const pending = Dialog.confirm('Delete this post?');

            TestCase.assertFalse(document.activeElement === opener, 'the dialog took focus');

            document.querySelector('.ConfirmDialogCancelButton').click();
            await pending;

            TestCase.assertTrue(document.activeElement === opener, 'and gave it back');
        },
        async 'tab from the last control wraps to the first instead of leaving'() {
            document.body.replaceChildren();

            const outside = document.createElement('button');
            document.body.appendChild(outside);

            const pending = Dialog.confirm('Delete this post?');
            const card = document.querySelector('.ConfirmDialogCard');
            const buttons = [...card.querySelectorAll('button')];
            const last = buttons[buttons.length - 1];

            last.focus();

            const tab = new window.KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
            document.dispatchEvent(tab);

            TestCase.assertTrue(tab.defaultPrevented, 'the tab was caught');
            TestCase.assertTrue(document.activeElement === buttons[0], 'and sent back to the top of the dialog');

            card.querySelector('.ConfirmDialogCancelButton').click();
            await pending;
        },
    },
};
