(function () {
    'use strict';

    function countCharacters(value) {
        return Array.from ? Array.from(value).length : value.length;
    }

    function bindCounter(fieldId, outputId, limit) {
        var field = document.getElementById(fieldId);
        var output = document.getElementById(outputId);

        if (!field || !output) {
            return;
        }

        var update = function () {
            output.textContent = countCharacters(field.value) + '/' + limit;
        };

        field.addEventListener('input', update);
        update();
    }

    bindCounter('title', 'title-count', 30);
    bindCounter('content', 'content-count', 1000);

    var autoFocusTarget = document.querySelector('[data-auto-focus]');
    if (autoFocusTarget) {
        autoFocusTarget.focus();
    }
}());

