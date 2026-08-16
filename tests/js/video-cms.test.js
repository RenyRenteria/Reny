import assert from 'node:assert/strict';
import test from 'node:test';

import { youtubeIdFromUrl } from '../../resources/js/features/video-cms.js';

test('video CMS accepts the supported YouTube URL formats', () => {
    assert.equal(youtubeIdFromUrl('https://www.youtube.com/watch?v=Ue8orNrHw9s'), 'Ue8orNrHw9s');
    assert.equal(youtubeIdFromUrl('https://youtu.be/Ue8orNrHw9s'), 'Ue8orNrHw9s');
    assert.equal(youtubeIdFromUrl('https://www.youtube.com/shorts/Ue8orNrHw9s'), 'Ue8orNrHw9s');
    assert.equal(youtubeIdFromUrl('https://www.youtube.com/embed/Ue8orNrHw9s'), 'Ue8orNrHw9s');
    assert.equal(youtubeIdFromUrl('https://example.com/video'), null);
    assert.equal(youtubeIdFromUrl('https://example.com/?v=Ue8orNrHw9s'), null);
});
