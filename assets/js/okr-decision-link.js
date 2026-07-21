(function () {
    'use strict';
    const form = document.querySelector('[data-okr-decision-form]');
    if (!form) return;
    const objective = form.querySelector('[data-okr-decision-objective]');
    const keyResult = form.querySelector('[data-okr-decision-kr]');
    const initiative = form.querySelector('[data-create-initiative]');
    const task = form.querySelector('[data-create-task]');

    function syncKeyResults() {
        const objectiveId = objective.value;
        Array.from(keyResult.options).forEach(function (option, index) {
            option.hidden = index > 0 && option.dataset.objectiveId !== objectiveId;
        });
        if (keyResult.selectedOptions[0]?.hidden) keyResult.value = '';
    }
    function syncCreationMode(event) {
        if (event?.target === initiative && initiative.checked) task.checked = false;
        if (event?.target === task && task.checked) initiative.checked = false;
    }
    objective.addEventListener('change', syncKeyResults);
    initiative.addEventListener('change', syncCreationMode);
    task.addEventListener('change', syncCreationMode);
    syncKeyResults();
})();
