import { TestCase } from './TestCase.js';
import { Toast } from '../../scripts/Toast.js';

export default {
    suite: 'Toast',
    tests: {
        'show() creates a toast element with the message'() {
            const toast = Toast.show('Hello');
            TestCase.assertNotNull(toast);
            TestCase.assertTrue(toast.classList.contains('Toast'));
            TestCase.assertTrue(toast.textContent.includes('Hello'));
            TestCase.assertTrue(toast.classList.contains('Active'));
            toast.remove();
        },
        'dismiss() removes the Active class'() {
            const toast = Toast.show('Hello');
            Toast.dismiss(toast);
            TestCase.assertFalse(toast.classList.contains('Active'));
            toast.remove();
        },
    }
};
