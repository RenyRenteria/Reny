import assert from 'node:assert/strict';
import test from 'node:test';

import { customRangeEnabled, setCustomRangeState } from '../../resources/js/features/admin-reports.js';

test('custom report ranges only enable date inputs for the custom preset', () => {
    assert.equal(customRangeEnabled('custom'), true);
    assert.equal(customRangeEnabled('30d'), false);

    const inputs = [{ disabled: false, required: false }, { disabled: false, required: false }];
    const range = {
        hidden: false,
        querySelectorAll: () => inputs,
    };
    const form = {
        querySelector: () => range,
    };

    setCustomRangeState(form, '7d');
    assert.equal(range.hidden, true);
    assert.deepEqual(inputs, [
        { disabled: true, required: false },
        { disabled: true, required: false },
    ]);

    setCustomRangeState(form, 'custom');
    assert.equal(range.hidden, false);
    assert.deepEqual(inputs, [
        { disabled: false, required: true },
        { disabled: false, required: true },
    ]);
});
