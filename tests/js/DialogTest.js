import { TestCase } from './TestCase.js';
import { Dialog } from '../../scripts/Dialog.js';

export default {
    suite: 'Dialog',
    tests: {
        'confirm() returns a promise'() {
            const p = Dialog.confirm('test');
            TestCase.assertTrue(p instanceof Promise);
            p.then(() => {});
        },
        'prompt() returns a promise'() {
            const p = Dialog.prompt('test');
            TestCase.assertTrue(p instanceof Promise);
            p.then(() => {});
        },
    }
};
