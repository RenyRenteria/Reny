const publicPageHeadElements = [
    ['link[rel="canonical"]', 'href'],
    ['meta[name="description"]', 'content'],
    ['meta[property="og:type"]', 'content'],
    ['meta[property="og:title"]', 'content'],
    ['meta[property="og:description"]', 'content'],
    ['meta[property="og:url"]', 'content'],
    ['meta[property="og:image"]', 'content'],
    ['meta[name="twitter:card"]', 'content'],
    ['meta[name="twitter:title"]', 'content'],
    ['meta[name="twitter:description"]', 'content'],
    ['meta[name="twitter:image"]', 'content'],
];

export const syncPublicPageHead = (nextDocument, currentDocument = document) => {
    publicPageHeadElements.forEach(([selector, attribute]) => {
        const nextElement = nextDocument.head?.querySelector(selector);
        const currentElement = currentDocument.head?.querySelector(selector);

        if (!nextElement) {
            currentElement?.remove();
            return;
        }

        if (currentElement) {
            currentElement.setAttribute(attribute, nextElement.getAttribute(attribute) || '');
            return;
        }

        currentDocument.head?.append(nextElement.cloneNode(true));
    });
};
