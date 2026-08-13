import { TestCase } from './TestCase.js';
import { Cookie } from '../../scripts/Cookie.js';

export default {
    suite: 'Cookie',
    tests: {
        'get() returns null for missing cookie'() {
            document.cookie = 'existing=value;';
            TestCase.assertNull(Cookie.get('nonexistent'));
        },
        'get() returns decoded value'() {
            document.cookie = 'test=%20hello%20;';
            TestCase.assertEquals(' hello ', Cookie.get('test'));
        },
        'get() handles special regex characters in cookie name'() {
            document.cookie = 'some.cookie$=works;';
            TestCase.assertEquals('works', Cookie.get('some.cookie$'));
        },
    }
};
