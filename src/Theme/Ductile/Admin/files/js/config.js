/*global $ */
'use strict';

$(function() {
    $('#stickerslist').sortable({'cursor':'move'});
    $('#stickerslist tr').hover(function () {
        $(this).css({'cursor':'move'});
    }, function () {
        $(this).css({'cursor':'auto'});
    });
    $('#theme_config').submit(function() {
        var order=[];
        $('#stickerslist tr td input.position').each(function() {
            order.push(this.name.replace(/^order\[([^\]]+)\]$/,'$1'));
        });
        $('input[name=ds_order]')[0].value = order.join(',');
        return true;
    });
    $('#stickerslist tr td input.position').hide();
    $('#stickerslist tr td.handle').addClass('handler');
});
