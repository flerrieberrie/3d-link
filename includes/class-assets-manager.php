<?php
/**
 * Assets Manager for 3D Link
 * 
 * Handles loading of scripts and styles for the plugin
 */

defined('ABSPATH') || exit;

class TD_Assets_Manager {
    /**
     * Initialize the class
     */
    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_assets() {
        if (!is_product()) return;

        // Get product information
        global $product;
        if (!$product || !is_a($product, 'WC_Product')) {
            $product = wc_get_product(get_the_ID());
        }
        if (!$product) return;

        $product_id = $product->get_id();
        
        // Only proceed if PolygonJS is enabled for this product
        if (get_post_meta($product_id, '_enable_polygonjs', true) !== 'yes') return;
        
        // Register and enqueue stylesheets
        wp_register_style(
            'td-customizer-style',
            TD_LINK_URL . 'assets/css/frontend-customizer.css',
            [],
            filemtime(TD_LINK_PATH . 'assets/css/frontend-customizer.css')
        );

        wp_register_style(
            'td-helpers-style',
            TD_LINK_URL . 'assets/css/frontend-helpers.css',
            ['td-customizer-style'],
            filemtime(TD_LINK_PATH . 'assets/css/frontend-helpers.css')
        );

        wp_enqueue_style('td-customizer-style');
        wp_enqueue_style('td-helpers-style');
        
        // Get a timestamp for cache busting
        $color_timestamp = get_option('td_colors_last_updated', time());
        
        // Add a query parameter for debugging
        if (isset($_GET['debug'])) {
            error_log("TD Link Debug: Current colors timestamp: {$color_timestamp}");
        }
        
        // Register and enqueue scripts with the color timestamp
        wp_register_script(
            'td-polygonjs-bridge', 
            TD_LINK_URL . 'assets/js/polygonjs-bridge.js', 
            ['jquery'], 
            filemtime(TD_LINK_PATH . 'assets/js/polygonjs-bridge.js') . '-' . $color_timestamp, 
            true
        );
        
        wp_register_script(
            'td-customizer-script',
            TD_LINK_URL . 'assets/js/frontend-customizer.js',
            ['jquery', 'td-polygonjs-bridge'],
            filemtime(TD_LINK_PATH . 'assets/js/frontend-customizer.js') . '-' . $color_timestamp,
            true
        );

        wp_register_script(
            'td-helpers-script',
            TD_LINK_URL . 'assets/js/frontend-helpers.js',
            ['jquery', 'td-customizer-script'],
            filemtime(TD_LINK_PATH . 'assets/js/frontend-helpers.js') . '-' . $color_timestamp,
            true
        );
        
        // Register and enqueue the measurement units handler script
        wp_register_script(
            'td-measurement-units',
            TD_LINK_URL . 'assets/js/measurement-units.js',
            ['jquery', 'td-customizer-script'],
            filemtime(TD_LINK_PATH . 'assets/js/measurement-units.js'),
            true
        );
        
        // Register unified parameter capture script
        wp_register_script(
            'td-unified-capture',
            TD_LINK_URL . 'assets/js/unified-parameter-capture.js',
            ['jquery', 'td-customizer-script'],
            filemtime(TD_LINK_PATH . 'assets/js/unified-parameter-capture.js'),
            true
        );

        wp_enqueue_script('td-polygonjs-bridge');
        wp_enqueue_script('td-customizer-script');
        wp_enqueue_script('td-helpers-script');
        wp_enqueue_script('td-measurement-units');
        wp_enqueue_script('td-unified-capture');
        
        // Get parameters and prepare data for JavaScript
        $parameters_manager = new TD_Parameters_Manager();
        $parameters = $parameters_manager->get_parameters($product_id);
        
        // Prepare data for JavaScript
        $js_data = $this->prepare_js_data($product_id, $product, $parameters);
        
        // Add timestamp to js_data for debugging
        if (isset($_GET['debug'])) {
            $js_data['debug'] = [
                'timestamp' => $color_timestamp,
                'version' => TD_LINK_VERSION
            ];
        }
        
        wp_localize_script('td-polygonjs-bridge', 'tdPolygonjs', $js_data);
        
        // Add variables for unified parameter capture
        wp_localize_script('td-unified-capture', 'tdUnified', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'product_id' => $product_id,
            'user_id' => get_current_user_id(),
            'nonce' => wp_create_nonce('td_unified_sync')
        ]);
    }

    /**
     * Prepare data for JavaScript
     */
    private function prepare_js_data($product_id, $product, $parameters) {
        $js_data = [
            'productId' => $product_id,
            'parameters' => [],
            'helperParameters' => [], // Add special array for helper parameters
            'model_path' => $this->get_model_path($product_id, $product),
            'rgbGroups' => [],
            'defaultColorIds' => [], // Store default color IDs for each parameter
            'measurementUnit' => get_option('td_measurement_unit', 'cm'), // Pass the measurement unit to frontend
            'exporterNodePath' => $this->get_exporter_node_path($product_id) // Get the configured exporter node path
        ];
        
        // Get sections manager for helper section check
        global $td_link;
        $sections_manager = isset($td_link) && isset($td_link->sections_manager)
            ? $td_link->sections_manager
            : new TD_Sections_Manager();

        // Process each parameter for JS
        foreach ($parameters as $param) {
            if (empty($param['node_id'])) continue;

            // Check if this is a helper parameter
            $is_helper = isset($param['is_helper']) && $param['is_helper'];

            // Get parameter section
            $section_id = isset($param['section']) && !empty($param['section'])
                ? $param['section']
                : $sections_manager->auto_categorize_parameter($param);

            // Also check if in helpers section
            $in_helper_section = $sections_manager->is_helper_section($section_id);

            // Store parameter data
            $param_data = [
                'display_name' => $param['display_name'],
                'control_type' => $param['control_type'],
                'html_snippet' => $param['html_snippet'],
                'default_value' => $param['default_value'] ?? '',
                'is_helper' => $is_helper || $in_helper_section,
                'section' => $section_id,
            ];

            // Add default color ID if this is a color parameter
            if ($param['control_type'] === 'color' && !empty($param['default_color_id'])) {
                $param_data['default_color_id'] = $param['default_color_id'];
                $js_data['defaultColorIds'][] = $param['default_color_id'];

                if (isset($_GET['debug'])) {
                    error_log("TD Link Debug: Adding default color ID to JS data: {$param['default_color_id']} for parameter {$param['node_id']}");
                }
            }

            // Store in the appropriate collection
            if ($is_helper || $in_helper_section) {
                $js_data['helperParameters'][$param['node_id']] = $param_data;
            }

            // Always include in the main parameters array for backward compatibility
            $js_data['parameters'][$param['node_id']] = $param_data;
            
            // Add RGB component info if available
            if (!empty($param['is_rgb_component']) && !empty($param['rgb_group'])) {
                $js_data['parameters'][$param['node_id']]['is_rgb_component'] = $param['is_rgb_component'];
                $js_data['parameters'][$param['node_id']]['rgb_group'] = $param['rgb_group'];
                
                // Initialize RGB group data structure if not exists
                if (!isset($js_data['rgbGroups'][$param['rgb_group']])) {
                    $js_data['rgbGroups'][$param['rgb_group']] = [
                        'components' => [],
                        'display_name' => $param['display_name']
                    ];
                }
                
                // Add this component to the RGB group
                $js_data['rgbGroups'][$param['rgb_group']]['components'][$param['is_rgb_component']] = $param['node_id'];
                
                // If this is the main (R) component, update the group display name
                if ($param['is_rgb_component'] === 'r') {
                    $js_data['rgbGroups'][$param['rgb_group']]['display_name'] = $param['display_name'];
                }
            }
            
            // Add control-specific settings
            switch ($param['control_type']) {
                case 'number':
                case 'slider':
                    $js_data['parameters'][$param['node_id']]['min'] = $param['min'] ?? 0;
                    $js_data['parameters'][$param['node_id']]['max'] = $param['max'] ?? 100;
                    $js_data['parameters'][$param['node_id']]['step'] = $param['step'] ?? 1;
                    break;
                    
                case 'text':
                    $js_data['parameters'][$param['node_id']]['max_length'] = $param['max_length'] ?? 20;
                    break;
            }
        }
        
        // Always fetch fresh colors from the database
        $js_colors = [];
        if (class_exists('TD_Colors_Manager')) {
            $colors_manager = new TD_Colors_Manager();
            
            // Clear any cached colors first
            wp_cache_delete('td_global_colors', 'options');
            
            // Get all colors including out-of-stock ones
            $colors = $colors_manager->get_colors_for_select(true);
            
            // Map colors for JS with stock status
            foreach ($colors as $color) {
                $color_key = strtolower(str_replace(' ', '-', $color['name']));
                $js_colors[$color_key] = [
                    'name' => $color['name'],
                    'hex' => $color['hex'],
                    'rgb' => $color['rgb'],
                    'in_stock' => isset($color['in_stock']) ? (bool)$color['in_stock'] : true
                ];
                
                // Debug output
                if (isset($_GET['debug'])) {
                    error_log("TD Link Debug: Adding color to JS data: {$color['name']}, in_stock: " . 
                             (isset($color['in_stock']) && $color['in_stock'] ? 'true' : 'false'));
                }
            }
            
            $js_data['colorOptions'] = $js_colors;
            
            // Get colors string for parameters without specific colors
            $color_options = $colors_manager->get_color_options_string(true); // Include out-of-stock colors
            $js_data['defaultColorOptions'] = $color_options;
        } else {
            // Fallback to product-specific colors
            $color_options = get_post_meta($product_id, '_td_case_color_options', true);
            
            // Parse colors for JS
            if ($color_options) {
                $colors = [];
                foreach (explode('|', $color_options) as $item) {
                    $parts = explode(';', trim($item));
                    if (count($parts) >= 2) {
                        $color_name = $parts[0];
                        $color_hex = $parts[1];
                        $in_stock = true;
                        
                        // Check for stock status
                        if (count($parts) >= 3) {
                            $in_stock = ($parts[2] === '1');
                        }
                        
                        $color_key = strtolower(str_replace(' ', '-', $color_name));
                        
                        // Convert to RGB for PolygonJS
                        $rgb = $this->hex_to_rgb($color_hex);
                        if ($rgb) {
                            $colors[$color_key] = [
                                'name' => $color_name,
                                'hex' => $color_hex,
                                'rgb' => $rgb,
                                'in_stock' => $in_stock
                            ];
                            
                            if (isset($_GET['debug'])) {
                                error_log("TD Link Debug: Adding fallback color to JS data: {$color_name}, in_stock: " . 
                                         ($in_stock ? 'true' : 'false'));
                            }
                        }
                    }
                }
                $js_data['colorOptions'] = $colors;
                $js_data['defaultColorOptions'] = $color_options;
            }
        }
        
        return $js_data;
    }
    
    /**
     * Get the 3D model path for a product
     */
    private function get_model_path($product_id, $product) {
        // Check for new dist path setting
        $dist_path = get_post_meta($product_id, '_td_dist_path', true);
        if (empty($dist_path)) {
            $dist_path = '3d/dist';
        }
        
        // Check for scene name
        $scene_name = get_post_meta($product_id, '_td_scene_name', true);
        if (empty($scene_name)) {
            $scene_name = $product->get_slug();
        }
        
        // Construct the new model path with scene parameter
        $model_path = rtrim($dist_path, '/') . '/?scene=' . $scene_name;
        
        // For backward compatibility, check if legacy folder path exists
        $legacy_folder_path = get_post_meta($product_id, '_td_polygonjs_folder', true);
        if (!empty($legacy_folder_path) && empty($scene_name)) {
            // Use legacy path if no scene name is defined
            $legacy_folder_path = str_replace('{product-slug}', $product->get_slug(), $legacy_folder_path);
            $legacy_folder_path = str_replace('{product-id}', $product_id, $legacy_folder_path);
            
            return $legacy_folder_path;
        }
        
        return $model_path;
    }
    
    /**
     * Convert hex color to RGB values (0-1 range for PolygonJS)
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) return false;
        
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        
        return [$r, $g, $b];
    }
    
    /**
     * Get the exporter node path for a product
     * Checks both product-specific setting and global default
     * 
     * @param int $product_id The product ID
     * @return string The exporter node path
     */
    private function get_exporter_node_path($product_id) {
        // First check if there's a product-specific setting
        $product_exporter_path = get_post_meta($product_id, '_td_exporter_node_path', true);
        
        if (!empty($product_exporter_path)) {
            return $product_exporter_path;
        }
        
        // Fallback to global default
        $default_path = get_option('td_default_exporter_node_path', '');
        
        // If still empty, provide a generic fallback based on common paths
        if (empty($default_path)) {
            // Check the scene name to suggest a common path
            $scene_name = get_post_meta($product_id, '_td_scene_name', true);
            
            // Map common scene names to their typical exporter paths
            $common_paths = [
                'telefoonhoes' => '/geo2/exporterGLTF1',
                'sleutelhanger' => '/geo1/exporterGLTF1',
                'doosje' => '/doos/exporterGLTF1',
                'sleutelhoes' => '/doos/exporterGLTF1',
                'sandblock' => '/geo1/exporterGLTF1'
            ];
            
            if (isset($common_paths[$scene_name])) {
                return $common_paths[$scene_name];
            }
            
            // Final fallback to most common path
            return '/geo1/exporterGLTF1';
        }
        
        return $default_path;
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * This optimized version uses consistently named CSS files
     * for better organization and clarity
     */
    public function enqueue_admin_assets($hook) {
        // Determine which admin page we're on
        $is_td_page = isset($_GET['page']) && strpos($_GET['page'], 'td-') === 0;
        $is_colors_page = isset($_GET['page']) && $_GET['page'] === 'td-colors';
        $is_dashboard_page = isset($_GET['page']) && $_GET['page'] === 'td-link';
        $is_settings_page = isset($_GET['page']) && $_GET['page'] === 'td-link-settings';
        $is_product_edit = ($hook === 'post.php' || $hook === 'post-new.php');
        
        // Only proceed if we're on a relevant page
        if (!$is_td_page && !$is_product_edit) {
            return;
        }
        
        // For product edit screens, check if it's actually a product
        if ($is_product_edit) {
            global $post;
            if (!$post || $post->post_type !== 'product') {
                return;
            }
        }
        
        // 1. Core styles for all plugin admin pages
        wp_register_style(
            'td-admin-core-style', 
            TD_LINK_URL . 'assets/css/admin-core.css', 
            [], 
            filemtime(TD_LINK_PATH . 'assets/css/admin-core.css')
        );
        
        wp_enqueue_style('td-admin-core-style');
        
        // 2. Dashboard-specific styles
        if ($is_dashboard_page || $is_settings_page) {
            wp_register_style(
                'td-admin-dashboard-style', 
                TD_LINK_URL . 'assets/css/admin-dashboard.css', 
                ['td-admin-core-style'], // Load after core styles
                filemtime(TD_LINK_PATH . 'assets/css/admin-dashboard.css')
            );
            
            wp_enqueue_style('td-admin-dashboard-style');
        }
        
        // 3. Colors Manager page styles
        if ($is_colors_page) {
            // WordPress color picker
            wp_enqueue_style('wp-color-picker');
            
            // Register and enqueue admin styles for colors manager
            wp_register_style(
                'td-admin-colors-style', 
                TD_LINK_URL . 'assets/css/admin-colors.css', 
                ['td-admin-core-style'], // Load after core styles for proper override
                filemtime(TD_LINK_PATH . 'assets/css/admin-colors.css')
            );
            
            wp_enqueue_style('td-admin-colors-style');
            
            // Register and enqueue scripts for colors manager
            wp_register_script(
                'td-colors-manager-script', 
                TD_LINK_URL . 'assets/js/admin-colors.js', 
                ['jquery', 'wp-color-picker'], 
                filemtime(TD_LINK_PATH . 'assets/js/admin-colors.js'), 
                true
            );
            
            wp_enqueue_script('td-colors-manager-script');
        }
        
        // 4. Customer Models Browser page
        $is_models_page = isset($_GET['page']) && $_GET['page'] === 'td-models';
        if ($is_models_page) {
            // Register model preview script - it will be loaded with the PolygonJS viewer
            wp_register_script(
                'td-admin-model-preview', 
                TD_LINK_URL . 'assets/js/admin-model-preview.js', 
                [], 
                filemtime(TD_LINK_PATH . 'assets/js/admin-model-preview.js'),
                true
            );
            
            // Add the script to the list of scripts that should be added to the polygonjs viewer
            add_filter('td_polygonjs_viewer_scripts', function($scripts) {
                $scripts[] = 'td-admin-model-preview';
                return $scripts;
            });
        }
        
        // 5. Product edit screen JS
        if ($is_product_edit) {
            wp_register_script(
                'td-admin-script', 
                TD_LINK_URL . 'assets/js/admin-core.js', 
                ['jquery', 'jquery-ui-sortable'], 
                filemtime(TD_LINK_PATH . 'assets/js/admin-core.js'), 
                true
            );
            
            wp_enqueue_script('td-admin-script');
            
            // Register and enqueue parameter groups script
            wp_register_script(
                'td-admin-parameter-groups', 
                TD_LINK_URL . 'assets/js/admin-parameter-groups.js', 
                ['jquery', 'jquery-ui-sortable', 'td-admin-script'], 
                filemtime(TD_LINK_PATH . 'assets/js/admin-parameter-groups.js'), 
                true
            );
            
            wp_enqueue_script('td-admin-parameter-groups');
            
            // No localization needed - simple system with no AJAX
        }
    }
}