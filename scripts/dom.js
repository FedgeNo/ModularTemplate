// dom.js – import once to enable parent.appendWithSpace(child) and
// parent.insertBeforeWithSpace(newNode, refNode)

const BLOCK_TAGS = new Set([
    'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
    'BLOCKQUOTE', 'PRE', 'UL', 'OL', 'LI',
    'DIV', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER',
]);

function isWhitespaceText(node) {
    return node && node.nodeType === Node.TEXT_NODE && /^\s*$/.test(node.data);
}

function isInlineElement(node) {
    return node && node.nodeType === Node.ELEMENT_NODE && !BLOCK_TAGS.has(node.tagName);
}

function spaceBetween(parent, prev, next) {
    const prevText = (prev.innerText || prev.textContent || '').trimEnd();
    const nextText = (next.innerText || next.textContent || '').trimStart();
    if (prevText.length > 0 && !/\s$/.test(prevText) &&
        nextText.length > 0 && !/^\s/.test(nextText)) {
        parent.insertBefore(document.createTextNode(' '), next);
    }
}

// ----------------------------------------------------------------
// appendWithSpace
// ----------------------------------------------------------------

Element.prototype.appendWithSpace = function (child) {
    if (child.nodeType !== Node.ELEMENT_NODE) {
        return this.appendChild(child);
    }

    const prev = this.lastChild;
    const result = this.appendChild(child);

    if (BLOCK_TAGS.has(child.tagName)) {
        // newline before
        if (prev && !isWhitespaceText(prev) && prev.nextSibling === child) {
            this.insertBefore(document.createTextNode('\n'), child);
        }
        // newline after
        const next = child.nextSibling;
        if (!isWhitespaceText(next)) {
            if (next) {
                this.insertBefore(document.createTextNode('\n'), next);
            } else {
                this.appendChild(document.createTextNode('\n'));
            }
        }
    } else if (isInlineElement(prev) && isInlineElement(child)) {
        spaceBetween(this, prev, child);
    }
    return result;
};

// ----------------------------------------------------------------
// insertBeforeWithSpace
// ----------------------------------------------------------------

Element.prototype.insertBeforeWithSpace = function (newNode, refNode) {
    if (newNode.nodeType !== Node.ELEMENT_NODE) {
        return this.insertBefore(newNode, refNode);
    }

    const prev = refNode ? refNode.previousSibling : this.lastChild;
    const next = refNode;  // the node that will become newNode's next sibling

    const result = this.insertBefore(newNode, refNode);

    if (BLOCK_TAGS.has(newNode.tagName)) {
        // newline before
        if (prev && !isWhitespaceText(prev) && prev.nextSibling === newNode) {
            this.insertBefore(document.createTextNode('\n'), newNode);
        }
        // newline after
        const after = newNode.nextSibling; // could be next if it was refNode, or something else
        if (!isWhitespaceText(after)) {
            if (after) {
                this.insertBefore(document.createTextNode('\n'), after);
            } else {
                this.appendChild(document.createTextNode('\n'));
            }
        }
    } else {
        // inline spacing
        if (isInlineElement(prev) && isInlineElement(newNode)) {
            spaceBetween(this, prev, newNode);
        }
        if (isInlineElement(newNode) && isInlineElement(next)) {
            spaceBetween(this, newNode, next);
        }
    }
    return result;
};
