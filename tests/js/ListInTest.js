import { TestCase } from './TestCase.js';
import { list_in } from '../../scripts/utils.js';

/**
 * An empty list isn't rendered at all - only the notice saying it is empty -
 * so the first item to arrive has to bring the list with it.
 */
export default {
    suite: 'list_in',
    tests: {
        'the list is built over the notice standing in its place'() {
            const container = document.createElement('div');
            container.innerHTML = '<p class="Notice muted">No blocked servers.</p>';

            const list = list_in(container, 'BlockedServerList d-flex flex-column');

            TestCase.assertNotNull(list);
            TestCase.assertEquals('UL', list.tagName);
            TestCase.assertEquals('BlockedServerList d-flex flex-column', list.className);
            TestCase.assertNull(container.querySelector('.Notice'));
            TestCase.assertEquals(list, container.firstElementChild);
        },
        'an existing list is handed back untouched'() {
            const container = document.createElement('div');
            const existing = document.createElement('ul');
            existing.className = 'BlockedServerList d-flex flex-column';
            existing.appendChild(document.createElement('li'));
            container.appendChild(existing);

            const list = list_in(container, 'BlockedServerList d-flex flex-column');

            TestCase.assertEquals(existing, list);
            TestCase.assertEquals(1, list.children.length);
        },
        'a container with neither is null rather than a list nobody asked for'() {
            const container = document.createElement('div');

            TestCase.assertNull(list_in(container, 'BlockedServerList'));
            TestCase.assertNull(container.querySelector('ul'));
        },
        'a missing container is null rather than a crash'() {
            TestCase.assertNull(list_in(null, 'BlockedServerList'));
        },
    }
};
