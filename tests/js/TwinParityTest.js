import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { TestCase } from './TestCase.js';
import { canonical_lines, first_difference } from './DOMCanonicalForm.js';
import { Strings } from '../../scripts/Strings.js';
import {
    Anchor, Article, Button, Card, Div, Figure, Heading2, Heading3, Image,
    ListItem, Paragraph, Section, Span, Table, TableData, TableHeader, TableRow,
    UnorderedList,
} from '../../scripts/HTMLObjects.js';
import { RelativeTime } from '../../scripts/RelativeTime.js';
import { ToggleButton } from '../../scripts/ToggleButton.js';

const projectRoot = resolve(import.meta.dirname, '../..');
const TWINS = {
    Anchor, Article, Button, Card, Div, Figure, Heading2, Heading3, Image,
    ListItem, Paragraph, RelativeTime, Section, Span, Table, TableData,
    TableHeader, TableRow, ToggleButton, UnorderedList,
};

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
