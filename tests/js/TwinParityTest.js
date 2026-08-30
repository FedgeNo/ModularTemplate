import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { TestCase } from './TestCase.js';
import { canonical_lines, first_difference } from './DOMCanonicalForm.js';
import { Strings } from '../../scripts/Strings.js';
import * as HTMLObjectExports from '../../scripts/HTMLObjects.js';
import { RelativeTime } from '../../scripts/RelativeTime.js';
import { ToggleButton } from '../../scripts/ToggleButton.js';

const projectRoot = resolve(import.meta.dirname, '../..');
const TWINS = {
    ...HTMLObjectExports,
    RelativeTime,
    ToggleButton,
};
const PARITY_EXCLUSIONS = new Map([
    ['HTMLObject', 'abstract DOM-building base with no tag name, so it cannot render'],
]);

function useSameWordsAsTheServer() {
    Strings.useLocale(JSON.parse(readFileSync(resolve(projectRoot, 'locales/en.json'), 'utf8')), 'en');
}

function cases() {
    const output = execFileSync('php', [resolve(projectRoot, 'bin/twin-parity-cases.php')], { encoding: 'utf8' });
    return Object.entries(JSON.parse(output));
}

const serverCases = cases();
const tests = {
    'every server case names a client twin'() {
        const missing = serverCases.map(([, item]) => item.class).filter(name => !TWINS[name]);
        TestCase.assertEquals('', [...new Set(missing)].join(', '));
    },
    'every exported HTML object is covered or deliberately excluded'() {
        const exportedClassNames = Object.entries(HTMLObjectExports)
            .filter(([, exported]) => typeof exported === 'function')
            .map(([name]) => name);
        const coveredClassNames = new Set(serverCases.map(([, item]) => item.class));
        const uncovered = exportedClassNames.filter(name =>
            !coveredClassNames.has(name) && !PARITY_EXCLUSIONS.has(name)
        );
        const staleExclusions = [...PARITY_EXCLUSIONS.keys()].filter(name =>
            !Object.hasOwn(HTMLObjectExports, name)
        );
        const failures = [];

        if (uncovered.length > 0) {
            failures.push('exported classes without parity cases: ' + uncovered.join(', '));
        }

        if (staleExclusions.length > 0) {
            failures.push('parity exclusions that are no longer exported: ' + staleExclusions.join(', '));
        }

        TestCase.assertEquals('', failures.join('; '));
    },
};

for (const [name, serverCase] of serverCases) {
    tests[`${name} builds the DOM the server rendered`] = () => {
        const Twin = TWINS[serverCase.class];
        TestCase.assertNotNull(Twin, `no client twin registered for ${serverCase.class}`);
        useSameWordsAsTheServer();

        const object = new Twin(...serverCase.arguments);
        Object.assign(object.attributes, serverCase.attributes);
        serverCase.content.forEach(content => object.addContent(content));

        const difference = first_difference(serverCase.canonical, canonical_lines(object.toDOM()));
        TestCase.assertNull(difference, `${name}: the client twin diverged from the server - ${difference}`);
    };
}

export default { suite: 'TwinParity', tests };
