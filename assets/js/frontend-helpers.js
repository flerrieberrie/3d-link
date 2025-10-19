/**
 * TD Link - Helpers JavaScript
 * Simple toggle functionality for helper controls
 * 
 * Note: The actual helper control connection is handled by polygonjs-viewer-helpers.js
 */

(function($) {
    'use strict';
    
    // Initialize helpers functionality
    $(document).ready(function() {
        initHelperToggle();
    });
    
    /**
     * Initialize helper toggle button only
     * The actual helper functionality is in polygonjs-viewer-helpers.js
     */
    function initHelperToggle() {
        const $helperContainer = $('.td-helper-controls');
        
        // If no helper controls present, do nothing
        if ($helperContainer.length === 0) {
            return;
        }
        
        // Wrap all controls in a content div for toggle functionality
        const $helperControls = $helperContainer.find('.slider-control, .number-control, .text-control, .checkbox-control, .color-control, .dropdown-control');
        const $helperDescription = $helperContainer.find('.helper-description');
        
        // Only proceed if we have actual controls
        if ($helperControls.length === 0) {
            return;
        }
        
        // Add content wrapper
        $helperControls.wrapAll('<div class="helper-content"></div>');
        $helperDescription.appendTo($helperContainer.find('.helper-content'));
        
        // Add toggle button
        const $toggleButton = $('<button class="td-helper-toggle" type="button">Show/Hide Helpers</button>');
        $helperContainer.prepend($toggleButton);
        
        // By default, show helpers
        let helpersVisible = true;
        
        // Toggle helper visibility
        $toggleButton.on('click', function() {
            helpersVisible = !helpersVisible;
            
            if (helpersVisible) {
                $helperContainer.removeClass('helpers-hidden');
                $toggleButton.text('Hide Helpers');
            } else {
                $helperContainer.addClass('helpers-hidden');
                $toggleButton.text('Show Helpers');
            }
        });
        
        // Add data attributes to PolygonJS controls
        $helperControls.each(function() {
            const $control = $(this);
            
            // Find the actual input element
            const $input = $control.find('input, select');
            
            if ($input.length > 0) {
                // Add helper attribute
                $input.attr('data-helper', 'true');
            }
        });
    }
    
})(jQuery);