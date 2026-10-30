(function ($) {
    'use strict';

    var list = $('#pwl-options-list');
    list.sortable({ handle: '.pwl-drag', axis: 'y' });

    $('#pwl-add-option').on('click', function () {
        var key = 'pwl-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        list.append(
            '<div class="pwl-option-row">' +
                '<span class="dashicons dashicons-menu pwl-drag" aria-hidden="true"></span>' +
                '<input type="hidden" name="pwl_option_keys[]" value="' + key + '">' +
                '<textarea name="pwl_option_labels[]" rows="3" placeholder="Digite uma alternativa ou shortcode, por exemplo: [meu_widget]" class="widefat pwl-option-content"></textarea>' +
                '<button type="button" class="button-link-delete pwl-remove-option">Remover</button>' +
            '</div>'
        );
    });

    list.on('click', '.pwl-remove-option', function () {
        if (list.children().length <= 2) {
            window.alert('A enquete precisa ter pelo menos duas alternativas.');
            return;
        }
        $(this).closest('.pwl-option-row').remove();
    });
})(jQuery);

