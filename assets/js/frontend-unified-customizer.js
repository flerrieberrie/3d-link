/**
 * Frontend Unified Customizer
 * Ensures smooth integration between WooCommerce and custom parameters
 */
jQuery(document).ready(function($) {
    
    const UnifiedCustomizer = {
        
        init: function() {
            this.bindEvents();
            this.ensureCompatibility();
        },
        
        bindEvents: function() {
            // Listen for parameter changes
            $(document).on('change', '.polygonjs-control', () => {
                this.updateAddToCartButton();
            });
            
            // Ensure add to cart works with custom parameters
            $('.single_add_to_cart_button').on('click', (e) => {
                if (!this.validateCustomization()) {
                    e.preventDefault();
                    this.showValidationMessage();
                }
            });
        },
        
        ensureCompatibility: function() {
            // Ensure proper form submission
            this.setupFormSubmission();
        },
        
        setupFormSubmission: function() {
            // Ensure custom parameters are submitted with the form
            const $form = $('.cart');
            
            $form.on('submit', function() {
                // Collect all custom parameter values
                const customData = {};
                
                $('.polygonjs-control').each(function() {
                    const $control = $(this);
                    const nodeId = $control.closest('[data-node-id]').data('node-id');
                    
                    if (nodeId) {
                        if ($control.is(':checkbox')) {
                            customData[nodeId] = $control.is(':checked') ? '1' : '0';
                        } else if ($control.is(':radio')) {
                            if ($control.is(':checked')) {
                                customData[nodeId] = $control.val();
                            }
                        } else {
                            customData[nodeId] = $control.val();
                        }
                    }
                });
                
                // Add custom data as hidden inputs
                $.each(customData, (key, value) => {
                    const inputName = 'td_custom[' + key + ']';
                    let $input = $form.find('input[name="' + inputName + '"]');
                    
                    if ($input.length === 0) {
                        $input = $('<input type="hidden" name="' + inputName + '">');
                        $form.append($input);
                    }
                    
                    $input.val(value);
                });
            });
        },
        
        
        updateAddToCartButton: function() {
            // Check if all required fields are filled
            const allFilled = this.validateCustomization();
            
            $('.single_add_to_cart_button').prop('disabled', !allFilled);
        },
        
        validateCustomization: function() {
            // Add any validation logic here
            return true; // For now, always valid
        },
        
        showValidationMessage: function() {
            // Show a message if validation fails
            if (!$('.td-validation-message').length) {
                const message = $('<div class="td-validation-message woocommerce-error">Please complete all customization options.</div>');
                $('.td-unified-customizer').prepend(message);
                
                setTimeout(() => {
                    message.fadeOut(() => message.remove());
                }, 3000);
            }
        }
    };
    
    // Initialize
    UnifiedCustomizer.init();
    
});