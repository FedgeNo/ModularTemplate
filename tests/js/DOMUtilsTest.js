import { TestCase } from './TestCase.js';
import { DOMUtils } from '../../scripts/DOMUtils.js';

export default {
    suite: 'DOMUtils',
    tests: {
        'slideOut() removes element immediately when reduced motion is preferred'() {
            const originalMatchMedia = window.matchMedia;
            window.matchMedia = () => ({ matches: true });
            const ul = document.createElement('ul');
            const li = document.createElement('li');
            li.textContent = 'test';
            ul.appendChild(li);
            document.body.appendChild(ul);
            DOMUtils.slideOut(li);
            TestCase.assertNull(li.parentNode);
            window.matchMedia = originalMatchMedia;
            document.body.removeChild(ul);
        },
    }
};
