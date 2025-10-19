<?php
/**
 * Parameter Synchronization for 3D Link
 * 
 * Handles saving and retrieving actual PolygonJS node paths and parameter names
 * between frontend and admin (similar to color sync)
 */

defined('ABSPATH') || exit;

class TD_Parameter_Sync {
    /**
     * Option key for storing parameter sync data
     */
    const PARAMETER_SYNC_OPTION = 'td_parameter_sync_data';
    
    /**
     * Store actual PolygonJS node path and parameter name for a specific model parameter
     *
     * @param int $model_id The model ID
     * @param string $param_key The original parameter key/ID
     * @param string $actual_node_path The actual PolygonJS node path (e.g., "/sleutelhoes/ctrl")
     * @param string $actual_param_name The actual PolygonJS parameter name (e.g., "width")
     * @param mixed $value The parameter value
     * @param string $control_type The control type (color, number, text, etc.)
     * @return bool Success status
     */
    public static function store_parameter_mapping($model_id, $param_key, $actual_node_path, $actual_param_name, $value, $control_type = '') {
        if (!$model_id || !$param_key || !$actual_node_path || !$actual_param_name) {
            return false;
        }
        
        // Get existing data
        $sync_data = get_option(self::PARAMETER_SYNC_OPTION, []);
        
        // Initialize model data if not exists
        if (!isset($sync_data[$model_id])) {
            $sync_data[$model_id] = [];
        }
        
        // Store parameter mapping
        $sync_data[$model_id][$param_key] = [
            'actual_node_path' => $actual_node_path,
            'actual_param_name' => $actual_param_name,
            'value' => $value,
            'control_type' => $control_type,
            'updated_at' => time()
        ];
        
        // Save data
        update_option(self::PARAMETER_SYNC_OPTION, $sync_data);
        return true;
    }
    
    /**
     * Get stored parameter mapping for a model
     *
     * @param int $model_id The model ID
     * @param string $param_key The parameter key/identifier
     * @return array|false Parameter mapping or false if not found
     */
    public static function get_parameter_mapping($model_id, $param_key) {
        if (!$model_id || !$param_key) {
            return false;
        }
        
        // Get existing data
        $sync_data = get_option(self::PARAMETER_SYNC_OPTION, []);
        
        // Check if parameter mapping exists
        if (isset($sync_data[$model_id][$param_key])) {
            return $sync_data[$model_id][$param_key];
        }
        
        return false;
    }
    
    /**
     * Get all stored parameter mappings for a model
     *
     * @param int $model_id The model ID
     * @return array Array of parameter mappings
     */
    public static function get_model_parameter_mappings($model_id) {
        if (!$model_id) {
            return [];
        }
        
        // Get existing data
        $sync_data = get_option(self::PARAMETER_SYNC_OPTION, []);
        
        // Return model parameter mappings or empty array
        return isset($sync_data[$model_id]) ? $sync_data[$model_id] : [];
    }
    
    /**
     * Store all parameter mappings for a model at once (when order is placed)
     *
     * @param int $model_id The model ID
     * @param array $parameter_mappings Array of parameter mappings
     * @return bool Success status
     */
    public static function store_all_model_mappings($model_id, $parameter_mappings) {
        if (!$model_id || !is_array($parameter_mappings)) {
            return false;
        }
        
        // Get existing data
        $sync_data = get_option(self::PARAMETER_SYNC_OPTION, []);
        
        // Store all mappings for this model
        $sync_data[$model_id] = $parameter_mappings;
        
        // Save data
        update_option(self::PARAMETER_SYNC_OPTION, $sync_data);
        return true;
    }
    
    /**
     * Clear parameter mappings for a model
     *
     * @param int $model_id The model ID
     * @return bool Success status
     */
    public static function clear_model_mappings($model_id) {
        if (!$model_id) {
            return false;
        }
        
        // Get existing data
        $sync_data = get_option(self::PARAMETER_SYNC_OPTION, []);
        
        // Remove model data
        if (isset($sync_data[$model_id])) {
            unset($sync_data[$model_id]);
            update_option(self::PARAMETER_SYNC_OPTION, $sync_data);
        }
        
        return true;
    }
    
    /**
     * Add hooks and actions for parameter synchronization
     */
    public static function init() {
        // Hook into AJAX requests from the frontend to store parameter mappings
        add_action('wp_ajax_td_store_parameter_mappings', [__CLASS__, 'ajax_store_parameter_mappings']);
        add_action('wp_ajax_nopriv_td_store_parameter_mappings', [__CLASS__, 'ajax_store_parameter_mappings']);
        
        // Hook into admin model viewer to inject parameter sync data
        add_action('wp_footer', [__CLASS__, 'inject_frontend_parameter_sync_script']);
    }
    
