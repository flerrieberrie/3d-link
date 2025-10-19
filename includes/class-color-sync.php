<?php
/**
 * Color Synchronization for 3D Link
 * 
 * Handles saving and retrieving color values between frontend and admin
 */

defined('ABSPATH') || exit;

class TD_Color_Sync {
    /**
     * Option key for storing color sync data
     */
    const COLOR_SYNC_OPTION = 'td_color_sync_data';
    
    /**
     * Store color RGB values for a specific product configuration
     *
     * @param int $product_id The product ID
     * @param string $color_key The color key/identifier
     * @param array $rgb_values RGB values in 0-1 range
     * @param string $color_name Optional color name
     * @return bool Success status
     */
    public static function store_color_values($product_id, $color_key, $rgb_values, $color_name = '') {
        if (!$product_id || !$color_key || !is_array($rgb_values) || count($rgb_values) !== 3) {
            return false;
        }
        
        // Get existing data
        $sync_data = get_option(self::COLOR_SYNC_OPTION, []);
        
        // Initialize product data if not exists
        if (!isset($sync_data[$product_id])) {
            $sync_data[$product_id] = [];
        }
        
        // Store color values
        $sync_data[$product_id][$color_key] = [
            'rgb' => $rgb_values,
            'name' => $color_name,
            'updated_at' => time()
        ];
        
        // Save data
        update_option(self::COLOR_SYNC_OPTION, $sync_data);
        return true;
    }
    
    /**
     * Get stored color RGB values
     *
     * @param int $product_id The product ID
     * @param string $color_key The color key/identifier
     * @return array|false RGB values or false if not found
     */
    public static function get_color_values($product_id, $color_key) {
        if (!$product_id || !$color_key) {
            return false;
        }
        
        // Get existing data
        $sync_data = get_option(self::COLOR_SYNC_OPTION, []);
        
        // Check if color data exists
        if (isset($sync_data[$product_id][$color_key])) {
            return $sync_data[$product_id][$color_key]['rgb'];
        }
        
        return false;
    }
    
    /**
     * Get all stored colors for a product
     *
     * @param int $product_id The product ID
     * @return array Array of color data
     */
    public static function get_product_colors($product_id) {
        if (!$product_id) {
            return [];
        }
        
        // Get existing data
        $sync_data = get_option(self::COLOR_SYNC_OPTION, []);
        
        // Return product colors or empty array
        return isset($sync_data[$product_id]) ? $sync_data[$product_id] : [];
    }
    
    /**
     * Add hooks and actions for color synchronization
     */
    public static function init() {
        // Hook into AJAX requests from the frontend to store colors
        add_action('wp_ajax_td_store_color_values', [__CLASS__, 'ajax_store_color_values']);
        add_action('wp_ajax_nopriv_td_store_color_values', [__CLASS__, 'ajax_store_color_values']);
        
        // Hook into admin model viewer to inject color data
        add_action('wp_footer', [__CLASS__, 'inject_frontend_color_sync_script']);
    }
    
    /**
     * AJAX handler for storing color values
     */
    public static function ajax_store_color_values() {
        // Verify request
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $color_key = isset($_POST['color_key']) ? sanitize_text_field($_POST['color_key']) : '';
        $color_name = isset($_POST['color_name']) ? sanitize_text_field($_POST['color_name']) : '';
        $rgb_values = isset($_POST['rgb_values']) && is_array($_POST['rgb_values']) ? 
                      array_map('floatval', $_POST['rgb_values']) : null;
        
        if (!$product_id || !$color_key || !$rgb_values || count($rgb_values) !== 3) {
            wp_send_json_error(['message' => 'Invalid color data']);
            return;
        }
        
        // Store color values
        $result = self::store_color_values($product_id, $color_key, $rgb_values, $color_name);
        
        if ($result) {
            wp_send_json_success(['message' => 'Color values stored']);
        } else {
            wp_send_json_error(['message' => 'Failed to store color values']);
        }
    }
    
    /**
     * Inject frontend script to sync color values
     */
    public static function inject_frontend_color_sync_script() {
        // Only inject on product pages with customizer
        if (!is_product() || !function_exists('is_product') || !is_product()) {
            return;
        }
        
        global $product;
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        
        ?>
        <script>
        (function($) {
            $(document).ready(function() {
                // Wait for the customizer to initialize
                $(document).on('td_polygonjs_bridge_ready', function() {
                    console.log('[Color Sync] Bridge ready, initializing color sync');
                    initColorSync();
                });
                
                function initColorSync() {
                    // Monitor color selection changes
                    $('.color-radio').on('change', function() {
                        const $radio = $(this);
                        const $swatch = $radio.closest('.color-swatch');
                        const colorKey = $radio.val();
                        const colorName = $swatch.find('.swatch-name').text();
                        
                        // Skip out-of-stock colors
                        if ($swatch.hasClass('out-of-stock')) {
                            return;
                        }
                        
                        // Get RGB values if available
                        let rgbValues = null;
                        
                        // Try to get RGB values from global object
                        if (window.tdPolygonjs && window.tdPolygonjs.colorOptions && 
                            window.tdPolygonjs.colorOptions[colorKey] &&
                            window.tdPolygonjs.colorOptions[colorKey].rgb) {
                            rgbValues = window.tdPolygonjs.colorOptions[colorKey].rgb;
                            
                            // Store the selected color and its RGB values
                            storeColorValues(colorKey, colorName, rgbValues);
                        }
                    });
                }
                
                function storeColorValues(colorKey, colorName, rgbValues) {
                    // Don't send if we don't have valid RGB values
                    if (!rgbValues || !Array.isArray(rgbValues) || rgbValues.length !== 3) {
                        return;
                    }
                    
                    console.log('[Color Sync] Storing color values for', colorName, rgbValues);
                    
                    // Send values to server
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'td_store_color_values',
                            product_id: <?php echo intval($product_id); ?>,
                            color_key: colorKey,
                            color_name: colorName,
                            rgb_values: rgbValues
                        },
                        success: function(response) {
                            if (response.success) {
                                console.log('[Color Sync] Color values stored', response);
                            } else {
                                console.warn('[Color Sync] Failed to store color values', response);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('[Color Sync] AJAX error:', status, error);
                        }
                    });
                }
            });
        })(jQuery);
        </script>
        <?php
    }
}

// Initialize the color sync system
TD_Color_Sync::init();