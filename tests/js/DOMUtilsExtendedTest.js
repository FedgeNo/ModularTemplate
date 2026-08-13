import { TestCase } from './TestCase.js';
import { DOMUtils } from '../../scripts/DOMUtils.js';

export default {
    suite: 'DOMUtilsExtended',
    tests: {
        'slideOut() with normal motion adds SlidingOut class'() {
            const orig = window.matchMedia;
            window.matchMedia = () => ({ matches: false });
            const ul = document.createElement('ul');
            const li = document.createElement('li');
            li.textContent = 'test';
            ul.appendChild(li);
            document.body.appendChild(ul);
            DOMUtils.slideOut(li);
            TestCase.assertTrue(li.classList.contains('SlidingOut'));
            window.matchMedia = orig;
        },
        'slideOut() with null does nothing'() {
            DOMUtils.slideOut(null);
            TestCase.assertTrue(true);
        },
    }
};
