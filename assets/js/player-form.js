jQuery(document).ready(function($) {
    $('.add-player').on('click', function() {
        var index = $('.player-fieldset').length;
        var newFieldset = $('.player-fieldset:first').clone();
        newFieldset.attr('data-index', index);
        newFieldset.find('input, textarea').each(function() {
            var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
            var id = $(this).attr('id').replace(/\d+/, index);
            $(this).attr('name', name).attr('id', id).val('');
        });
        newFieldset.find('label').each(function() {
            var forAttr = $(this).attr('for').replace(/\d+/, index);
            $(this).attr('for', forAttr);
        });
        $('.player-fields').append(newFieldset);
    });
});

