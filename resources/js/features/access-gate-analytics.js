const accessGatesWithin = (root) => {
    if (!root) {
        return [];
    }

    return [
        ...(root.matches?.('.access-gate') ? [root] : []),
        ...root.querySelectorAll('.access-gate'),
    ];
};

const trackAccessGateViews = (root, track, fallbackSection = 'unknown') => {
    const gates = accessGatesWithin(root);

    gates.forEach((gate) => {
        track('permission_denied', {
            section: gate.dataset.section || fallbackSection,
            item_type: 'access_gate',
            item_id: gate.dataset.section || fallbackSection,
            result: 'blocked',
        });
    });

    return gates.length;
};

export { accessGatesWithin, trackAccessGateViews };
