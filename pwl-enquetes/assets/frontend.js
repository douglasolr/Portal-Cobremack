document.addEventListener('submit', function (event) {
    var form = event.target.closest('.pwl-poll__form');
    if (!form) return;

    event.preventDefault();
    var poll = form.closest('.pwl-poll');
    var feedback = poll.querySelector('.pwl-poll__feedback');
    var button = form.querySelector('.pwl-poll__submit');
    var checked = form.querySelectorAll('input[name="choices"], input[name="choices[]"]');
    var hasSelection = Array.prototype.some.call(checked, function (input) { return input.checked; });

    if (!hasSelection) {
        feedback.textContent = 'Selecione pelo menos uma alternativa.';
        feedback.className = 'pwl-poll__feedback pwl-poll__feedback--error';
        return;
    }

    button.disabled = true;
    feedback.textContent = PWLEnquetes.sending;
    feedback.className = 'pwl-poll__feedback';

    fetch(PWLEnquetes.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: new FormData(form)
    })
        .then(function (response) { return response.json(); })
        .then(function (response) {
            if (!response.success) {
                throw new Error(response.data && response.data.message ? response.data.message : PWLEnquetes.error);
            }
            feedback.textContent = response.data.message;
            feedback.className = 'pwl-poll__feedback pwl-poll__feedback--success';
            form.remove();
            if (response.data.showResults) {
                var results = poll.querySelector('.pwl-poll__results');
                results.innerHTML = response.data.resultsHtml;
                results.hidden = false;
            }
        })
        .catch(function (error) {
            feedback.textContent = error.message || PWLEnquetes.error;
            feedback.className = 'pwl-poll__feedback pwl-poll__feedback--error';
            button.disabled = false;
        });
});

