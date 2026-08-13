import { TestCase } from './TestCase.js';
import { Api } from '../../scripts/Api.js';
import { Toast } from '../../scripts/Toast.js';

/** Runs `body` with fetch replaced, counting what got toasted while it ran. */
async function withFetch(fake, body) {
    const real_fetch = globalThis.fetch;
    const real_toast = Toast.show;
    const said = [];

    globalThis.fetch = fake;
    Toast.show = (text) => said.push(text);

    try {
        return { result: await body(), said };
    } finally {
        globalThis.fetch = real_fetch;
        Toast.show = real_toast;
    }
}

export default {
    suite: 'Api',
    tests: {
        async 'post() returns response on success'() {
            const orig = globalThis.fetch;
            globalThis.fetch = async () => ({
                ok: true,
                json: async () => ({ response: { success: true } }),
            });
            const result = await Api.post('/api/test');
            TestCase.assertNotNull(result);
            globalThis.fetch = orig;
        },
        async 'post() handles network error gracefully'() {
            const orig = globalThis.fetch;
            globalThis.fetch = async () => { throw new Error('Network error'); };
            const result = await Api.post('/api/test');
            TestCase.assertNull(result);
            globalThis.fetch = orig;
        },
        async 'post() handles non-ok response with error message'() {
            const orig = globalThis.fetch;
            globalThis.fetch = async () => ({
                ok: false,
                json: async () => ({ error: 'Invalid' }),
            });
            const result = await Api.post('/api/test');
            TestCase.assertNull(result);
            globalThis.fetch = orig;
        },

        /**
         * The reason nothing has to call fetch itself any more: a call whose
         * failure is nobody's business can ask to stay silent.
         */
        async 'a quiet post says nothing when it fails'() {
            const { result, said } = await withFetch(
                async () => ({ ok: false, status: 500, json: async () => ({ error: 'Boom' }) }),
                () => Api.post('/api/test', {}, { quiet: true })
            );

            TestCase.assertNull(result);
            TestCase.assertEquals(0, said.length);
        },
        async 'a loud post still says so'() {
            const { said } = await withFetch(
                async () => ({ ok: false, status: 500, json: async () => ({ error: 'Boom' }) }),
                () => Api.post('/api/test', {})
            );

            TestCase.assertEquals('Boom', said.join(''));
        },

        /** An abort is somebody changing their mind, and was never a failure. */
        async 'an aborted post is silent whether or not it was asked to be'() {
            const abort = () => { const e = new Error('aborted'); e.name = 'AbortError'; throw e; };

            const { result, said } = await withFetch(abort, () => Api.post('/api/test', {}));

            TestCase.assertNull(result);
            TestCase.assertEquals(0, said.length);
        },

        /** request() hands back the status, which is why the scroller can use it. */
        async 'request() reports the status and never toasts'() {
            const { result, said } = await withFetch(
                async () => ({ ok: false, status: 429, json: async () => ({ error: 'Slow down' }) }),
                () => Api.request('/api/test', {})
            );

            TestCase.assertFalse(result.ok);
            TestCase.assertEquals(429, result.status);
            TestCase.assertEquals('Slow down', result.error);
            TestCase.assertEquals(0, said.length);
        },
        async 'request() marks an abort as one rather than as a failure to report'() {
            const abort = () => { const e = new Error('aborted'); e.name = 'AbortError'; throw e; };

            const { result } = await withFetch(abort, () => Api.request('/api/test', {}));

            TestCase.assertTrue(result.aborted);
            TestCase.assertNull(result.error);
        },

        /** FormData goes as it is, with no Content-Type of ours over the top. */
        async 'FormData is sent unencoded and untyped'() {
            let sent = null;
            const body = new FormData();
            body.append('favicon', 'x');

            await withFetch(
                async (url, options) => { sent = options; return { ok: true, json: async () => ({ response: {} }) }; },
                () => Api.post('/api/test', body)
            );

            TestCase.assertTrue(sent.body === body, 'passed through, not stringified');
            TestCase.assertTrue(sent.headers['Content-Type'] === undefined, 'the boundary is the browser\'s to write');
        },
        async 'keepalive is passed through for a request that outlives its page'() {
            let sent = null;

            await withFetch(
                async (url, options) => { sent = options; return { ok: true, json: async () => ({ response: {} }) }; },
                () => Api.post('/api/test', {}, { keepalive: true })
            );

            TestCase.assertTrue(sent.keepalive);
        },
    }
};
