import { TestCase } from './TestCase.js';
import { ClientConfig } from '../../scripts/ClientConfig.js';

/**
 * The floor under the config cookie.
 *
 * A value added to ClientConfig::send() does not reach a page whose cookie was
 * written before it existed, and that page keeps using the old cookie until it
 * navigates. Every reader would otherwise need its own fall-back literal - a
 * second copy of the server's number, which is the drift this is meant to
 * avoid - so the defaults sit underneath whatever the cookie says.
 */
export default {
    suite: 'ClientConfig',
    tests: {
        'a page with no cookie still answers for every value'() {
            for (const key of ['siteURL', 'locale', 'serverTime']) {
                TestCase.assertNotNull(ClientConfig.get(key), key + ' has no default');
            }
        },
        'nobody is signed in until the cookie says so'() {
            TestCase.assertEquals(null, ClientConfig.get('currentUserId'));
            TestCase.assertEquals(false, ClientConfig.get('currentUserCanModerate'));
        },
    },
};

// The other half - that a cookie predating a value still answers for it - has
// no test, and cannot get one cheaply: ClientConfig parses the cookie once and
// keeps it for the life of the page, which is right in a browser and means any
// test writing a cookie leaves its config behind for every suite that runs
// after it. Reaching for write_client_config() here is the trap; it passes and
// breaks something three files later.
