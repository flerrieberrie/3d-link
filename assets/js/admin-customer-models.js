/**
 * 3D-Link Customer Models Admin JavaScript
 */
(function($) {
    'use strict';

    // Initialize when the document is ready
    $(document).ready(function() {
        initModelsAdmin();
    });

    /**
     * Initialize the models admin page functionality
     */
    function initModelsAdmin() {
        // Parameters dialog
        initParametersDialog();
        
        // Pagination form submission
        $('#current-page-selector').on('keydown', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                var page = parseInt($(this).val());
                var totalPages = parseInt($('.total-pages').text());
                
                if (page > 0 && page <= totalPages) {
                    window.location.href = updateQueryStringParameter(window.location.href, 'paged', page);
                }
            }
        });
    }

    /**
     * Initialize the parameters dialog
     */
    function initParametersDialog() {
        // View parameters button
        $('.td-view-parameters').on('click', function() {
            var modelId = $(this).data('model-id');
            var parametersHtml = $('#td-parameters-' + modelId).html();
            
            $('#td-dialog-content').html(parametersHtml);
            $('#td-parameters-dialog').show();
        });
        
        // Close dialog
        $('.td-dialog-close').on('click', function() {
            $('#td-parameters-dialog').hide();
        });
        
        // Close dialog when clicking outside
        $(window).on('click', function(event) {
            if ($(event.target).is('#td-parameters-dialog')) {
                $('#td-parameters-dialog').hide();
            }
        });
    }

    /**
     * Update a URL query parameter or add it if it doesn't exist
     * 
     * @param {string} uri Current URL
     * @param {string} key Parameter name
     * @param {string} value Parameter value
     * @returns {string} Updated URL
     */
    function updateQueryStringParameter(uri, key, value) {
        var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
        var separator = uri.indexOf('?') !== -1 ? "&" : "?";
        
        if (uri.match(re)) {
            return uri.replace(re, '$1' + key + "=" + value + '$2');
        } else {
            return uri + separator + key + "=" + value;
        }
    }

})(jQuery);