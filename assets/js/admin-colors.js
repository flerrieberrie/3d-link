/**
 * TD Link Colors Manager JavaScript
 * 
 * Handles the color management interface in the WordPress admin
 */
(function($) {
    $(document).ready(function() {
        initColorManager();
    });
    
    /**
     * Initialize the color manager
     */
    function initColorManager() {
        initColorPicker();
        setupFormHandling();
        setupColorActions();
        setupHexRgbSync();
    }
    
    /**
     * Initialize the WordPress color picker
     */
    function initColorPicker() {
        // Initialize WordPress color picker
        $('.color-field').wpColorPicker({
            change: function(event, ui) {
                const hexColor = ui.color.toString();
                
                // Update form field
                $('#color_hex').val(hexColor);
                
                // Update RGB values based on hex
                updateRgbFromHex(hexColor);
            }
        });
    }
    
    /**
     * Set up form submission handling
     */
    function setupFormHandling() {
        $('#td-color-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const formData = $form.serialize();
            
            // Disable submit button and show loading state
            const $submitButton = $form.find('.save-color');
            const originalText = $submitButton.text();
            $submitButton.text('Saving...').prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showNotice('success', response.data.message);
                        
                        // Reload page to show updated colors
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        showNotice('error', response.data.message);
                        $submitButton.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    showNotice('error', 'An error occurred while saving the color.');
                    $submitButton.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Cancel edit button
        $('.cancel-edit').on('click', function() {
            resetForm();
        });
    }
    
    /**
     * Set up color actions (edit, delete, stock toggle)
     */
    function setupColorActions() {
        // Edit color
        $('.edit-color').on('click', function() {
            const $colorItem = $(this).closest('.td-color-item');
            const colorId = $colorItem.data('id');
            const colorName = $colorItem.find('.color-details h3').text();
            const colorHex = $colorItem.find('.color-hex').text();
            const rgbText = $colorItem.find('.color-rgb').text().replace('RGB: ', '').split(', ');
            const colorRed = parseFloat(rgbText[0]);
            const colorGreen = parseFloat(rgbText[1]);
            const colorBlue = parseFloat(rgbText[2]);
            
            // Set form values
            $('#color_id').val(colorId);
            $('#color_name').val(colorName);
            $('#color_hex').wpColorPicker('color', colorHex);
            $('#color_red').val(colorRed);
            $('#color_green').val(colorGreen);
            $('#color_blue').val(colorBlue);
            
            // Update UI for edit mode
            $('#color-form-title').text('Edit Color');
            $('.save-color').text('Update Color');
            $('.cancel-edit').show();
            
            // Scroll to form
            $('html, body').animate({
                scrollTop: $('.td-color-form-container').offset().top - 50
            }, 500);
        });
        
        // Delete color
        $('.delete-color').on('click', function() {
            const $colorItem = $(this).closest('.td-color-item');
            const colorId = $colorItem.data('id');
            const colorName = $colorItem.find('.color-details h3').text();
            
            if (!confirm('Are you sure you want to delete the color "' + colorName + '"? This cannot be undone.')) {
                return;
            }
            
            // Send delete request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'td_delete_global_color',
                    nonce: $('#td-color-form input[name="nonce"]').val(),
                    id: colorId
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        $colorItem.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Show empty state if no colors left
                            if ($('.td-color-item').length === 0) {
                                $('.td-colors-list').html(
                                    '<div class="td-colors-empty">' +
                                    '<p>No colors defined yet. Add your first color using the form.</p>' +
                                    '</div>'
                                );
                            }
                        });
                    } else {
                        showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    showNotice('error', 'An error occurred while deleting the color.');
                }
            });
        });
        
        // Toggle stock status
        $('.toggle-stock').on('change', function() {
            const $checkbox = $(this);
            const $colorItem = $checkbox.closest('.td-color-item');
            const $stockToggle = $checkbox.closest('.color-stock-toggle');
            const colorId = $colorItem.data('id');
            const inStock = $checkbox.prop('checked');
            
            // Update the UI immediately for better UX
            if (inStock) {
                $colorItem.removeClass('out-of-stock');
                $stockToggle.removeClass('out-of-stock');
            } else {
                $colorItem.addClass('out-of-stock');
                $stockToggle.addClass('out-of-stock');
            }
            
            // Send update request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'td_save_global_colors',
                    nonce: $('#td-color-form input[name="nonce"]').val(),
                    id: colorId,
                    in_stock: inStock ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', inStock ? 
                            'Color marked as in stock.' : 
                            'Color marked as out of stock.');
                    } else {
                        // Reset checkbox and UI if failed
                        $checkbox.prop('checked', !inStock);
                        if (!inStock) {
                            $colorItem.removeClass('out-of-stock');
                            $stockToggle.removeClass('out-of-stock');
                        } else {
                            $colorItem.addClass('out-of-stock');
                            $stockToggle.addClass('out-of-stock');
                        }
                        showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    // Reset checkbox and UI if failed
                    $checkbox.prop('checked', !inStock);
                    if (!inStock) {
                        $colorItem.removeClass('out-of-stock');
                        $stockToggle.removeClass('out-of-stock');
                    } else {
                        $colorItem.addClass('out-of-stock');
                        $stockToggle.addClass('out-of-stock');
                    }
                    showNotice('error', 'An error occurred while updating stock status.');
                }
            });
        });
        
        // Initialize stock status classes on page load
        $('.toggle-stock').each(function() {
            const $checkbox = $(this);
            const $colorItem = $checkbox.closest('.td-color-item');
            const $stockToggle = $checkbox.closest('.color-stock-toggle');
            const inStock = $checkbox.prop('checked');
            
            if (!inStock) {
                $colorItem.addClass('out-of-stock');
                $stockToggle.addClass('out-of-stock');
            }
        });
    }
    
    /**
     * Set up synchronization between hex and RGB values
     */
    function setupHexRgbSync() {
        // Update RGB when hex changes manually
        $('#color_hex').on('input change', function() {
            const hexColor = $(this).val();
            updateRgbFromHex(hexColor);
        });
        
        // Update hex when RGB changes
        $('.rgb-field input').on('input change', function() {
            updateHexFromRgb();
        });
    }
    
    /**
     * Update RGB inputs based on hex color
     */
    function updateRgbFromHex(hexColor) {
        const rgb = hexToRgb(hexColor);
        
        if (rgb) {
            // Set values (0-1 range for PolygonJS)
            $('#color_red').val((rgb.r / 255).toFixed(2));
            $('#color_green').val((rgb.g / 255).toFixed(2));
            $('#color_blue').val((rgb.b / 255).toFixed(2));
        }
    }
    
    /**
     * Update hex input based on RGB values
     */
    function updateHexFromRgb() {
        const r = parseFloat($('#color_red').val());
        const g = parseFloat($('#color_green').val());
        const b = parseFloat($('#color_blue').val());
        
        // Convert from 0-1 range to 0-255
        const rInt = Math.min(255, Math.max(0, Math.round(r * 255)));
        const gInt = Math.min(255, Math.max(0, Math.round(g * 255)));
        const bInt = Math.min(255, Math.max(0, Math.round(b * 255)));
        
        // Create hex color
        const hexColor = '#' + 
            (rInt < 16 ? '0' : '') + rInt.toString(16) +
            (gInt < 16 ? '0' : '') + gInt.toString(16) +
            (bInt < 16 ? '0' : '') + bInt.toString(16);
        
        // Update color picker
        $('#color_hex').wpColorPicker('color', hexColor);
    }
    
    /**
     * Reset the form to add new color state
     */
    function resetForm() {
        $('#color_id').val('');
        $('#color_name').val('');
        $('#color_hex').val('#FF0000');
        $('#color_red').val('1');
        $('#color_green').val('0');
        $('#color_blue').val('0');
        
        // Update color picker
        $('#color_hex').wpColorPicker('color', '#FF0000');
        
        // Update form UI
        $('#color-form-title').text('Add New Color');
        $('.save-color').text('Save Color');
        $('.cancel-edit').hide();
    }
    
    /**
     * Show a notification message
     */
    function showNotice(type, message) {
        const $notice = $('.td-admin-notice.notice-' + type);
        $notice.find('p').text(message);
        $notice.slideDown();
        
        // Hide after 4 seconds
        setTimeout(function() {
            $notice.slideUp();
        }, 4000);
    }
    
    /**
     * Convert hex color to RGB object
     */
    function hexToRgb(hex) {
        // Remove # if present
        hex = hex.replace('#', '');
        
        // Parse hex values
        let r, g, b;
        
        if (hex.length === 3) {
            // Short form (e.g., #F00)
            r = parseInt(hex.charAt(0) + hex.charAt(0), 16);
            g = parseInt(hex.charAt(1) + hex.charAt(1), 16);
            b = parseInt(hex.charAt(2) + hex.charAt(2), 16);
        } else if (hex.length === 6) {
            // Long form (e.g., #FF0000)
            r = parseInt(hex.substring(0, 2), 16);
            g = parseInt(hex.substring(2, 4), 16);
            b = parseInt(hex.substring(4, 6), 16);
        } else {
            return null;
        }
        
        return { r: r, g: g, b: b };
    }
})(jQuery);