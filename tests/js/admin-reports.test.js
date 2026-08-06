import assert from 'node:assert/strict';
import test from 'node:test';

import {
    customRangeEnabled,
    initializeReportFilters,
    setCustomRangeState,
} from '../../resources/js/features/admin-reports.js';

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

test('report submit exposes real skeletons and busy states for each module', () => {
    const skeleton = { hidden: true };
    const classes = [];
    const attributes = {};
    const module = {
        classList: { add: (name) => classes.push(name) },
        querySelector: () => skeleton,
        setAttribute: (name, value) => {
            attributes[name] = value;
        },
    };
    let submit = null;
    const status = { textContent: '' };
    const range = { hidden: false, querySelectorAll: () => [] };
    const form = {
        classList: { add: () => {} },
        setAttribute: () => {},
        querySelector: (selector) => {
            if (selector.includes(':checked')) {
                return { value: '30d' };
            }

            return selector === '[data-report-filter-status]' ? status : range;
        },
        querySelectorAll: () => [],
        addEventListener: (name, callback) => {
            if (name === 'submit') {
                submit = callback;
            }
        },
    };
    const root = {
        querySelectorAll: (selector) => (selector === '[data-report-filter]' ? [form] : [module]),
    };

    initializeReportFilters(root);
    submit();

    assert.equal(skeleton.hidden, false);
    assert.deepEqual(classes, ['is-loading']);
    assert.equal(attributes['aria-busy'], 'true');
    assert.match(status.textContent, /Loading/);
});
