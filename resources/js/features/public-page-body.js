const persistentBodyClasses = new Set([
    'has-music-player',
    'has-music-player-widget',
]);

const bodyClasses = (body) => (body?.className || '')
    .split(/\s+/)
    .filter(Boolean);

export const syncPublicPageBody = (nextDocument, currentDocument = document) => {
    const currentBody = currentDocument.body;
    const nextBody = nextDocument.body;

    if (!currentBody || !nextBody) {
        return;
    }

    const persistentClasses = bodyClasses(currentBody)
        .filter((className) => persistentBodyClasses.has(className));

    currentBody.className = [...new Set([
        ...bodyClasses(nextBody),
        ...persistentClasses,
    ])].join(' ');
};
