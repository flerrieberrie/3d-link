/**
 * TD Link Admin Script
 * 
 * Handles parameter management in the WordPress admin
 */
(function($) {
    $(document).ready(function() {
        initParameterManager();
    });
    
    /**
     * Initialize the parameter manager functionality
     */
    function initParameterManager() {
        // Get parameter template
        const paramTemplate = window.parameterTemplate || '';
        const rgbColorTemplate = window.rgbColorTemplate || '';
        let paramCounter = $('.polygonjs-parameter').length;
        
        // Set initial collapse state
        $('.polygonjs-parameter').each(function() {
            $(this).addClass('collapsed');
        });
        
        // Toggle parameter content on header click
        $(document).on('click', '.parameter-header', function(e) {
            if (!$(e.target).closest('.parameter-actions').length) {
                const parameter = $(this).closest('.polygonjs-parameter');
                parameter.toggleClass('collapsed');
                updateToggleIcon(parameter);
            }
        });
        
        // Add parameter button
        $('.add-parameter').on('click', function() {
            const newParam = paramTemplate
                .replace(/\{\{INDEX\}\}/g, paramCounter)
                .replace(/\{\{NUMBER\}\}/g, paramCounter + 1);
            
            $(this).closest('.polygonjs-add-parameter').before(newParam);
            const $newParameter = $('.polygonjs-parameter').last();
            $newParameter.show().removeClass('collapsed');
            
            paramCounter++;
            
            // Reinitialize WooCommerce tooltips
            reinitializeTooltips();
            
            // Focus on HTML snippet field
            $newParameter.find('.html-snippet-field').focus();
            
            // Update color previews
            updateColorPreview($newParameter.find('.color-options-field'));
        });
        
        // Quick-Add RGB Color button
        $('.add-rgb-color').on('click', function() {
            // Generate a unique group ID for the RGB components
            const rgbGroupId = 'rgb-' + Date.now();
            
            // Insert the RGB color template
            let newParam = rgbColorTemplate
                .replace(/\{\{INDEX\}\}/g, paramCounter)
                .replace(/\{\{RGB_GROUP_ID\}\}/g, rgbGroupId);
            
            $(this).closest('.polygonjs-add-parameter').before(newParam);
            
            // Show and expand the main (red) component
            const $newParameters = $('.polygonjs-parameter').slice(-3);
            $newParameters.first().show().removeClass('collapsed');
            
            // Update internal counters
            paramCounter += 3;
            
            // Reinitialize WooCommerce tooltips
            reinitializeTooltips();
            
            // Focus on display name field
            $newParameters.first().find('.display-name-field').val('Product Color').focus().select();
            
            // Update color previews
            updateColorPreview($newParameters.first().find('.color-options-field'));
        });
        
        // Remove parameter button
        $(document).on('click', '.remove-parameter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parameter = $(this).closest('.polygonjs-parameter');
            
            // Use a single, simple confirmation message and removal action for all parameters
            if (confirm('Are you sure you want to remove this parameter?')) {
                parameter.slideUp(300, function() {
                    $(this).remove();
                    // Update parameter numbers
                    updateParameterNumbers();
                });
            }
            
            return false;
        });
        
        // Move up button
        $(document).on('click', '.move-up', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parameter = $(this).closest('.polygonjs-parameter');
            const rgbGroup = parameter.data('rgb-group');
            const isRgbComponent = parameter.data('rgb-component');
            
            if (isRgbComponent === 'r' && rgbGroup) {
                // Get all parameters in this RGB group
                const rgbComponents = $('.polygonjs-parameter[data-rgb-group="' + rgbGroup + '"]');
                
                // Find the previous parameter that is not part of this RGB group
                const prevParameter = rgbComponents.first().prev('.polygonjs-parameter:not([data-rgb-group="' + rgbGroup + '"])');
                
                if (prevParameter.length) {
                    // Move all components before the previous parameter
                    rgbComponents.detach().insertBefore(prevParameter);
                    updateIndices();
                }
            } else {
                const prev = parameter.prev('.polygonjs-parameter');
                if (prev.length) {
                    parameter.insertBefore(prev);
                    updateIndices();
                }
            }
        });
        
        // Move down button
        $(document).on('click', '.move-down', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parameter = $(this).closest('.polygonjs-parameter');
            const rgbGroup = parameter.data('rgb-group');
            const isRgbComponent = parameter.data('rgb-component');
            
            if (isRgbComponent === 'r' && rgbGroup) {
                // Get all parameters in this RGB group
                const rgbComponents = $('.polygonjs-parameter[data-rgb-group="' + rgbGroup + '"]');
                
                // Find the next parameter that is not part of this RGB group
                const nextParameter = rgbComponents.last().next('.polygonjs-parameter:not([data-rgb-group="' + rgbGroup + '"])');
                
                if (nextParameter.length) {
                    // Move all components after the next parameter
                    rgbComponents.detach().insertAfter(nextParameter);
                    updateIndices();
                }
            } else {
                const next = parameter.next('.polygonjs-parameter');
                if (next.length) {
                    parameter.insertAfter(next);
                    updateIndices();
                }
            }
        });
        
        // Duplicate parameter button
        $(document).on('click', '.duplicate-parameter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $originalParam = $(this).closest('.polygonjs-parameter');
            const rgbGroup = $originalParam.data('rgb-group');
            const isRgbComponent = $originalParam.data('rgb-component');
            
            if (isRgbComponent && rgbGroup) {
                // Handle RGB group duplication
                duplicateRgbGroup($originalParam, rgbGroup);
            } else {
                // Handle single parameter duplication
                window.duplicateSingleParameter($originalParam);
            }
        });
        
        // Toggle control settings based on control type
        $(document).on('change', '.control-type-field', function() {
            const controlType = $(this).val();
            const parameter = $(this).closest('.polygonjs-parameter');
            
            // Hide all setting panels
            parameter.find('.settings-number-slider, .settings-text, .settings-checkbox, .settings-color, .settings-hidden').hide();
            
            // Show appropriate panel
            switch (controlType) {
                case 'number':
                case 'slider':
                    parameter.find('.settings-number-slider').show();
                    break;
                case 'text':
                    parameter.find('.settings-text').show();
                    break;
                case 'checkbox':
                    parameter.find('.settings-checkbox').show();
                    break;
                case 'color':
                    parameter.find('.settings-color').show();
                    break;
                case 'hidden':
                    parameter.find('.settings-hidden').show();
                    break;
            }
            
            // Preserve values across control type changes
            handleDefaultValueChanges(parameter, controlType);
        });
        
        // Update values when inputs change
        $(document).on('input change', '.type-specific-field', function() {
            updateCombinedValue($(this));
        });
        
        // Parse HTML snippet
        $(document).on('change', '.html-snippet-field', function() {
            const snippet = $(this).val();
            const parameter = $(this).closest('.polygonjs-parameter');
            
            parseHtmlSnippet(snippet, parameter);
            
            // Update parameter header to show display name
            updateParameterHeader(parameter);
        });
        
        // Update parameter header when display name changes
        $(document).on('input', '.display-name-field', function() {
            updateParameterHeader($(this).closest('.polygonjs-parameter'));
        });
        
        // Handle color options field
        $(document).on('input change', '.color-options-field', function() {
            const $field = $(this);
            updateColorPreview($field);
            
            // If this is part of an RGB group, update the hidden components
            const $parameter = $field.closest('.polygonjs-parameter');
            const rgbGroup = $parameter.data('rgb-group');
            const isRgbComponent = $parameter.data('rgb-component');
            
            if (isRgbComponent === 'r' && rgbGroup) {
                // This is the main component of an RGB group
                // The color options should be used for all components
                $('.polygonjs-parameter[data-rgb-group="' + rgbGroup + '"]').each(function() {
                    if ($(this).data('rgb-component') !== 'r') {
                        $(this).find('.color-options-field').val($field.val());
                        updateColorPreview($(this).find('.color-options-field'));
                    }
                });
            }
        });
        
        // Expand all parameters
        $('.td-expand-all').on('click', function(e) {
            e.preventDefault();
            $('.polygonjs-parameter').removeClass('collapsed');
            $('.parameter-toggle .dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
        });
        
        // Collapse all parameters
        $('.td-collapse-all').on('click', function(e) {
            e.preventDefault();
            $('.polygonjs-parameter').addClass('collapsed');
            $('.parameter-toggle .dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
        });
        
        // NEW CODE: Set up the color selection interface
        setupColorSelectionInterface();
        
        // Reinitialize all tooltips on page load
        reinitializeTooltips();
    }
    
    /**
     * Set up color selection interface
     */
    function setupColorSelectionInterface() {
        console.log('Initializing color selection interface');
        
        // Toggle color checkboxes to update hidden field
        $(document).on('change', '.color-checkbox', function() {
            updateSelectedColorIds($(this).closest('.settings-color'));
        });
        
        // Select all colors
        $(document).on('click', '.select-all-colors', function(e) {
            e.preventDefault();
            const $container = $(this).closest('.settings-color');
            $container.find('.color-checkbox').prop('checked', true);
            updateSelectedColorIds($container);
        });
        
        // Deselect all colors
        $(document).on('click', '.deselect-all-colors', function(e) {
            e.preventDefault();
            const $container = $(this).closest('.settings-color');
            $container.find('.color-checkbox').prop('checked', false);
            updateSelectedColorIds($container);
        });
        
        // Fix initial color selection display
        $('.settings-color').each(function() {
            const $container = $(this);
            
            // This timeout ensures the DOM is fully loaded before we modify it
            setTimeout(function() {
                // First, log all color items for debugging
                console.log('Color items in container:', $container.find('.color-selection-item').length);
                
                // Cancel any existing styles that might be causing issues
                $container.find('.color-selection-grid').css({
                    'display': 'grid',
                    'grid-template-columns': 'repeat(auto-fill, minmax(100px, 150px))',
                    'grid-gap': '8px',
                    'position': 'static',
                    'visibility': 'visible',
                    'opacity': '1'
                });
                
                // Process each color item individually
                $container.find('.color-selection-item').each(function(index) {
                    const $item = $(this);
                    const colorName = $item.find('.color-name').text();
                    const colorId = $item.data('color-id');
                    
                    console.log(`Processing color ${index}: ${colorName} (ID: ${colorId})`);
                    
                    // Force each item to be visible with specific styling
                    // Use !important to override any conflicting styles
                    $item.attr('style', 
                        'display: flex !important; ' +
                        'flex-direction: column !important; ' +
                        'padding: 8px !important; ' +
                        'border: 1px solid #e5e5e5 !important; ' +
                        'border-radius: 4px !important; ' +
                        'background: #fff !important; ' +
                        'min-height: 50px !important; ' +
                        'position: relative !important; ' +
                        'box-sizing: border-box !important; ' +
                        'margin: 0 !important; ' +
                        'visibility: visible !important; ' + 
                        'opacity: 1 !important;'
                    );
                    
                    // Ensure the label and input are visible
                    $item.find('label').attr('style',
                        'display: flex !important; ' +
                        'align-items: center !important; ' +
                        'cursor: pointer !important; ' +
                        'width: 100% !important; ' +
                        'margin: 0 !important; ' +
                        'visibility: visible !important;'
                    );
                    
                    // Ensure the color swatch is visible
                    $item.find('.color-swatch').attr('style',
                        'display: block !important; ' +
                        'width: 20px !important; ' +
                        'height: 20px !important; ' +
                        'min-width: 20px !important; ' +
                        'border-radius: 50% !important; ' +
                        'margin-right: 8px !important; ' +
                        'border: 1px solid #ddd !important; ' +
                        'flex-shrink: 0 !important; ' +
                        'background-color: ' + $item.find('.color-swatch').css('background-color') + ' !important; ' +
                        'visibility: visible !important;'
                    );
                    
                    // Ensure out of stock items are properly styled
                    if ($item.hasClass('out-of-stock')) {
                        $item.attr('style', $item.attr('style') + 
                            'opacity: 0.85 !important; ' +
                            'background-color: rgba(214, 54, 56, 0.05) !important; ' +
                            'border-color: rgba(214, 54, 56, 0.2) !important;'
                        );
                        
                        // Add out-of-stock label if needed
                        if (!$item.find('.out-of-stock-label').length) {
                            $item.append(
                                '<span class="out-of-stock-label" style="' +
                                'display: block !important; ' +
                                'font-size: 9px !important; ' +
                                'color: #d63638 !important; ' +
                                'font-weight: 600 !important; ' +
                                'margin-top: 4px !important; ' +
                                'text-transform: uppercase !important; ' +
                                'padding-left: 28px !important; ' +
                                'visibility: visible !important;">' +
                                'Out of Stock</span>'
                            );
                        }
                    }
                    
                    // Log the status of this item
                    console.log(`Color ${index} (${colorName}) visibility enforced!`);
                });
            }, 100);
        });
        
        // Update the hidden field with selected color IDs
        function updateSelectedColorIds($container) {
            const selectedIds = [];
            $container.find('.color-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
            $container.find('.selected-color-ids').val(selectedIds.join(','));
            
            console.log('Updated selected color IDs:', selectedIds);
        }
    }
    /**
     * Reinitialize WooCommerce tooltips
     */
    function reinitializeTooltips() {
        setTimeout(function() {
            if (typeof woocommerce_admin_meta_boxes !== 'undefined' && 
                typeof woocommerce_admin_meta_boxes.init_tiptip === 'function') {
                woocommerce_admin_meta_boxes.init_tiptip();
            } else {
                // Fallback tooltip initialization
                $('.woocommerce-help-tip').tipTip({
                    'attribute': 'data-tip',
                    'fadeIn': 50,
                    'fadeOut': 50,
                    'delay': 200
                });
            }
        }, 500);
    }
    
    /**
     * Update parameter numbers after reordering
     */
    function updateParameterNumbers() {
        $('.polygonjs-parameter').each(function(index) {
            $(this).attr('data-index', index);
        });
    }
    
    /**
     * Update toggle icon based on collapsed state
     */
    function updateToggleIcon(parameter) {
        const icon = parameter.find('.parameter-toggle .dashicons');
        if (parameter.hasClass('collapsed')) {
            icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
        } else {
            icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
        }
    }
    
    /**
     * Update indices when parameters are reordered
     */
    function updateIndices() {
        // Store current group assignments before updating indices
        const groupAssignments = {};
        $('.polygonjs-parameter').each(function(index) {
            const $param = $(this);
            const $groupField = $param.find('.parameter-group-id');
            if ($groupField.length) {
                groupAssignments[index] = $groupField.val();
            }
        });
        
        $('.polygonjs-parameter').each(function(index) {
            $(this).attr('data-index', index);
            
            // Update all field names
            $(this).find('textarea, input, select').each(function() {
                const name = $(this).attr('name');
                if (name && name.includes('[')) {
                    $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                }
                
                // Also update IDs for type-specific fields
                const id = $(this).attr('id');
                if (id && id.includes('_poly_params_')) {
                    $(this).attr('id', id.replace(/_poly_params_\d+_/, '_poly_params_' + index + '_'));
                }
            });
            
            // Restore group assignment
            if (groupAssignments[index] !== undefined) {
                $(this).find('.parameter-group-id').val(groupAssignments[index]);
            }
            
            // Update parameter title
            updateParameterHeader($(this));
        });
    }
    
    /**
     * Handle default value preservation across control type changes
     */
    function handleDefaultValueChanges(parameter, newControlType) {
        const allValuesElem = parameter.find('.all-default-values');
        
        // Get all stored values
        let allValues = {};
        try {
            allValues = JSON.parse(allValuesElem.val()) || {};
        } catch(e) {
            allValues = {};
        }
        
        // Get current visible field
        const visibleField = parameter.find('.type-specific-field:visible');
        
        // Store current value
        if (visibleField.length > 0) {
            if (visibleField.is(':checkbox')) {
                const fieldType = getFieldType(visibleField);
                allValues[fieldType] = visibleField.is(':checked') ? 'yes' : 'no';
            } else {
                const fieldType = getFieldType(visibleField);
                allValues[fieldType] = visibleField.val() || '';
            }
            
            // Update hidden storage
            allValuesElem.val(JSON.stringify(allValues));
        }
        
        // Set value for new control type
        switch (newControlType) {
            case 'number':
            case 'slider':
                if (allValues.number !== undefined) {
                    parameter.find('.number-default-value').val(allValues.number);
                    parameter.find('.default-value-combined').val(allValues.number);
                }
                break;
            case 'text':
                if (allValues.text !== undefined) {
                    parameter.find('.text-default-value').val(allValues.text);
                    parameter.find('.default-value-combined').val(allValues.text);
                }
                break;
            case 'checkbox':
                if (allValues.checkbox !== undefined) {
                    parameter.find('.checkbox-default-value').prop('checked', allValues.checkbox === 'yes');
                    parameter.find('.default-value-combined').val(allValues.checkbox === 'yes' ? '1' : '0');
                }
                break;
            case 'color':
                if (allValues.color !== undefined) {
                    parameter.find('.color-default-value').val(allValues.color);
                    parameter.find('.default-value-combined').val(allValues.color);
                }
                break;
            case 'hidden':
                if (allValues.hidden !== undefined) {
                    parameter.find('.hidden-default-value').val(allValues.hidden);
                    parameter.find('.default-value-combined').val(allValues.hidden);
                }
                break;
        }
    }
    
    /**
     * Get field type from class name
     */
    function getFieldType(field) {
        const classes = field.attr('class') || '';
        
        if (classes.indexOf('number-default-value') !== -1) return 'number';
        else if (classes.indexOf('text-default-value') !== -1) return 'text';
        else if (classes.indexOf('checkbox-default-value') !== -1) return 'checkbox';
        else if (classes.indexOf('color-default-value') !== -1) return 'color';
        else if (classes.indexOf('hidden-default-value') !== -1) return 'hidden';
        
        return '';
    }
    
    /**
     * Update the combined hidden value
     */
    function updateCombinedValue(field) {
        const parameter = field.closest('.polygonjs-parameter');
        const combinedField = parameter.find('.default-value-combined');
        
        if (field.is(':checkbox')) {
            combinedField.val(field.is(':checked') ? '1' : '0');
        } else {
            combinedField.val(field.val());
        }
        
        // Also update the storage
        const allValuesElem = parameter.find('.all-default-values');
        let allValues = {};
        try {
            allValues = JSON.parse(allValuesElem.val()) || {};
        } catch(e) {
            allValues = {};
        }
        
        // Determine field type
        const fieldType = getFieldType(field);
        
        if (fieldType) {
            if (fieldType === 'checkbox') {
                allValues[fieldType] = field.is(':checked') ? 'yes' : 'no';
            } else {
                allValues[fieldType] = field.val() || '';
            }
            allValuesElem.val(JSON.stringify(allValues));
        }
    }
    
    /**
     * Parse HTML snippet to extract node attributes
     */
    function parseHtmlSnippet(snippet, parameter) {
        if (!snippet) return;
        
        try {
            // Extract the ID attribute from input tag
            const inputTag = snippet.match(/<input[^>]*>/);
            if (inputTag && inputTag[0]) {
                const idMatch = inputTag[0].match(/id=['"]([\w\-\.]+)['"]/);
                if (idMatch && idMatch[1]) {
                    const nodeId = idMatch[1];
                    parameter.find('.node-id-field').val(nodeId);
                    
                    // Extract other attributes
                    const minMatch = inputTag[0].match(/min=(['"]{0,1})([\d\.\-]+)(['"]{0,1})/);
                    const maxMatch = inputTag[0].match(/max=(['"]{0,1})([\d\.\-]+)(['"]{0,1})/);
                    const stepMatch = inputTag[0].match(/step=(['"]{0,1})([\d\.]+)(['"]{0,1})/);
                    const valueMatch = inputTag[0].match(/value=(['"]{0,1})([\w\d\.\-#]+)(['"]{0,1})/);
                    const typeMatch = inputTag[0].match(/type=['"]([\w]+)['"]/);
                    
                    // Only set control type if this is a new parameter (not yet configured)
                    const currentControlType = parameter.find('.control-type-field').val();
                    const isNewParameter = !currentControlType || currentControlType === '';
                    
                    if (isNewParameter && typeMatch && typeMatch[1]) {
                        const inputType = typeMatch[1];
                        let controlType = 'number';
                        
                        switch (inputType) {
                            case 'number':
                                controlType = 'number';
                                break;
                            case 'text':
                                controlType = 'text';
                                break;
                            case 'checkbox':
                                controlType = 'checkbox';
                                break;
                            case 'color':
                                controlType = 'color';
                                break;
                            case 'range':
                                controlType = 'slider';
                                break;
                            case 'hidden':
                                controlType = 'hidden';
                                break;
                        }
                        
                        // Set the control type dropdown only for new parameters
                        parameter.find('.control-type-field').val(controlType).trigger('change');
                    }
                    
                    if (minMatch && minMatch[2]) {
                        parameter.find('input[name$="[min]"]').val(minMatch[2]);
                    }
                    
                    if (maxMatch && maxMatch[2]) {
                        parameter.find('input[name$="[max]"]').val(maxMatch[2]);
                    }
                    
                    if (stepMatch && stepMatch[2]) {
                        parameter.find('input[name$="[step]"]').val(stepMatch[2]);
                    }
                    
                    if (valueMatch && valueMatch[2]) {
                        const value = valueMatch[2];
                        
                        // Update combined field
                        parameter.find('.default-value-combined').val(value);
                        
                        // Also update type-specific field
                        const controlType = parameter.find('.control-type-field').val();
                        switch (controlType) {
                            case 'number':
                            case 'slider':
                                parameter.find('.number-default-value').val(value);
                                break;
                            case 'text':
                                parameter.find('.text-default-value').val(value);
                                break;
                            case 'checkbox':
                                const isChecked = value === '1' || value === 'true' || value === 'yes';
                                parameter.find('.checkbox-default-value').prop('checked', isChecked);
                                break;
                            case 'color':
                                parameter.find('.color-default-value').val(value);
                                break;
                            case 'hidden':
                                parameter.find('.hidden-default-value').val(value);
                                break;
                        }
                    }
                    
                    // Suggest a display name based on the node ID (only for new parameters)
                    const displayNameField = parameter.find('input[name$="[display_name]"]');
                    if (!displayNameField.val()) {
                        let displayName = generateDisplayName(nodeId);
                        
                        // Special handling for RGB components
                        const isRgbComponent = parameter.find('input[name$="[is_rgb_component]"]').val();
                        if (isRgbComponent) {
                            // For RGB components, prefix with color channel
                            if (isRgbComponent === 'r') {
                                displayName = 'Color (Red Channel)';
                            } else if (isRgbComponent === 'g') {
                                displayName = 'Color (Green Channel)';
                            } else if (isRgbComponent === 'b') {
                                displayName = 'Color (Blue Channel)';
                            }
                        }
                        
                        displayNameField.val(displayName);
                    }
                }
            }
        } catch (error) {
            console.error('Error parsing HTML snippet:', error);
        }
    }
    
    /**
     * Generate a display name from node ID
     */
    function generateDisplayName(nodeId) {
        // Special cases for RGB color components
        if (nodeId.includes('colorr')) return 'Color (Red Channel)';
        if (nodeId.includes('colorg')) return 'Color (Green Channel)';
        if (nodeId.includes('colorb')) return 'Color (Blue Channel)';
        
        let displayName = nodeId.split('-').pop();
        displayName = displayName.charAt(0).toUpperCase() + displayName.slice(1);
        
        // Make it more human readable
        displayName = displayName.replace(/([A-Z])/g, ' $1').trim();
        
        // Try to make it even more human readable
        const nameMap = {
            'Points Count': 'Number of Shelves',
            'Text': 'Custom Text',
            'Size Y': 'Height',
            'Size X': 'Width',
            'Size Z': 'Depth',
            'Rotation': 'Rotation',
            'Scale': 'Scale',
            'Color': 'Color'
        };
        
        return nameMap[displayName] || displayName;
    }
    
    /**
     * Update parameter header with display name
     */
    function updateParameterHeader(parameter) {
        const displayName = parameter.find('.display-name-field').val();
        
        if (displayName) {
            parameter.find('.parameter-name').text(displayName);
        } else {
            // Just use node ID if no display name
            const nodeId = parameter.find('.node-id-field').val();
            if (nodeId) {
                parameter.find('.parameter-name').text(nodeId);
            } else {
                parameter.find('.parameter-name').text('Parameter');
            }
        }
    }
    
    /**
     * Update color preview based on color options
     */
    function updateColorPreview(field) {
        const parameter = field.closest('.polygonjs-parameter');
        const previewContainer = parameter.find('.color-preview');
        const colorOptions = field.val();
        
        // Clear previous preview
        previewContainer.empty();
        
        if (!colorOptions) return;
        
        // Parse color options
        const colors = [];
        colorOptions.split('|').forEach(function(item) {
            const parts = item.trim().split(';');
            if (parts.length === 2) {
                colors.push({
                    name: parts[0],
                    hex: parts[1]
                });
            }
        });
        
        // Create preview swatches
        colors.forEach(function(color) {
            const swatch = $('<div class="color-preview-swatch"></div>')
                .css('background-color', color.hex)
                .attr('data-color', color.name);
            
            previewContainer.append(swatch);
        });
    }
    
    /**
     * Duplicate a single parameter
     */
    window.duplicateSingleParameter = function($originalParam) {
        // Store the original group ID before cloning
        const originalGroupId = $originalParam.find('.parameter-group-id').val() || '';
        
        const $clone = $originalParam.clone(true, true);
        
        // Clear unique identifiers and increment counters
        const newIndex = $('.polygonjs-parameter').length;
        updateParameterIndexes($clone, newIndex);
        
        // Restore the group ID after index updates
        $clone.find('.parameter-group-id').val(originalGroupId);
        if (originalGroupId) {
            $clone.attr('data-group-id', originalGroupId);
        }
        
        // Update display name to indicate it's a copy
        const $displayNameField = $clone.find('.display-name-field');
        const originalName = $displayNameField.val();
        if (originalName) {
            $displayNameField.val(originalName + ' (Copy)');
        }
        
        // Clear node ID to force manual entry
        $clone.find('.node-id-field').val('');
        
        // Insert after the original parameter
        $originalParam.after($clone);
        
        // Show and expand the cloned parameter
        $clone.show().removeClass('collapsed');
        updateToggleIcon($clone);
        
        // Update parameter header
        updateParameterHeader($clone);
        
        // Focus on the HTML snippet field
        $clone.find('.html-snippet-field').focus();
        
        // Reinitialize tooltips and update indexes
        reinitializeTooltips();
        updateIndices();
        
        // Trigger parameter-added event for groups system
        $(document).trigger('parameter-added');
        
        return $clone;
    }
    
    /**
     * Duplicate an entire RGB group
     */
    function duplicateRgbGroup($originalParam, rgbGroup) {
        // Find all parameters in the RGB group
        const $rgbComponents = $('.polygonjs-parameter[data-rgb-group="' + rgbGroup + '"]');
        
        // Generate new RGB group ID
        const newRgbGroupId = 'rgb-' + Date.now();
        
        // Clone each component
        const $clonedComponents = [];
        $rgbComponents.each(function(index) {
            const $clone = $(this).clone(true, true);
            const newIndex = $('.polygonjs-parameter').length + index;
            
            // Update indexes
            updateParameterIndexes($clone, newIndex);
            
            // Update RGB group ID
            $clone.attr('data-rgb-group', newRgbGroupId);
            $clone.find('input[name$="[rgb_group]"]').val(newRgbGroupId);
            
            // Update display name for the main component (red)
            if (index === 0) {
                const $displayNameField = $clone.find('.display-name-field');
                const originalName = $displayNameField.val();
                if (originalName) {
                    $displayNameField.val(originalName + ' (Copy)');
                }
            }
            
            // Clear node IDs to force manual entry
            $clone.find('.node-id-field').val('');
            
            $clonedComponents.push($clone);
        });
        
        // Insert all cloned components after the last original component
        const $lastComponent = $rgbComponents.last();
        $clonedComponents.forEach($clone => {
            $lastComponent.after($clone);
        });
        
        // Show and expand the main (red) component
        if ($clonedComponents.length > 0) {
            $clonedComponents[0].show().removeClass('collapsed');
            updateToggleIcon($clonedComponents[0]);
            updateParameterHeader($clonedComponents[0]);
            
            // Focus on the HTML snippet field of the main component
            $clonedComponents[0].find('.html-snippet-field').focus();
        }
        
        // Reinitialize tooltips and update indexes
        reinitializeTooltips();
        updateIndices();
        
        // Trigger parameter-added event for groups system
        $(document).trigger('parameter-added');
    }
    
    /**
     * Update parameter indexes and form field names
     */
    function updateParameterIndexes($param, newIndex) {
        // Update data-index attribute
        $param.attr('data-index', newIndex);
        
        // Update all form field names and IDs
        $param.find('input, select, textarea').each(function() {
            const $field = $(this);
            
            // Update name attribute
            const name = $field.attr('name');
            if (name) {
                $field.attr('name', name.replace(/\[(\d+)\]/, '[' + newIndex + ']'));
            }
            
            // Update ID attribute
            const id = $field.attr('id');
            if (id) {
                $field.attr('id', id.replace(/(_|-)(\d+)(_|-)/g, '$1' + newIndex + '$3'));
            }
        });
        
        // Update labels
        $param.find('label').each(function() {
            const $label = $(this);
            const forAttr = $label.attr('for');
            if (forAttr) {
                $label.attr('for', forAttr.replace(/(_|-)(\d+)(_|-)/g, '$1' + newIndex + '$3'));
            }
        });
    }
})(jQuery);