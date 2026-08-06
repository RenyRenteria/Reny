import assert from 'node:assert/strict';
import test from 'node:test';

import {
    reportQueryForUrl,
    syncCustomDateInputs,
} from '../../resources/js/features/admin-reports.js';

test('default report range is reflected in the URL without a reload', () => {
    assert.equal(reportQueryForUrl('', 'preset=30d'), '?preset=30d');
    assert.equal(reportQueryForUrl('?preset=7d', 'preset=30d'), '?preset=7d');
});

test('custom date inputs are enabled only for the custom preset', () => {
    const inputs = [{ disabled: true }, { disabled: true }];
    let customChecked = false;
    const form = {
        querySelector() {
            return { checked: customChecked };
        },
        querySelectorAll() {
            return inputs;
        },
    };

    assert.equal(syncCustomDateInputs(form), false);
    assert.deepEqual(inputs.map((input) => input.disabled), [true, true]);

    customChecked = true;
    assert.equal(syncCustomDateInputs(form), true);
    assert.deepEqual(inputs.map((input) => input.disabled), [false, false]);
});
