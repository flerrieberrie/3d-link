/**
 * Parameter Visibility System
 * 
 * Handles dynamic showing/hiding of parameter groups based on variation selection
 * Integrates with WooCommerce variations and PolygonJS parameters
 */

jQuery(document).ready(function($) {
    'use strict';
    
    const ParameterVisibility = {
        
        // Configuration
        data: null,
        currentVariationId: null,
        $form: null,
        initialized: false,
        
        /**
         * Initialize the system
         */
        init: function() {
            console.log('[TD Parameter Visibility] Initializing...');
            
            // Check if we have the required data
            if (typeof window.tdParameterVisibility === 'undefined') {
                console.log('[TD Parameter Visibility] No visibility data found');
                return;
            }
            
            this.data = window.tdParameterVisibility;
            this.$form = $('form.variations_form');
            
            if (!this.$form.length) {
                console.log('[TD Parameter Visibility] No variation form found');
                return;
            }
            
            this.bindEvents();
            this.setupInitialState();
            this.initialized = true;
            
            console.log('[TD Parameter Visibility] Initialized successfully', {
                variationMappings: Object.keys(this.data.variationMappings || {}).length,
                parameterGroups: Object.keys(this.data.parameterGroups || {}).length
            });
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // WooCommerce variation events
            this.$form.on('found_variation', (event, variation) => {
                this.onVariationFound(variation);
            });
            
            this.$form.on('reset_data', () => {
                this.onVariationReset();
            });
            
            // Custom attribute changes (for checkbox, button, radio displays)
            $(document).on('change', '.td-variation-checkbox, .td-variation-radio, .td-binary-checkbox-input', (e) => {
                this.onCustomVariationChange($(e.target));
            });
            
            $(document).on('click', '.td-variation-button', (e) => {
                this.onCustomVariationChange($(e.target));
            });
        },
        
        /**
         * Setup initial state - hide all grouped parameters
         */
        setupInitialState: function() {
            console.log('[TD Parameter Visibility] Setting up initial state');
            
            // Hide all grouped parameters initially
            this.hideAllGroupedParameters();
            
            // Check if a variation is already selected (for direct links)
            const currentVariationId = this.$form.find('input[name="variation_id"]').val();
            if (currentVariationId) {
                console.log('[TD Parameter Visibility] Found pre-selected variation:', currentVariationId);
                this.showParametersForVariation(currentVariationId);
            }
        },
        
        /**
         * Handle WooCommerce variation found event
         */
        onVariationFound: function(variation) {
            console.log('[TD Parameter Visibility] Variation found:', variation.variation_id);
            this.currentVariationId = variation.variation_id;
            this.showParametersForVariation(variation.variation_id);
        },
        
        /**
         * Handle WooCommerce variation reset event
         */
        onVariationReset: function() {
            console.log('[TD Parameter Visibility] Variation reset');
            this.currentVariationId = null;
            this.hideAllGroupedParameters();
        },
        
        /**
         * Handle custom variation control changes (checkboxes, buttons, etc.)
         */
        onCustomVariationChange: function($element) {
            // This method handles cases where custom display types are used
            // It works in conjunction with the variation display handler
            
            // Find the associated hidden select and trigger its change
            const $wrapper = $element.closest('.td-variation-wrapper');
            const $hiddenSelect = $wrapper.siblings('.td-variation-select');
            
            if ($hiddenSelect.length) {
                let value = '';
                
                if ($element.hasClass('td-variation-checkbox') && $element.is(':checked')) {
                    value = $element.val();
                } else if ($element.hasClass('td-variation-radio') && $element.is(':checked')) {
                    value = $element.val();
                } else if ($element.hasClass('td-variation-button')) {
                    value = $element.data('option');
                } else if ($element.hasClass('td-binary-checkbox-input')) {
                    value = $element.is(':checked') ? 
                        $element.data('checked-value') : 
                        $element.data('unchecked-value');
                }
                
                if (value) {
                    $hiddenSelect.val(value).trigger('change');
                }
            }
        },
        
        /**
         * Show parameters for a specific variation
         */
        showParametersForVariation: function(variationId) {
            if (!this.data.variationMappings[variationId]) {
                console.log('[TD Parameter Visibility] No mapping found for variation:', variationId);
                this.hideAllGroupedParameters();
                return;
            }
            
            const mapping = this.data.variationMappings[variationId];
            const groupsToShow = mapping.groups || [];
            
            console.log('[TD Parameter Visibility] Showing groups for variation:', variationId, groupsToShow);
            
            // Hide all grouped parameters first
            this.hideAllGroupedParameters();
            
            // Show the assigned groups
            groupsToShow.forEach(groupName => {
                this.showParameterGroup(groupName);
            });
            
            // Update any group visibility indicators
            this.updateGroupVisibilityIndicators(groupsToShow);
        },
        
        /**
         * Show a specific parameter group
         */
        showParameterGroup: function(groupName) {
            const selector = '.polygonjs-parameter[data-parameter-group="' + groupName + '"]';
            const $parameters = $(selector);
            
            console.log('[TD Parameter Visibility] Showing group:', groupName, '(' + $parameters.length + ' parameters)');
            
            $parameters.each(function() {
                const $param = $(this);
                
                // Show with animation
                $param.stop(true, true).fadeIn(300);
                
                // Re-enable form controls
                $param.find('input, select, textarea, button').prop('disabled', false);
                
                // Remove hidden class
                $param.removeClass('td-parameter-hidden');
                
                // Add visible class
                $param.addClass('td-parameter-visible');
            });
            
            // Show any group headers/sections
            this.showGroupSection(groupName);
        },
        
        /**
         * Hide a specific parameter group
         */
        hideParameterGroup: function(groupName) {
            const selector = '.polygonjs-parameter[data-parameter-group="' + groupName + '"]';
            const $parameters = $(selector);
            
            console.log('[TD Parameter Visibility] Hiding group:', groupName, '(' + $parameters.length + ' parameters)');
            
            $parameters.each(function() {
                const $param = $(this);
                
                // Hide with animation
                $param.stop(true, true).fadeOut(300);
                
                // Disable form controls to prevent submission
                $param.find('input, select, textarea, button').prop('disabled', true);
                
                // Add hidden class
                $param.addClass('td-parameter-hidden');
                
                // Remove visible class
                $param.removeClass('td-parameter-visible');
            });
            
            // Hide any group headers/sections
            this.hideGroupSection(groupName);
        },
        
        /**
         * Hide all grouped parameters
         */
        hideAllGroupedParameters: function() {
            console.log('[TD Parameter Visibility] Hiding all grouped parameters');
            
            // Hide all parameters that have a group assigned
            $('.polygonjs-parameter[data-parameter-group]').each(function() {
                const $param = $(this);
                
                // Hide immediately (no animation for initial state)
                $param.hide();
                
                // Disable form controls
                $param.find('input, select, textarea, button').prop('disabled', true);
                
                // Add hidden class
                $param.addClass('td-parameter-hidden');
                
                // Remove visible class
                $param.removeClass('td-parameter-visible');
            });
            
            // Hide all group sections
            $('.td-parameter-group-section').hide();
            
            // Show ungrouped parameters (those without data-parameter-group or with data-has-group="false")
            $('.polygonjs-parameter').filter(function() {
                const $this = $(this);
                return !$this.attr('data-parameter-group') || $this.attr('data-has-group') === 'false';
            }).each(function() {
                const $param = $(this);
                
                // Show ungrouped parameters
                $param.show();
                
                // Enable form controls
                $param.find('input, select, textarea, button').prop('disabled', false);
                
                // Remove hidden class
                $param.removeClass('td-parameter-hidden');
                
                // Add visible class for consistency
                $param.addClass('td-parameter-visible');
            });
        },
        
        /**
         * Show group section header
         */
        showGroupSection: function(groupName) {
            const $section = $('.td-parameter-group-section[data-group="' + groupName + '"]');
            if ($section.length) {
                $section.fadeIn(300);
            }
        },
        
        /**
         * Hide group section header
         */
        hideGroupSection: function(groupName) {
            const $section = $('.td-parameter-group-section[data-group="' + groupName + '"]');
            if ($section.length) {
                $section.fadeOut(300);
            }
        },
        
        /**
         * Update group visibility indicators
         */
        updateGroupVisibilityIndicators: function(visibleGroups) {
            // Update any UI indicators showing which groups are visible
            $('.td-group-indicator').removeClass('active');
            
            visibleGroups.forEach(groupName => {
                $('.td-group-indicator[data-group="' + groupName + '"]').addClass('active');
            });
        },
        
        /**
         * Get currently visible groups
         */
        getCurrentlyVisibleGroups: function() {
            const visibleGroups = [];
            
            $('.polygonjs-parameter[data-parameter-group].td-parameter-visible').each(function() {
                const groupName = $(this).attr('data-parameter-group');
                if (groupName && visibleGroups.indexOf(groupName) === -1) {
                    visibleGroups.push(groupName);
                }
            });
            
            return visibleGroups;
        },
        
        /**
         * Debug method to show current state
         */
        debugCurrentState: function() {
            console.log('[TD Parameter Visibility] Current State:', {
                initialized: this.initialized,
                currentVariationId: this.currentVariationId,
                visibleGroups: this.getCurrentlyVisibleGroups(),
                totalParameters: $('.polygonjs-parameter').length,
                groupedParameters: $('.polygonjs-parameter[data-parameter-group]').length,
                visibleParameters: $('.polygonjs-parameter.td-parameter-visible').length,
                hiddenParameters: $('.polygonjs-parameter.td-parameter-hidden').length
            });
        }
    };
    
    // Initialize when DOM is ready
    ParameterVisibility.init();
    
    // Add to global scope for debugging
    window.tdParameterVisibility = window.tdParameterVisibility || {};
    window.tdParameterVisibility.controller = ParameterVisibility;
    
    // Add debug command
    window.debugParameterVisibility = function() {
        ParameterVisibility.debugCurrentState();
    };
    
    console.log('[TD Parameter Visibility] Script loaded. Use debugParameterVisibility() in console for state info.');
});