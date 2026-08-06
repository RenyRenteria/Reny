import assert from 'node:assert/strict';
import test from 'node:test';

import {
    ONE_GIBIBYTE,
    isVideoFile,
    oversizedVideo,
    validateCommunityAttachments,
} from '../../resources/js/features/admin-community-media.js';
import {
    communityUploadErrorMessage,
    distributeProgressAcrossFiles,
    formatUploadBytes,
} from '../../resources/js/features/admin-community-upload.js';

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

test('formats community upload sizes for readable per-file status', () => {
    assert.equal(formatUploadBytes(1536), '1.5 KB');
    assert.equal(formatUploadBytes(1024 ** 3), '1 GB');
});

test('distributes real request progress across multiple files in order', () => {
    assert.deepEqual(
        distributeProgressAcrossFiles([{ size: 100 }, { size: 300 }], 200, 400),
        [
            { percent: 100, state: 'uploaded' },
            { percent: 33, state: 'uploading' },
        ],
    );
});

test('shows validation and request-size failures clearly', () => {
    assert.equal(
        communityUploadErrorMessage({
            status: 422,
            responseText: JSON.stringify({ errors: { attachments: ['Choose a supported file.'] } }),
        }),
        'Choose a supported file.',
    );
    assert.match(
        communityUploadErrorMessage({ status: 413, responseText: '<html></html>' }),
        /request is too large/,
    );
    assert.match(
        communityUploadErrorMessage({ status: 419, responseText: '<html></html>' }),
        /session expired/,
    );
});
