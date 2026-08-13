import { TestCase } from './TestCase.js';
import { Search } from '../../scripts/Search.js';

// A search box as a page would render it, plus a stand-in for the network so
// a test can see what was actually searched for.
let realFetch = null;

function setUpSearchPage(query) {
    const searched = [];

    const box = document.createElement('div');
    box.className = 'SearchBox';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'SearchInput';
    box.appendChild(input);

    const list = document.createElement('ul');

    document.body.append(box, list);

    if (query === null) {
        window.history.replaceState({}, '', '/search');
    } else {
        window.history.replaceState({}, '', '/search?q=' + encodeURIComponent(query));
    }

    realFetch = globalThis.fetch;
    globalThis.fetch = async (url, options) => {
        searched.push(JSON.parse(options.body).q);

        return new Response(JSON.stringify({ response: { items: [], hasMore: false } }), { status: 200 });
    };

    const search = new Search(input, {
        endpoint: '/api/search-items',
        buildRequest: (q) => ({ q }),
        resultsContainer: list,
        renderItem: () => document.createElement('div'),
    });

    return { input, box, list, search, searched };
}

function tearDownSearchPage(search) {
    search.destroy();
    globalThis.fetch = realFetch;
    document.body.replaceChildren();
    window.history.replaceState({}, '', '/');
}

export default {
    suite: 'Search',
    tests: {
        'constructor creates an input listener'() {
            const input = document.createElement('input');
            input.type = 'text';
            document.body.appendChild(input);
            const results = document.createElement('div');
            const search = new Search(input, {
                endpoint: '/api/search-test',
                buildRequest: () => ({ q: '' }),
                resultsContainer: results,
                renderItem: (data) => document.createElement('div'),
            });
            TestCase.assertNotNull(search);
            TestCase.assertTrue(search instanceof Search);
            search.destroy();
            document.body.removeChild(input);
        },
        // Arriving with ?q= has to actually search. Filling the box and
        // dispatching an input event before the listener exists lands a linked
        // search on an empty page with the term sitting in the input.
        'a search page arrived at with a query runs that search'() {
            const { input, box, search, searched } = setUpSearchPage('kittens');

            Search.searchFromURL(search);

            TestCase.assertEquals('kittens', input.value);
            TestCase.assertTrue(searched.length > 0, 'the query should have been searched for');
            TestCase.assertEquals('kittens', searched[0]);
            TestCase.assertTrue(box.classList.contains('HasQuery'), 'the clear button has to be reachable');

            tearDownSearchPage(search);
        },

        'an empty query searches for nothing at all'() {
            // Otherwise the default content is hidden behind an empty result list.
            const { box, search, searched } = setUpSearchPage('   ');

            Search.searchFromURL(search);

            TestCase.assertEquals(0, searched.length);
            TestCase.assertFalse(box.classList.contains('HasQuery'));

            tearDownSearchPage(search);
        },

        'no query at all leaves the page alone'() {
            const { search, searched } = setUpSearchPage(null);

            Search.searchFromURL(search);

            TestCase.assertEquals(0, searched.length);

            tearDownSearchPage(search);
        },

        /**
         * Every endpoint names its results differently - users, items - so a
         * callback that reached into the response for a particular key threw
         * whenever it guessed wrong. It ran after the list had been emptied
         * and before anything was rendered into it, so the search went blank
         * rather than erroring visibly. Callbacks are handed the results
         * instead of digging for them.
         */
        async 'a search renders what the endpoint returned'() {
            const box = document.createElement('div');
            box.className = 'SearchBox';

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'SearchInput';
            box.appendChild(input);

            const list = document.createElement('ul');
            box.appendChild(list);

            document.body.appendChild(box);

            const previous_fetch = globalThis.fetch;
            globalThis.fetch = async () => new Response(
                JSON.stringify({ response: { users: [{ userId: 11, slug: 'andrew', title: 'Andrew Dunham' }], hasMore: false } }),
                { status: 200 }
            );

            let handed = null;
            const search = new Search(input, {
                endpoint: '/api/search-users',
                buildRequest: (q) => ({ q }),
                resultsContainer: list,
                renderItem: (userData) => {
                    const card = document.createElement('div');
                    card.className = 'User';
                    card.textContent = userData.title;
                    return card;
                },
                onResponse: (input_element, data, items) => { handed = items; },
            });

            input.value = 'andrew';
            input.dispatchEvent(new window.Event('input'));

            // Past the debounce, then past the fetch.
            await new Promise((resolve) => setTimeout(resolve, 400));

            TestCase.assertEquals(1, list.querySelectorAll('.User').length, 'the user should have been rendered');
            TestCase.assertEquals(1, handed.length, 'the callback is handed the extracted results');

            search.destroy();
            globalThis.fetch = previous_fetch;
            document.body.removeChild(box);
        },

        'constructor enables infinite scroll when requested'() {
            const input = document.createElement('input');
            input.type = 'text';
            document.body.appendChild(input);
            const results = document.createElement('div');
            const search = new Search(input, {
                endpoint: '/api/search-test',
                buildRequest: () => ({ q: '' }),
                resultsContainer: results,
                renderItem: (data) => document.createElement('div'),
                enableInfiniteScroll: true,
                countOffset: () => 0,
            });
            TestCase.assertNotNull(search.scroller);
            search.destroy();
            document.body.removeChild(input);
        },
    }
};
