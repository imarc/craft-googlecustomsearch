$(function() {
    var $submit = $('input[type=submit]');
    var $testButton = $('<div />')
        .attr({
            id: 'test',
            class: 'btn'
        })
        .html('Test');
    var $testSpinner = $('<div />')
        .attr({
            id: 'test-spinner',
            class: 'spinner hidden'
        }); 

    $testButton.on('click', function(e) {
        if ($testButton.hasClass('sel')) {
            return;
        }

        $testButton.addClass('sel');
        $testSpinner.removeClass('hidden');
        
        $.get(
            Craft.getActionUrl('googleCustomSearch/connection/test'),
            $('form').serializeArray(),
            function(result) {
                $testButton.removeClass('sel');
                $testSpinner.addClass('hidden');

                if (result.success) {
                    Craft.cp.displayNotice(Craft.t('Successfully Connected!'));
                } else {
                    Craft.cp.displayError('Error: ' + result.error);
                }
            },
            'json'
        );
    });

    $submit.after($testButton, $testSpinner);
});