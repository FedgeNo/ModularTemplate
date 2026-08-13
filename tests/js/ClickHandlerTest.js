import { TestCase } from './TestCase.js';
import { ClickHandler } from '../../scripts/ClickHandler.js';

export default {
    suite: 'ClickHandler',
    tests: {
        'init() dispatches to registered handler on matching click'() {
            let called = false;
            ClickHandler.init([
                { selector: '.btn', handler: () => { called = true; } },
            ]);
            const button = document.createElement('button');
            button.className = 'btn';
            document.body.appendChild(button);
            button.click();
            TestCase.assertTrue(called);
            document.body.removeChild(button);
        },
        'init() ignores clicks on non-matching elements'() {
            let called = false;
            ClickHandler.init([
                { selector: '.btn', handler: () => { called = true; } },
            ]);
            const link = document.createElement('a');
            link.className = 'link';
            document.body.appendChild(link);
            link.click();
            TestCase.assertFalse(called);
            document.body.removeChild(link);
        },
    }
};
