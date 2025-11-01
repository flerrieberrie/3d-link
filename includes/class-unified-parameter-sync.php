<?php
/**
 * Unified Parameter Synchronization
 * 
 * Single source of truth for parameter synchronization between frontend and backend
 * Ensures backend 3D viewer shows exactly what customer saw in frontend
 */

defined('ABSPATH') || exit;

class TD_Unified_Parameter_Sync {
    
    const SYNC_OPTION = 'td_unified_parameter_sync';
    
    /**
     * Store complete parameter state from frontend when adding to cart
     * 
     * @param int $product_id Product ID
     * @param string $session_key Unique session identifier
     * @param array $parameter_data Complete parameter state
     * @return bool Success status
     */
    public static function store_frontend_state($product_id, $session_key, $parameter_data) {
        if (!$product_id || !$session_key || !is_array($parameter_data)) {
            return false;
        }
        
        $sync_data = get_option(self::SYNC_OPTION, []);
        
        $state_data = [
            'product_id' => $product_id,
            'parameters' => $parameter_data['parameters'] ?? [],
            'colors' => $parameter_data['colors'] ?? [],
            'actual_values' => $parameter_data['actual_values'] ?? [],
            'scene_state' => $parameter_data['scene_state'] ?? [],
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        $sync_data[$session_key] = $state_data;
        
        // Clean old entries (older than 7 days)
        $cutoff = time() - (7 * 24 * 60 * 60);
        foreach ($sync_data as $key => $data) {
            if (isset($data['timestamp']) && $data['timestamp'] < $cutoff) {
                unset($sync_data[$key]);
            }
        }
        
        return update_option(self::SYNC_OPTION, $sync_data);
    }
    
    /**
     * Get stored frontend state for backend viewing
     * 
     * @param string $session_key Session identifier
     * @return array|false Parameter state or false
     */
    public static function get_frontend_state($session_key) {
        if (!$session_key) {
            return false;
        }
        
        $sync_data = get_option(self::SYNC_OPTION, []);
        return isset($sync_data[$session_key]) ? $sync_data[$session_key] : false;
    }
    
    /**
     * Generate a session key from cart item data
     * 
     * @param array $cart_item_data Cart item data
     * @return string Session key
     */
    public static function generate_session_key($cart_item_data) {
        $user_id = get_current_user_id();
        $session_id = WC()->session ? WC()->session->get_customer_id() : '';
        $timestamp = time();
        
        $key_data = [
            'user_id' => $user_id,
            'session_id' => $session_id,
            'timestamp' => $timestamp,
            'parameters' => $cart_item_data['td_parameters'] ?? [],
        ];
        
        return 'td_' . md5(serialize($key_data)) . '_' . $timestamp;
    }
    
    /**
     * Store parameter state during cart addition
     * 
     * @param array $cart_item_data Cart item data
     * @param int $product_id Product ID
     * @return string Session key
     */
    public static function store_cart_state($cart_item_data, $product_id) {
        $session_key = self::generate_session_key($cart_item_data);
        
        // Extract all relevant parameter data
        $parameter_data = [
            'parameters' => $cart_item_data['td_parameters'] ?? [],
            'colors' => $cart_item_data['td_color_data'] ?? [],
            'actual_values' => [],
            'scene_state' => []
        ];
        
        // Add session key to cart data for reference
        $cart_item_data['td_sync_key'] = $session_key;
        
        self::store_frontend_state($product_id, $session_key, $parameter_data);
        
        return $session_key;
    }
    
    /**
     * Generate JavaScript for capturing actual scene state
     * 
     * @param int $product_id Product ID
     * @return string JavaScript code
     */
    public static function generate_capture_script($product_id) {
        ob_start();
        ?>
        <script>
        // Unified Parameter Sync - Capture actual scene state
        (function() {
            let capturedState = {
                parameters: {},
                colors: {},
                actual_values: {},
                scene_state: {}
            };
            
            // Global function to store parameter value
            window.tdCaptureParameter = function(nodeId, paramName, value, controlType) {
                const key = nodeId.replace(/^\//, '').replace(/\//g, '-') + '-' + paramName;
                
                capturedState.actual_values[key] = {
                    node_path: nodeId,
                    param_name: paramName,
                    value: value,
                    control_type: controlType,
                    timestamp: Date.now()
                };
                
                console.log('📋 Captured parameter:', key, value);
            };
            
            // Global function to store color with full data
            window.tdCaptureColor = function(paramKey, colorData) {
                capturedState.colors[paramKey] = {
                    name: colorData.name,
                    hex: colorData.hex,
                    rgb: colorData.rgb,
                    color_id: colorData.color_id,
                    timestamp: Date.now()
                };
                
                console.log('🎨 Captured color:', paramKey, colorData);
            };
            
            // Global function to store scene state
            window.tdCaptureSceneState = function(state) {
                capturedState.scene_state = state;
                console.log('🎭 Captured scene state:', state);
            };
            
            // Function to send captured state to server
            window.tdSendCapturedState = function(sessionKey) {
                if (!sessionKey) {
                    console.warn('No session key provided for state capture');
                    return;
                }
                
                console.log('💾 Sending captured state with session key:', sessionKey);
                
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'td_store_unified_state',
                        product_id: <?php echo intval($product_id); ?>,
                        session_key: sessionKey,
                        state_data: JSON.stringify(capturedState),
                        nonce: '<?php echo wp_create_nonce('td_unified_sync'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('✅ State captured successfully');
                        } else {
                            console.warn('❌ Failed to capture state:', response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error capturing state:', error);
                    }
                });
            };
            
            // Expose state for debugging
            window.tdCapturedState = capturedState;
        })();
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('wp_ajax_td_store_unified_state', [__CLASS__, 'ajax_store_unified_state']);
        add_action('wp_ajax_nopriv_td_store_unified_state', [__CLASS__, 'ajax_store_unified_state']);
        add_action('wp_footer', [__CLASS__, 'inject_capture_script']);
    }
    
