(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : event.target.parentElement;
        if (!target) return;
        var removeButton = target.closest('[data-pwl-carrier-image-remove]');

        if (removeButton) {
            var removePicker = removeButton.closest('[data-pwl-carrier-picker]');
            if (!removePicker) return;
            var removeId = removePicker.querySelector('[data-pwl-carrier-image-id]');
            var removePreview = removePicker.querySelector('[data-pwl-carrier-preview-wrap]');
            if (removeId) removeId.value = '';
            if (removePreview) removePreview.hidden = true;
            removeButton.hidden = true;
            return;
        }
    });
}());
