import assert from 'node:assert/strict';
import test from 'node:test';

import {
    ONE_GIBIBYTE,
    isVideoFile,
    oversizedVideo,
    validateCommunityAttachments,
} from '../../resources/js/features/admin-community-media.js';

test('recognizes community videos by MIME type or approved extension', () => {
    assert.equal(isVideoFile({ name: 'clip.bin', type: 'video/mp4' }), true);
    assert.equal(isVideoFile({ name: 'clip.WEBM', type: '' }), true);
    assert.equal(isVideoFile({ name: 'cover.jpg', type: 'image/jpeg' }), false);
});

test('accepts a video at exactly 1 GB and rejects a larger video', () => {
    assert.equal(oversizedVideo([{ name: 'exact.mp4', type: 'video/mp4', size: ONE_GIBIBYTE }]), null);
    assert.equal(
        oversizedVideo([{ name: 'large.mp4', type: 'video/mp4', size: ONE_GIBIBYTE + 1 }])?.name,
        'large.mp4',
    );
});

test('sets a clear browser validation message for oversized videos', () => {
    let validationMessage = '';
    let reportCount = 0;
    const input = {
        dataset: { maxVideoBytes: String(ONE_GIBIBYTE) },
        files: [{ name: 'concert.mov', type: 'video/quicktime', size: ONE_GIBIBYTE + 1 }],
        setCustomValidity(message) {
            validationMessage = message;
        },
        reportValidity() {
            reportCount += 1;
        },
    };

    assert.equal(validateCommunityAttachments(input, { report: true }), false);
    assert.equal(validationMessage, '“concert.mov” is larger than the 1 GB maximum per video.');
    assert.equal(reportCount, 1);

    input.files = [{ name: 'concert.mov', type: 'video/quicktime', size: ONE_GIBIBYTE }];

    assert.equal(validateCommunityAttachments(input), true);
    assert.equal(validationMessage, '');
});
