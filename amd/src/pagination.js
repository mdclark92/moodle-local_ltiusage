define(['jquery'], function($) {
    'use strict';

    return {
        init: function() {
            console.log('Pagination init called');
            $('.pagination a').each(function() {
                console.log('Binding click to:', $(this).attr('href'));
            });
            $(document).off('click.pagination').on('click.pagination', '.pagination a', function(e) {
                console.log('Pagination link clicked');
                e.preventDefault();
                e.stopImmediatePropagation();
                e.stopPropagation();
                var url = $(this).attr('href');
                console.log('URL:', url);
                var tableContainer = $(this).closest('.lti-usage-table-group');
                var tableId = tableContainer.data('typeid');
                console.log('Table ID:', tableId);
                return false;
            });
        }
    };
});