    /**
     * AJAX handler for storing unified state
     */
    public static function ajax_store_unified_state() {
        if (!wp_verify_nonce($_POST['nonce'], 'td_unified_sync')) {
            wp_die('Security check failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $session_key = sanitize_text_field($_POST['session_key']);
        $state_data = json_decode(stripslashes($_POST['state_data']), true);
        
        if (!$product_id || !$session_key || !is_array($state_data)) {
            wp_send_json_error('Invalid data');
            return;
        }
        
        $success = self::store_frontend_state($product_id, $session_key, $state_data);
        
        if ($success) {
            wp_send_json_success('State stored successfully');
        } else {
            wp_send_json_error('Failed to store state');
        }
    }
    
    /**
     * Inject capture script on product pages
     */
    public static function inject_capture_script() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        if (!$product || get_post_meta($product->get_id(), '_enable_polygonjs', true) !== 'yes') {
            return;
        }
        
        echo self::generate_capture_script($product->get_id());
    }
    
    /**
     * Generate backend viewer JavaScript with exact frontend state
     * 
     * @param string $session_key Session key
     * @return string JavaScript code
     */
    public static function generate_backend_viewer_script($session_key) {
        $state = self::get_frontend_state($session_key);
        
        if (!$state) {
            return '// No synchronized state found';
        }
        
        ob_start();
        ?>
        <script>
        // Backend viewer with exact frontend state
        (function() {
            const frontendState = <?php echo json_encode($state, JSON_PRETTY_PRINT); ?>;
            
            console.log('🔄 Loading frontend state in backend viewer:', frontendState);
            
            // Apply exact parameter values from frontend
            function applyFrontendState(scene) {
                if (!scene) return;
                
                // Apply actual values captured from frontend
                Object.entries(frontendState.actual_values || {}).forEach(([key, data]) => {
                    try {
                        const node = scene.node(data.node_path);
                        if (node && node.p && node.p[data.param_name]) {
                            console.log('📌 Applying frontend value:', data.node_path, data.param_name, data.value);
                            node.p[data.param_name].set(data.value);
                        }
                    } catch (error) {
                        console.warn('Failed to apply parameter:', key, error);
                    }
                });
                
                // Apply exact color values from frontend
                Object.entries(frontendState.colors || {}).forEach(([key, colorData]) => {
                    if (colorData.rgb && Array.isArray(colorData.rgb)) {
                        console.log('🎨 Applying frontend color:', key, colorData);
                        // Find corresponding parameter and apply RGB
                        // This ensures exact color matching
                        window.tdApplyColorFromFrontend && window.tdApplyColorFromFrontend(key, colorData);
                    }
                });
                
                console.log('✅ Frontend state applied to backend viewer');
            }
            
            // Wait for scene to be ready
            function waitForScene() {
                if (window.polyScene) {
                    applyFrontendState(window.polyScene);
                } else {
                    setTimeout(waitForScene, 100);
                }
            }
            
            // Start waiting for scene
            waitForScene();
            
            // Also listen for bridge ready event
            jQuery(document).on('td_polygonjs_bridge_ready', function(event, scene) {
                setTimeout(() => applyFrontendState(scene), 500);
            });
            
            // Expose state for debugging
            window.tdFrontendState = frontendState;
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

// Initialize the unified sync system
TD_Unified_Parameter_Sync::init();