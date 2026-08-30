const URL_ATTRIBUTES = new Set(['href', 'src', 'action', 'poster', 'formaction']);

/** Reduce a DOM tree to the same stable list of lines as DOMCanonicalForm.php. */
export function canonical_lines(node, depth = 0) {
    if (node.nodeType === 3) {
        const text = node.textContent.replace(/\s+/gu, ' ').trim();

        return text === '' ? [] : ['  '.repeat(depth) + '#text ' + JSON.stringify(text)];
    }

    if (node.nodeType !== 1) return [];

    const written = [...node.attributes]
        .map(attribute => [
            attribute.name,
            URL_ATTRIBUTES.has(attribute.name) ? route(attribute.value) : attribute.value,
        ])
        .sort(([left], [right]) => (left < right ? -1 : left > right ? 1 : 0))
        .map(([name, value]) => name + '=' + JSON.stringify(value));

    const lines = ['  '.repeat(depth) + node.tagName.toLowerCase()
        + (written.length === 0 ? '' : ' ' + written.join(' '))];

    for (const child of node.childNodes) {
        lines.push(...canonical_lines(child, depth + 1));
    }

    return lines;
}

function route(url) {
    try {
        const parsed = new URL(url, document.baseURI);

        return parsed.pathname + parsed.search + parsed.hash;
    } catch {
        return url;
    }
}

/** Describe the first node at which two canonical DOM forms diverge. */
export function first_difference(expected, actual) {
    const length = Math.max(expected.length, actual.length);

    for (let index = 0; index < length; index++) {
        const want = expected[index] ?? '(nothing)';
        const got = actual[index] ?? '(nothing)';

        if (want !== got) {
            return `line ${index}: PHP rendered ${want.trim()} - JavaScript rendered ${got.trim()}`;
        }
    }

    return null;
}
