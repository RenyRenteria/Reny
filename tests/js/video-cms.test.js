import assert from 'node:assert/strict';
import test from 'node:test';

import { serializeVideoOrder, youtubeIdFromUrl } from '../../resources/js/features/video-cms.js';

test('video CMS accepts the supported YouTube URL formats', () => {
    assert.equal(youtubeIdFromUrl('https://www.youtube.com/watch?v=Ue8orNrHw9s'), 'Ue8orNrHw9s');
    assert.equal(youtubeIdFromUrl('https://youtu.be/Ue8orNrHw9s'), 'Ue8orNrHw9s');
    assert.equal(youtubeIdFromUrl('https://www.youtube.com/shorts/Ue8orNrHw9s'), 'Ue8orNrHw9s');
    assert.equal(youtubeIdFromUrl('https://www.youtube.com/embed/Ue8orNrHw9s'), 'Ue8orNrHw9s');
    assert.equal(youtubeIdFromUrl('https://example.com/video'), null);
    assert.equal(youtubeIdFromUrl('https://example.com/?v=Ue8orNrHw9s'), null);
});

test('video CMS serializes the current DOM order immediately before submit', () => {
    const rows = ['3', '1', '2'].map((videoId) => ({ dataset: { videoId } }));
    const cms = {
        querySelectorAll(selector) {
            assert.equal(selector, '[data-video-sort-list] [data-video-row]');

            return rows;
        },
    };
    const staleInputs = [{ removed: false }, { removed: false }, { removed: false }];
    staleInputs.forEach((input) => {
        input.remove = () => {
            input.removed = true;
        };
    });
    const appended = [];
    const form = {
        querySelectorAll(selector) {
            assert.equal(selector, '[data-video-order-input]');

            return staleInputs;
        },
        append(input) {
            appended.push(input);
        },
    };
    const createElement = (tagName) => {
        assert.equal(tagName, 'input');

        return {
            attributes: {},
            setAttribute(name, value) {
                this.attributes[name] = value;
            },
        };
    };

    assert.deepEqual(serializeVideoOrder(cms, form, createElement), ['3', '1', '2']);
    assert.ok(staleInputs.every((input) => input.removed));
    assert.deepEqual(
        appended.map(({ type, name, value, attributes }) => ({ type, name, value, attributes })),
        [
            { type: 'hidden', name: 'video_ids[]', value: '3', attributes: { 'data-video-order-input': '' } },
            { type: 'hidden', name: 'video_ids[]', value: '1', attributes: { 'data-video-order-input': '' } },
            { type: 'hidden', name: 'video_ids[]', value: '2', attributes: { 'data-video-order-input': '' } },
        ],
    );
});