    /**
     * AJAX handler for storing parameter mappings from frontend
     */
    public static function ajax_store_parameter_mappings() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'td_parameter_sync')) {
            wp_die('Security check failed');
        }
        
        $model_id = intval($_POST['model_id']);
        $parameter_mappings = json_decode(stripslashes($_POST['parameter_mappings']), true);
        
        if (!$model_id || !is_array($parameter_mappings)) {
            wp_send_json_error('Invalid data');
            return;
        }
        
        // Store the mappings
        $success = self::store_all_model_mappings($model_id, $parameter_mappings);
        
        if ($success) {
            wp_send_json_success('Parameter mappings stored successfully');
        } else {
            wp_send_json_error('Failed to store parameter mappings');
        }
    }
    
    /**
     * Inject JavaScript for parameter sync on frontend
     */
    public static function inject_frontend_parameter_sync_script() {
        // Only on product pages with PolygonJS enabled
        if (!is_product()) {
            return;
        }
        
        global $product;
        if (!$product || get_post_meta($product->get_id(), '_enable_polygonjs', true) !== 'yes') {
            return;
        }
        
        ?>
        <script>
        // TD Parameter Sync - Capture actual PolygonJS parameter calls from frontend
        (function() {
            // Store parameter mappings as they are applied
            let parameterMappings = {};
            
            // Hook into PolygonJS scene parameter changes by monitoring control events
            function captureParameterFromControl(elementId, nodePath, paramName, value) {
                // Create a clean parameter key from the element ID
                let paramKey = elementId;
                
                // Store the exact mapping
                parameterMappings[paramKey] = {
                    actual_node_path: nodePath,
                    actual_param_name: paramName,
                    value: value,
                    control_type: typeof value === 'number' ? 'number' : 
                                 typeof value === 'boolean' ? 'checkbox' : 'text',
                    updated_at: Math.floor(Date.now() / 1000)
                };
                
                console.log('📋 Captured parameter mapping from control:', paramKey, {
                    nodePath: nodePath,
                    paramName: paramName,
                    value: value
                });
            }
            
            // Monitor all input events on the page to capture parameter mappings
            document.addEventListener('input', function(event) {
                const element = event.target;
                if (!element.id) return;
                
                // Check if this looks like a PolygonJS control
                const elementId = element.id;
                
                // Pattern: sceneName-nodeName-paramName (e.g., "sleutelhoes-CTRL-width")
                const parts = elementId.split('-');
                if (parts.length >= 3) {
                    const sceneName = parts[0];
                    const value = element.type === 'number' ? parseFloat(element.value) : element.value;
                    
                    // Build the node path from the element ID pattern
                    let nodePath = '/' + parts.slice(0, -1).join('/');
                    let paramName = parts[parts.length - 1];
                    
                    // Capture the mapping
                    captureParameterFromControl(elementId, nodePath, paramName, value);
                }
            });
            
            // Also hook into any existing PolygonJS scene if available
            function hookIntoScene() {
                if (window.polyScene) {
                    console.log('🎯 Found PolygonJS scene, setting up parameter capture');
                    
                    // Override the node parameter set method
                    const originalNodeMethod = window.polyScene.node;
                    if (originalNodeMethod) {
                        window.polyScene.node = function(path) {
                            const node = originalNodeMethod.call(this, path);
                            
                            if (node && node.p) {
                                // Wrap parameter setters to capture calls
                                Object.keys(node.p).forEach(paramName => {
                                    const param = node.p[paramName];
                                    if (param && typeof param.set === 'function') {
                                        const originalSet = param.set.bind(param);
                                        param.set = function(value) {
                                            // Call original
                                            const result = originalSet(value);
                                            
                                            // Capture the mapping
                                            const elementId = path.substring(1).replace(/\//g, '-') + '-' + paramName;
                                            captureParameterFromControl(elementId, path, paramName, value);
                                            
                                            return result;
                                        };
                                    }
                                });
                            }
                            
                            return node;
                        };
                    }
                }
            }
            
            // Try to hook into scene when it's ready
            setTimeout(hookIntoScene, 1000);
            document.addEventListener('DOMContentLoaded', hookIntoScene);
            window.addEventListener('load', hookIntoScene);
            
            // Function to send parameter mappings to server (called when adding to cart)
            function sendParameterMappings(modelId) {
                if (!modelId || Object.keys(parameterMappings).length === 0) {
                    return;
                }
                
                console.log('💾 Sending parameter mappings for model:', modelId, parameterMappings);
                
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'td_store_parameter_mappings',
                        model_id: modelId,
                        parameter_mappings: JSON.stringify(parameterMappings),
                        nonce: '<?php echo wp_create_nonce('td_parameter_sync'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('✅ Parameter mappings stored successfully');
                        } else {
                            console.log('❌ Failed to store parameter mappings:', response.data);
                        }
                    },
                    error: function() {
                        console.log('❌ Error storing parameter mappings');
                    }
                });
            }
            
            // Hook into cart addition events
            jQuery(document).on('td_model_saved', function(event, modelData) {
                if (modelData && modelData.model_id) {
                    sendParameterMappings(modelData.model_id);
                }
            });
            
            // Also expose globally for manual triggering
            window.tdParameterSync = {
                sendMappings: sendParameterMappings,
                getMappings: function() { return parameterMappings; },
                clearMappings: function() { parameterMappings = {}; }
            };
        })();
        </script>
        <?php
    }
}