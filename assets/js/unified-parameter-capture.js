/**
 * Unified Parameter Capture
 * 
 * Captures exact parameter values as they are applied to the 3D scene
 * and sends them to the unified sync system
 */
(function($) {
    'use strict';
    
    let captureInitialized = false;
    let sessionKey = null;
    
    // Storage for captured values
    const capturedData = {
        parameters: {},
        colors: {},
        actual_values: {},
        scene_state: {}
    };
    
    function logDebug(message, ...args) {
        if (window.location.search.includes('debug')) {
            console.log('[Unified Capture]', message, ...args);
        }
    }
    
    /**
     * Initialize parameter capture system
     */
    function initParameterCapture() {
        if (captureInitialized) return;
        
        logDebug('Initializing unified parameter capture system');
        
        // Hook into PolygonJS bridge ready event
        $(document).on('td_polygonjs_bridge_ready', function(event, scene) {
            logDebug('Bridge ready, setting up scene parameter capture');
            setupSceneCapture(scene);
        });
        
        // Hook into control changes
        setupControlCapture();
        
        // Hook into cart addition
        setupCartCapture();
        
        captureInitialized = true;
        logDebug('Parameter capture system initialized');
    }
    
    /**
     * Set up capture for scene parameter changes
     */
    function setupSceneCapture(scene) {
        if (!scene) return;
        
        logDebug('Setting up scene parameter capture');
        
        // Override scene.node method to intercept parameter sets
        const originalNode = scene.node.bind(scene);
        scene.node = function(path) {
            const node = originalNode(path);
            
            if (node && node.p) {
                // Wrap each parameter's set method
                Object.keys(node.p).forEach(paramName => {
                    const param = node.p[paramName];
                    if (param && typeof param.set === 'function' && !param._tdCaptureWrapped) {
                        const originalSet = param.set.bind(param);
                        
                        param.set = function(value) {
                            // Call original method
                            const result = originalSet(value);
                            
                            // Capture the actual value
                            captureParameterValue(path, paramName, value);
                            
                            return result;
                        };
                        
                        param._tdCaptureWrapped = true;
                    }
                });
            }
            
            return node;
        };
        
        logDebug('Scene parameter capture set up successfully');
    }
    
    /**
     * Set up capture for UI control changes
     */
    function setupControlCapture() {
        logDebug('Setting up control change capture');
        
        // Monitor all input changes
        $(document).on('input change', '.polygonjs-control, input, select, textarea', function(e) {
            const $control = $(this);
            const controlId = $control.attr('id') || $control.attr('name');
            
            if (!controlId) return;
            
            let value = $control.val();
            let controlType = 'text';
            
            // Determine control type and format value
            if ($control.is(':checkbox')) {
                value = $control.is(':checked');
                controlType = 'checkbox';
            } else if ($control.is('input[type="number"], input[type="range"]')) {
                value = parseFloat(value);
                controlType = 'number';
            } else if ($control.hasClass('color-radio')) {
                controlType = 'color';
                // Capture full color data
                captureColorFromControl($control);
                return; // Color handling is special
            }
            
            // Store the control value
            capturedData.parameters[controlId] = {
                value: value,
                control_type: controlType,
                element_id: controlId,
                timestamp: Date.now()
            };
            
            logDebug('Captured control value:', controlId, value, controlType);
        });
        
        // Monitor color selection specifically
        $(document).on('change', '.color-radio', function(e) {
            captureColorFromControl($(this));
        });
        
        logDebug('Control capture set up successfully');
    }
    
    /**
     * Capture color selection with full data
     */
    function captureColorFromControl($radio) {
        const controlId = $radio.attr('name');
        const colorKey = $radio.val();
        const $swatch = $radio.closest('.color-swatch');
        const colorName = $swatch.find('.swatch-name').text().trim();
        
        // Get color data from global objects
        let colorData = {
            name: colorName,
            key: colorKey,
            hex: '',
            rgb: null,
            color_id: '',
            control_id: controlId
        };
        
        // Try to get RGB and hex from global color options
        if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
            const globalColor = window.tdPolygonjs.colorOptions[colorKey];
            if (globalColor) {
                colorData.hex = globalColor.hex || '';
                colorData.rgb = globalColor.rgb || null;
                colorData.color_id = globalColor.id || '';
            }
        }
        
        // Store color data
        capturedData.colors[controlId] = colorData;
        
        logDebug('Captured color selection:', controlId, colorData);
        
        // Also store as parameter
        capturedData.parameters[controlId] = {
            value: colorName,
            control_type: 'color',
            element_id: controlId,
            timestamp: Date.now()
        };
    }
    
    /**
     * Capture actual parameter value applied to scene
     */
    function captureParameterValue(nodePath, paramName, value) {
        const key = nodePath.replace(/^\//, '').replace(/\//g, '-') + '-' + paramName;
        
        capturedData.actual_values[key] = {
            node_path: nodePath,
            param_name: paramName,
            value: value,
            timestamp: Date.now()
        };
        
        logDebug('Captured scene parameter:', key, nodePath, paramName, value);
    }
    
    /**
     * Set up cart addition capture
     */
    function setupCartCapture() {
        logDebug('Setting up cart capture');
        
        // Monitor add to cart form submission
        $(document).on('submit', 'form.cart', function(e) {
            logDebug('Cart form submitted, preparing to send captured data');
            
            // Get or generate session key
            if (!sessionKey) {
                sessionKey = generateSessionKey();
            }
            
            // Send captured data immediately
            sendCapturedData();
            
            // Also add session key to form
            const $form = $(this);
            if (!$form.find('input[name="td_sync_key"]').length) {
                $form.append('<input type="hidden" name="td_sync_key" value="' + sessionKey + '">');
            }
        });
        
        // Monitor WooCommerce AJAX cart events
        $(document.body).on('added_to_cart', function(e, fragments, cart_hash, $button) {
            logDebug('WooCommerce added_to_cart event fired');
            sendCapturedData();
        });
        
        logDebug('Cart capture set up successfully');
    }
    
    /**
     * Generate unique session key
     */
    function generateSessionKey() {
        const timestamp = Date.now();
        const random = Math.random().toString(36).substring(7);
        const userId = window.td_user_id || 0;
        return 'td_' + timestamp + '_' + random + '_' + userId;
    }
    
    /**
     * Send captured data to server
     */
    function sendCapturedData() {
        if (!sessionKey) {
            sessionKey = generateSessionKey();
        }
        
        // Add current scene state if available
        if (window.polyScene) {
            capturedData.scene_state = {
                scene_ready: true,
                timestamp: Date.now()
            };
        }
        
        logDebug('Sending captured data with session key:', sessionKey);
        logDebug('Captured data:', capturedData);
        
        // Send to unified sync system
        if (window.tdSendCapturedState) {
            window.tdSendCapturedState(sessionKey);
        } else {
            // Use WordPress localized variables
            const ajaxUrl = window.tdUnified ? window.tdUnified.ajax_url : '/wp-admin/admin-ajax.php';
            const productId = window.tdUnified ? window.tdUnified.product_id : 0;
            const nonce = window.tdUnified ? window.tdUnified.nonce : '';
            
            // Fallback AJAX call
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'td_store_unified_state',
                    product_id: productId,
                    session_key: sessionKey,
                    state_data: JSON.stringify(capturedData),
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        logDebug('✅ Captured data sent successfully');
                    } else {
                        console.warn('❌ Failed to send captured data:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Error sending captured data:', error);
                }
            });
        }
    }
    
    /**
     * Public API for manual triggering
     */
    window.tdUnifiedCapture = {
        capture: captureParameterValue,
        captureColor: captureColorFromControl,
        send: sendCapturedData,
        getData: function() { return capturedData; },
        getSessionKey: function() { return sessionKey; },
        setSessionKey: function(key) { sessionKey = key; }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        logDebug('DOM ready, initializing parameter capture');
        initParameterCapture();
    });
    
    // Also initialize immediately if DOM is already loaded
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        logDebug('DOM already loaded, initializing parameter capture immediately');
        initParameterCapture();
    }
    
})(jQuery);