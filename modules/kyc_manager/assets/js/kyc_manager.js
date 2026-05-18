/* KYC Manager — Admin JS */
'use strict';

$(function () {

    // Auto-highlight expiring items
    $('table').find('td .text-danger, td .text-warning').closest('tr')
        .css('background', function(){
            var $el = $(this).find('.text-danger');
            return $el.length ? '#fff5f5' : '#fffef5';
        });

});