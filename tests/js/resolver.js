import { pathToFileURL } from 'node:url';
import { resolve as pathResolve } from 'node:path';

const projectRoot = pathResolve(import.meta.dirname, '../..');

export function resolve(specifier, context, nextResolve) {
    // Only remap bare absolute imports like '/ClientConfig.js'
    // (specifiers that start with a single '/' and don't already
    // point to a file inside the project root).
    if (specifier.startsWith('/') && !specifier.startsWith(projectRoot + '/')) {
        const filePath = pathResolve(projectRoot, specifier.slice(1));
        return nextResolve(pathToFileURL(filePath).href, context);
    }
    return nextResolve(specifier, context);
}

