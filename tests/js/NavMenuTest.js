import { TestCase } from './TestCase.js';
import { NavMenu } from '../../scripts/NavMenu.js';

/**
 * The mobile menu saying whether it is open.
 *
 * The menu itself is a checkbox and some CSS, and works with no JavaScript -
 * so the thing worth holding to account is that this stays an addition. It
 * reports the state; it must never become the state, or a browser that never
 * runs it gets a menu that no longer opens.
 */
function menu(checked = false) {
    document.body.replaceChildren();

    const toggle = document.createElement('input');
    toggle.type = 'checkbox';
    toggle.id = 'NavToggle';
    toggle.checked = checked;
    document.body.appendChild(toggle);

    NavMenu.init();

    return toggle;
}

export default {
    suite: 'NavMenu',
    tests: {
        'a closed menu says it is closed'() {
            TestCase.assertEquals('false', menu().getAttribute('aria-expanded'));
        },
        'one already open says so from the start'() {
            TestCase.assertEquals('true', menu(true).getAttribute('aria-expanded'));
        },
        'opening it is announced'() {
            const toggle = menu();

            toggle.checked = true;
            toggle.dispatchEvent(new window.Event('change'));

            TestCase.assertEquals('true', toggle.getAttribute('aria-expanded'));

            toggle.checked = false;
            toggle.dispatchEvent(new window.Event('change'));

            TestCase.assertEquals('false', toggle.getAttribute('aria-expanded'));
        },
        /**
         * The checkbox is what the CSS keys on. If this module ever started
         * intercepting the press, a browser without it would have no menu.
         */
        'it never takes the state away from the checkbox'() {
            const toggle = menu();

            toggle.click();

            TestCase.assertTrue(toggle.checked, 'the press still lands on the checkbox');
            TestCase.assertEquals('true', toggle.getAttribute('aria-expanded'));
        },
        'a page with no menu is not an error'() {
            document.body.replaceChildren();

            NavMenu.init();

            TestCase.assertTrue(true);
        },
    },
};
