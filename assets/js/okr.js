(function () {
    'use strict';

    const typeSelect = document.querySelector('[data-okr-source-type]');
    const sourceSelect = document.querySelector('[data-okr-source-key]');
    const sourcePanel = document.querySelector('[data-okr-source-panel]');
    const filterFields = Array.from(document.querySelectorAll('[data-source-filter]'));

    function syncSourceFields() {
        if (!typeSelect || !sourcePanel) return;
        const automatic = typeSelect.value === 'automatic';
        sourcePanel.hidden = !automatic;
        if (!automatic || !sourceSelect) return;
        const option = sourceSelect.options[sourceSelect.selectedIndex];
        const active = new Set((option?.dataset.filters || '').split(',').filter(Boolean));
        filterFields.forEach(function (field) {
            field.hidden = !active.has(field.dataset.sourceFilter);
        });
    }

    typeSelect?.addEventListener('change', syncSourceFields);
    sourceSelect?.addEventListener('change', syncSourceFields);
    syncSourceFields();

    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    const motion = window.SobhanMotion;
    if (!reduceMotion && motion?.animate) {
        document.querySelectorAll('[data-okr-reveal]').forEach(function (element, index) {
            motion.animate(
                element,
                { opacity: [0, 1], y: [8, 0] },
                { duration: 0.2, delay: Math.min(index * 0.035, 0.18), easing: 'ease-out' }
            );
        });
    }
})();
