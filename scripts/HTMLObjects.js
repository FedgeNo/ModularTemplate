const STRUCTURAL_PROPERTIES = new Set([
    'tagName', 'class', 'attributes', 'contents', 'rendered', 'items', 'contentType',
]);
const PROPERTY_DEFAULTS = new WeakMap();

function copyDefault(value) {
    if (Array.isArray(value)) return value.map(copyDefault);

    if (value !== null && typeof value === 'object' && Object.getPrototypeOf(value) === Object.prototype) {
        return Object.fromEntries(Object.entries(value).map(([name, item]) => [name, copyDefault(item)]));
    }

    return value;
}

/** Client-side DOM-building core mirroring PHP's HTMLObject contract. */
export class HTMLObject {
    static tagName = null;
    static className = null;
    static properties = { id: null };

    #rendered = false;

    constructor(properties = null) {
        this.class = null;
        this.attributes = {};
        this.contents = [];

        const defaults = this.constructor.propertyDefaults();

        for (const [name, value] of Object.entries(defaults)) {
            this[name] = copyDefault(value);
        }

        if (properties !== null) {
            for (const [name, value] of Object.entries(properties)) {
                if (Object.hasOwn(defaults, name)) this[name] = value;
            }
        }
    }

    static propertyDefaults() {
        if (PROPERTY_DEFAULTS.has(this)) return PROPERTY_DEFAULTS.get(this);

        const levels = [];

        for (let type = this; type && type !== Function.prototype; type = Object.getPrototypeOf(type)) {
            levels.push(type);
            if (type === HTMLObject) break;
        }

        const defaults = {};

        levels.reverse().forEach(type => {
            if (!Object.hasOwn(type, 'properties')) return;

            for (const [name, value] of Object.entries(type.properties)) {
                if (!STRUCTURAL_PROPERTIES.has(name)) defaults[name] = value;
            }
        });

        PROPERTY_DEFAULTS.set(this, Object.freeze(defaults));

        return defaults;
    }

    static compoundedClassName() {
        const levels = [];

        for (let type = this; type && type !== HTMLObject; type = Object.getPrototypeOf(type)) {
            levels.push(type);
        }

        let first = null;
        levels.forEach((type, index) => {
            if (Object.hasOwn(type, 'className') && type.className !== null) first = index;
        });

        if (first === null) return '';

        const names = [];

        for (let index = first; index >= 0; index--) {
            const type = levels[index];
            names.push(Object.hasOwn(type, 'className') && type.className !== null ? type.className : type.name);
        }

        return names.join(' ');
    }

    addContent(item) { this.contents.push(item); }
    addContents(items) { items.forEach(item => this.addContent(item)); }

    toDOM() {
        if (this.#rendered) {
            throw new Error(this.constructor.name + ' produced output twice; build a fresh instance per output step.');
        }
        this.#rendered = true;

        const tagName = this.constructor.tagName;
        if (!tagName) throw new Error(this.constructor.name + ' has no tagName.');

        const element = document.createElement(tagName);

        if (this.id !== null) element.setAttribute('id', this.id);

        const inherited = this.constructor.compoundedClassName();
        const inheritedNames = inherited.split(' ').filter(Boolean);
        const added = String(this.class || '').split(' ')
            .filter(name => name && !inheritedNames.includes(name));
        const className = [...inheritedNames, ...added].join(' ');

        if (className) element.setAttribute('class', className);

        for (const [name, value] of Object.entries(this.attributes)) {
            if (value === null) {
                console.warn(this.constructor.name + ' left the "' + name + '" attribute null');
                continue;
            }
            element.setAttribute(name, value);
        }

        for (const item of this.contents) {
            let node = null;
            if (item instanceof HTMLObject) node = item.toDOM();
            else if (typeof item === 'string') node = document.createTextNode(item);
            else if (item instanceof Node) node = item;
            if (node) element.appendChild(node);
        }

        return element;
    }
}

export class Article extends HTMLObject { static tagName = 'article'; }
export class Div extends HTMLObject { static tagName = 'div'; }
export class Section extends HTMLObject { static tagName = 'section'; }
export class Figure extends HTMLObject { static tagName = 'figure'; }
export class Heading2 extends HTMLObject { static tagName = 'h2'; }
export class Heading3 extends HTMLObject { static tagName = 'h3'; }
export class ListItem extends HTMLObject { static tagName = 'li'; }
export class Paragraph extends HTMLObject { static tagName = 'p'; }
export class Span extends HTMLObject { static tagName = 'span'; }
export class Table extends HTMLObject { static tagName = 'table'; }
export class UnorderedList extends HTMLObject { static tagName = 'ul'; }
export class TableRow extends HTMLObject { static tagName = 'tr'; }
export class TableHeader extends HTMLObject { static tagName = 'th'; }
export class TableData extends HTMLObject { static tagName = 'td'; }

export class Button extends HTMLObject {
    static tagName = 'button';
    static properties = { type: 'button' };

    toDOM() {
        this.attributes.type = this.type;
        return super.toDOM();
    }
}

export class ButtonButton extends Button { static className = 'Button'; }

export class Anchor extends HTMLObject {
    static tagName = 'a';
    static properties = { href: null };

    constructor(href = null, text = null) {
        super(href !== null && typeof href === 'object' ? href : { href });
        if (text !== null) this.addContent(text);
    }

    toDOM() {
        if (this.href !== null) this.attributes.href = this.href;
        return super.toDOM();
    }
}

export class Image extends HTMLObject {
    static tagName = 'img';
    static properties = { src: null, alt: null };

    toDOM() {
        if (this.src !== null) this.attributes.src = this.src;
        if (this.alt !== null) this.attributes.alt = this.alt;
        return super.toDOM();
    }
}

export class Card extends Div { static className = 'Card'; }
