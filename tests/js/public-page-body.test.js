import assert from 'node:assert/strict';
import test from 'node:test';

import { syncPublicPageBody } from '../../resources/js/features/public-page-body.js';

const createDocument = (className = '') => ({
    body: { className },
});

test('SPA navigation from Photos to Videos applies the Videos page class', () => {
    const currentDocument = createDocument();
    const nextDocument = createDocument('videos-page');

    syncPublicPageBody(nextDocument, currentDocument);

    assert.equal(currentDocument.body.className, 'videos-page');
});

test('SPA navigation away from Videos removes its page class', () => {
    const currentDocument = createDocument('videos-page');
    const nextDocument = createDocument();

    syncPublicPageBody(nextDocument, currentDocument);

    assert.equal(currentDocument.body.className, '');
});

test('SPA navigation preserves persistent music player state only', () => {
    const currentDocument = createDocument('videos-page has-modal-open has-music-player has-music-player-widget');
    const nextDocument = createDocument('home-page music-page');

    syncPublicPageBody(nextDocument, currentDocument);

    assert.equal(
        currentDocument.body.className,
        'home-page music-page has-music-player has-music-player-widget',
    );
});
