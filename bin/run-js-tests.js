import { register } from 'node:module';

register('../tests/js/resolver.js', import.meta.url);

import { readdirSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import { JSDOM } from 'jsdom';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');
const testsDir = resolve(projectRoot, 'tests/js');

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
    url: 'http://localhost/',
    pretendToBeVisual: true,
});
globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.Element = dom.window.Element;
globalThis.Node = dom.window.Node;
globalThis.HTMLElement = dom.window.HTMLElement;
globalThis.CustomEvent = dom.window.CustomEvent;
globalThis.NodeFilter = dom.window.NodeFilter;

await import('../scripts/dom.js');

// Mocks for modules that need them
globalThis.ClientConfig = {
    get: (key) => key === 'currentUserId' ? 2 : null,
    siteURL: () => 'http://localhost',
};
// jsdom implements neither fetch nor Response, so the Node runtime's own
// Response is what stands in. Assigning jsdom's (undefined) over it is what made
// this stub throw on every call, so any test reaching fetch only ever exercised
// the code's error branch.
globalThis.fetch = async () => new Response(
    JSON.stringify({ response: { success: true } }),
    { status: 200 }
);
globalThis.requestAnimationFrame = (cb) => cb();
dom.window.requestAnimationFrame = (cb) => cb();

let total = 0, passed = 0;
const failures = [];

const files = readdirSync(testsDir).filter(f => f.endsWith('Test.js') && f !== 'resolver.js');
for (const file of files) {
    const module = await import(resolve(testsDir, file));
    const { suite, tests } = module.default;
    console.group(suite);
    for (const [name, fn] of Object.entries(tests)) {
        total++;
        try {
            await fn();
            console.log(`  \x1b[32mPASS\x1b[0m  ${suite} :: ${name}`);
            passed++;
        } catch (err) {
            console.log(`  \x1b[31mFAIL\x1b[0m  ${suite} :: ${name}  —  ${err.message}`);
            failures.push({ suite, name, message: err.message });
        }
    }
    console.groupEnd();
}
console.log(`\n${passed}/${total} passed`);
if (failures.length) {
    console.group('Failures');
    failures.forEach(f => console.log(`  ${f.suite} :: ${f.name}  —  ${f.message}`));
    console.groupEnd();
}

dom.window.close();
process.exit(failures.length ? 1 : 0);
