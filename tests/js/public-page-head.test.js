import assert from 'node:assert/strict';
import test from 'node:test';

import { syncPublicPageHead } from '../../resources/js/features/public-page-head.js';

class FakeElement {
    constructor(selector, attribute, value, head = null) {
        this.selector = selector;
        this.attribute = attribute;
        this.attributes = new Map([[attribute, value]]);
        this.head = head;
    }

    cloneNode() {
        return new FakeElement(this.selector, this.attribute, this.getAttribute(this.attribute));
    }

    getAttribute(attribute) {
        return this.attributes.get(attribute) ?? null;
    }

    remove() {
        this.head?.elements.delete(this.selector);
    }

    setAttribute(attribute, value) {
        this.attributes.set(attribute, value);
    }
}

const createDocument = (metadata) => {
    const head = {
        elements: new Map(),
        querySelector(selector) {
            return this.elements.get(selector) ?? null;
        },
        append(element) {
            element.head = this;
            this.elements.set(element.selector, element);
        },
    };

    Object.entries(metadata).forEach(([selector, [attribute, value]]) => {
        head.append(new FakeElement(selector, attribute, value));
    });

    return { head };
};

test('SPA navigation replaces Home canonical, Open Graph, and Twitter metadata with Royals values', () => {
    const currentDocument = createDocument({
        'link[rel="canonical"]': ['href', 'https://renyrenteria.com/'],
        'meta[property="og:type"]': ['content', 'website'],
        'meta[property="og:title"]': ['content', 'Reny Renteria'],
        'meta[property="og:url"]': ['content', 'https://renyrenteria.com/'],
        'meta[name="twitter:card"]': ['content', 'summary_large_image'],
        'meta[name="twitter:title"]': ['content', 'Reny Renteria'],
    });
    const nextDocument = createDocument({
        'link[rel="canonical"]': ['href', 'https://renyrenteria.com/royals'],
        'meta[name="description"]': ['content', 'Join the Royals community'],
        'meta[property="og:type"]': ['content', 'website'],
        'meta[property="og:title"]': ['content', 'Royals | Reny Renteria'],
        'meta[property="og:description"]': ['content', 'Join the Royals community'],
        'meta[property="og:url"]': ['content', 'https://renyrenteria.com/royals'],
        'meta[property="og:image"]': ['content', 'https://renyrenteria.com/royals.jpg'],
        'meta[name="twitter:card"]': ['content', 'summary_large_image'],
        'meta[name="twitter:title"]': ['content', 'Royals | Reny Renteria'],
        'meta[name="twitter:description"]': ['content', 'Join the Royals community'],
        'meta[name="twitter:image"]': ['content', 'https://renyrenteria.com/royals.jpg'],
    });

    syncPublicPageHead(nextDocument, currentDocument);

    for (const [selector, nextElement] of nextDocument.head.elements) {
        const currentElement = currentDocument.head.querySelector(selector);

        assert.ok(currentElement, `${selector} should exist after navigation`);
        assert.equal(
            currentElement.getAttribute(nextElement.attribute),
            nextElement.getAttribute(nextElement.attribute),
            `${selector} should use the Royals value`,
        );
    }
});

test('SPA navigation removes optional metadata that is absent from the destination page', () => {
    const currentDocument = createDocument({
        'meta[property="og:image"]': ['content', 'https://renyrenteria.com/home.jpg'],
        'meta[name="twitter:image"]': ['content', 'https://renyrenteria.com/home.jpg'],
    });
    const nextDocument = createDocument({});

    syncPublicPageHead(nextDocument, currentDocument);

    assert.equal(currentDocument.head.querySelector('meta[property="og:image"]'), null);
    assert.equal(currentDocument.head.querySelector('meta[name="twitter:image"]'), null);
});

test('SPA navigation loads the DM Sans stylesheet required by the destination page', () => {
    const selector = 'link[rel="stylesheet"][href*="fonts.googleapis.com/css2?family=DM+Sans"]';
    const currentDocument = createDocument({});
    const nextDocument = createDocument({
        [selector]: ['href', 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap'],
    });

    syncPublicPageHead(nextDocument, currentDocument);

    assert.equal(
        currentDocument.head.querySelector(selector)?.getAttribute('href'),
        nextDocument.head.querySelector(selector)?.getAttribute('href'),
    );
});
