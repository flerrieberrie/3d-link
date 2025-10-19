<?php
/**
 * Cart Handler for PolygonJS Parameters
 * 
 * Handles adding parameter values to cart items and orders
 */

defined('ABSPATH') || exit;

class TD_Frontend_Cart {
    /**
     * Initialize the class
     */
    public function __construct() {
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_parameters_to_cart'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_parameters_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_parameters_to_order'], 10, 4);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'format_order_meta'], 10, 2);

        // Add a hook to capture cart examples for admin viewing
        add_action('woocommerce_after_cart_item_data_set', [$this, 'save_cart_example'], 10, 2);

        // Register settings initialization for parameter visibility
        add_action('admin_init', [$this, 'register_parameter_visibility_settings']);
        
        // Get the measurement unit setting
        $this->measurement_unit = get_option('td_measurement_unit', 'cm');
    }

    /**
     * Register settings for parameter visibility
     */
    public function register_parameter_visibility_settings() {
        register_setting('td_link_settings', 'td_hidden_parameters');
        register_setting('td_link_settings', 'td_measurement_unit');
    }
    
    /**
     * Save example of cart with 3D parameters for admin to view
     * This helps with demonstrating the cart viewer functionality
     */
    public function save_cart_example($cart_item_key, $cart_item) {
        // Skip if there are no 3D parameters
        if (empty($cart_item['td_parameters'])) {
            return;
        }
        
        // Save up to 5 examples for admin to view
        $examples = get_option('td_example_carts', []);
        
        // Add this cart item as an example if we have less than 5
        if (count($examples) < 5) {
            $examples[$cart_item_key] = [
                'item' => $cart_item,
                'time' => time(),
                'user_id' => get_current_user_id(),
                'session_id' => isset(WC()->session) ? WC()->session->get_customer_id() : ''
            ];
            
            // Keep only the 5 most recent examples
            if (count($examples) > 5) {
                uasort($examples, function($a, $b) {
                    return $b['time'] - $a['time'];
                });
                $examples = array_slice($examples, 0, 5, true);
            }
            
            update_option('td_example_carts', $examples);
        }
    }
    
    /**
     * Add parameter values to cart item data
     */
    public function add_parameters_to_cart($cart_item_data, $product_id, $variation_id) {
        // Get parameters
        $parameters_manager = new TD_Parameters_Manager();
        $parameters = $parameters_manager->get_parameters($product_id);

        if (empty($parameters)) return $cart_item_data;

        $custom_data = [];

        // Get sections manager for helper section check
        global $td_link;
        $sections_manager = isset($td_link) && isset($td_link->sections_manager)
            ? $td_link->sections_manager
            : new TD_Sections_Manager();

        // Track RGB groups to avoid duplication
        $processed_rgb_groups = [];

        // Process each parameter
        foreach ($parameters as $param) {
            if (empty($param['node_id'])) continue;

            // Skip helper parameters - they should not be stored in the cart
            $is_helper = isset($param['is_helper']) && $param['is_helper'];
            if ($is_helper) {
                continue;
            }

            // Skip parameters in the helper section
            $section_id = isset($param['section']) && !empty($param['section'])
                ? $param['section']
                : $sections_manager->auto_categorize_parameter($param);

            if ($sections_manager->is_helper_section($section_id)) {
                continue;
            }

            // Check if this is part of an RGB group
            $rgb_group = !empty($param['rgb_group']) ? $param['rgb_group'] : '';
            $is_rgb_component = !empty($param['is_rgb_component']) ? $param['is_rgb_component'] : '';

            // Skip non-main RGB components
            if ($rgb_group && $is_rgb_component !== 'r') {
                continue;
            }

            // Skip if this RGB group was already processed
            if ($rgb_group && in_array($rgb_group, $processed_rgb_groups)) {
                continue;
            }
            
            $control_id = sanitize_title($param['node_id']);
            
            // Initialize with default value if parameter wasn't submitted
            if (!isset($_POST[$control_id]) && !isset($_POST[$control_id . '_radio'])) {
                // Use default value from parameter definition
                $default_value = isset($param['default_value']) ? $param['default_value'] : '';
                
                // For colors, we need to handle differently
                if ($param['control_type'] === 'color') {
                    // For color with radio selection, we'll check for default color ID first, then fallback to first in-stock
                    if (class_exists('TD_Colors_Manager')) {
                        $colors_manager = new TD_Colors_Manager();
                        $global_colors = $colors_manager->get_global_colors();

                        // Get selected color IDs for this parameter
                        $selected_color_ids = [];
                        if (isset($param['color_ids']) && !empty($param['color_ids'])) {
                            $selected_color_ids = explode(',', $param['color_ids']);
                        }

                        // Check if a default color is specified
                        $default_color_id = isset($param['default_color_id']) ? $param['default_color_id'] : '';
                        $default_color_found = false;

                        // Try to use the default color if it exists and is in stock
                        if (!empty($default_color_id) && isset($global_colors[$default_color_id])) {
                            $color = $global_colors[$default_color_id];

                            // Make sure the color is selected for this parameter and in stock
                            $is_selected = empty($selected_color_ids) || in_array($default_color_id, $selected_color_ids);
                            $is_in_stock = isset($color['in_stock']) ? (bool)$color['in_stock'] : true;

                            if ($is_selected && $is_in_stock) {
                                $default_color_name = $color['name'];
                                $default_color_key = strtolower(str_replace(' ', '-', $color['name']));
                                $default_color_found = true;
                            }
                        }

                        // If no default color found or it wasn't available, fallback to first in-stock color
                        if (!$default_color_found) {
                            $default_color_name = '';
                            $default_color_key = '';

                            foreach ($global_colors as $color_id => $color) {
                                // Skip if not selected for this parameter (if we have a selection)
                                if (!empty($selected_color_ids) && !in_array($color_id, $selected_color_ids)) {
                                    continue;
                                }

                                // Check if color is in stock
                                $is_in_stock = isset($color['in_stock']) ? (bool)$color['in_stock'] : true;

                                if ($is_in_stock) {
                                    $default_color_name = $color['name'];
                                    $default_color_key = strtolower(str_replace(' ', '-', $color['name']));
                                    break;
                                }
                            }
                        }

                        // Set default color values
                        $_POST[$control_id] = $default_color_name;
                        $_POST[$control_id . '_radio'] = $default_color_key;
                    }
                } else {
                    // For other control types, set the default value in POST data
                    $_POST[$control_id] = $default_value;
                }
            }
            
            // Continue only if we have a value (either submitted or default)
            if (!isset($_POST[$control_id]) && !isset($_POST[$control_id . '_radio'])) continue;
            
            // Get the value based on control type
            switch ($param['control_type']) {
                case 'slider':
                case 'number':
                    $value = floatval($_POST[$control_id]);
                    break;
                    
                case 'text':
                    $value = sanitize_text_field($_POST[$control_id]);
                    break;
                    
                case 'checkbox':
                    $value = isset($_POST[$control_id]) && $_POST[$control_id] === '1' ? 'Yes' : 'No';
                    break;
                    
                case 'color':
                    $value = sanitize_text_field($_POST[$control_id]);
                    
                    // Get the name of the color for display
                    if (isset($_POST[$control_id . '_radio'])) {
                        $color_key = sanitize_text_field($_POST[$control_id . '_radio']);
                        
                        // Try to get human-readable color name
                        if (!empty($param['color_options'])) {
                            $colors = $this->parse_color_options($param['color_options']);
                            foreach ($colors as $name => $hex) {
                                $color_key_test = strtolower(str_replace(' ', '-', $name));
                                if ($color_key_test === $color_key) {
                                    $value = $name;
                                    break;
                                }
                            }
                        } else if (class_exists('TD_Colors_Manager')) {
                            // Look up from global colors
                            $colors_manager = new TD_Colors_Manager();
                            $colors = $colors_manager->get_global_colors();
                            foreach ($colors as $color_id => $color) {
                                $color_key_test = strtolower(str_replace(' ', '-', $color['name']));
                                if ($color_key_test === $color_key) {
                                    $value = $color['name'];
                                    break;
                                }
                            }
                        }
                    }
                    
                    // If this is an RGB group, mark it as processed
                    if ($rgb_group) {
                        $processed_rgb_groups[] = $rgb_group;
                    }
                    break;
                    
                case 'dropdown':
                    $value = sanitize_text_field($_POST[$control_id]);
                    
                    // Get option label for display if available
                    if (!empty($param['dropdown_options'])) {
                        $options = [];
                        foreach (explode("\n", $param['dropdown_options']) as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            
                            $parts = explode('=', $line, 2);
                            if (count($parts) === 2 && trim($parts[0]) === $value) {
                                $value = trim($parts[0]) . ' - ' . trim($parts[1]); // Show both value and label
                                break;
                            }
                        }
                    }
                    break;
                    
                default:
                    $value = sanitize_text_field($_POST[$control_id]);
            }

            // For colors, store additional data including hex values
            if ($param['control_type'] === 'color') {
                $value = sanitize_text_field($_POST[$control_id]);
                $color_key = isset($_POST[$control_id . '_radio']) ? 
                    sanitize_text_field($_POST[$control_id . '_radio']) : '';
                
                // Initialize color data storage if not already present
                if (!isset($cart_item_data['td_color_data'])) {
                    $cart_item_data['td_color_data'] = [];
                }
                
                // Try to get color information including hex
                $color_hex = '';
                $color_rgb = null;
                
                // Try to find this color in the global colors by name or key
                if (class_exists('TD_Colors_Manager')) {
                    $colors_manager = new TD_Colors_Manager();
                    $global_colors = $colors_manager->get_global_colors();
                    
                    // First check by color key
                    if (!empty($color_key)) {
                        foreach ($global_colors as $color_id => $color) {
                            $test_key = strtolower(str_replace(' ', '-', $color['name']));
                            if ($test_key === $color_key) {
                                $color_hex = $color['hex'];
                                $color_rgb = $color['rgb'] ?? null;
                                break;
                            }
                        }
                    }
                    
                    // If no hex found yet, try by name
                    if (empty($color_hex)) {
                        foreach ($global_colors as $color_id => $color) {
                            if (strtolower($color['name']) === strtolower($value)) {
                                $color_hex = $color['hex'];
                                $color_rgb = $color['rgb'] ?? null;
                                break;
                            }
                        }
                    }
                }
                
                // Get the default color ID if it exists
                $default_color_id = isset($param['default_color_id']) ? $param['default_color_id'] : '';

                // Get the color ID if we can identify it
                $color_id = '';
                foreach ($global_colors as $id => $color) {
                    if (strtolower($color['name']) === strtolower($value)) {
                        $color_id = $id;
                        break;
                    }
                }

                // Store the color data
                $cart_item_data['td_color_data'][$control_id] = [
                    'name' => $value,
                    'hex' => $color_hex,
                    'rgb' => $color_rgb,
                    'key' => $color_key,
                    'color_id' => $color_id,
                    'default_color_id' => $default_color_id,
                    'is_default' => ($color_id === $default_color_id && !empty($default_color_id))
                ];
            }            
            
            // Add to custom data
            $custom_data[$control_id] = [
                'display_name' => $param['display_name'],
                'value' => $value,
                'control_type' => $param['control_type'],
                'is_rgb_group' => !empty($rgb_group),
                'rgb_group' => $rgb_group
            ];
        }
        
        // If we have custom data, add it to the cart item
        if (!empty($custom_data)) {
            $cart_item_data['td_parameters'] = $custom_data;
            
            // Store unified sync state for exact frontend-backend matching
            if (class_exists('TD_Unified_Parameter_Sync')) {
                $session_key = TD_Unified_Parameter_Sync::store_cart_state($cart_item_data, $product_id);
                $cart_item_data['td_sync_key'] = $session_key;
            }
        }
        
        return $cart_item_data;
    }
    
    /**
     * Display parameter values in cart
     */
    public function display_parameters_in_cart($item_data, $cart_item) {
        if (empty($cart_item['td_parameters'])) return $item_data;
        
        foreach ($cart_item['td_parameters'] as $control_id => $data) {
            // Format the value for display
            $value = $data['value'];
            $unit = '';
            
            // Add units for specific parameter types
            if (in_array($data['control_type'], ['slider', 'number'])) {
                // Check if this is a dimension parameter
                $lc_name = strtolower($data['display_name']);
                if (strpos($lc_name, 'size') !== false || 
                    strpos($lc_name, 'height') !== false || 
                    strpos($lc_name, 'width') !== false || 
                    strpos($lc_name, 'depth') !== false ||
                    strpos($lc_name, 'length') !== false ||
                    strpos($lc_name, 'thickness') !== false ||
                    strpos($lc_name, 'radius') !== false ||
                    strpos($lc_name, 'diameter') !== false ||
                    strpos($lc_name, 'distance') !== false) {
                    $unit = ' ' . $this->measurement_unit;
                }
            }
            
            // Special handling for RGB color groups
            if (!empty($data['is_rgb_group']) && $data['control_type'] === 'color') {
                $item_data[] = [
                    'key' => $data['display_name'],
                    'value' => $value,
                    'display' => $value . ' (RGB)',
                ];
            }
            // Special handling for dropdown values
            else if ($data['control_type'] === 'dropdown') {
                // Check if the value contains both value and label
                if (strpos($value, ' - ') !== false) {
                    $parts = explode(' - ', $value, 2);
                    $item_data[] = [
                        'key' => $data['display_name'],
                        'value' => $parts[1], // Just display the label part
                        'display' => $parts[1],
                    ];
                } else {
                    $item_data[] = [
                        'key' => $data['display_name'],
                        'value' => $value,
                        'display' => $value,
                    ];
                }
            }
            else {
                $item_data[] = [
                    'key' => $data['display_name'],
                    'value' => $value . $unit,
                    'display' => $value . $unit,
                ];
            }
        }
        
        return $item_data;
    }
    
    /**
     * Add parameter values to order line items
     */
    public function add_parameters_to_order($item, $cart_item_key, $values, $order) {
        if (empty($values['td_parameters'])) return;

        foreach ($values['td_parameters'] as $control_id => $data) {
            // Format the value for saving to the order
            $value = $data['value'];
            $unit = '';

            // Add units for specific parameter types
            if (in_array($data['control_type'], ['slider', 'number'])) {
                // Check if this is a dimension parameter
                $lc_name = strtolower($data['display_name']);
                if (strpos($lc_name, 'size') !== false ||
                    strpos($lc_name, 'height') !== false ||
                    strpos($lc_name, 'width') !== false ||
                    strpos($lc_name, 'depth') !== false ||
                    strpos($lc_name, 'length') !== false ||
                    strpos($lc_name, 'thickness') !== false ||
                    strpos($lc_name, 'radius') !== false ||
                    strpos($lc_name, 'diameter') !== false ||
                    strpos($lc_name, 'distance') !== false) {
                    $unit = ' ' . $this->measurement_unit;
                }
            }

            // Get parameter visibility settings
            $hidden_params = get_option('td_hidden_parameters', array());

            // Get section for this parameter
            global $td_link;
            $sections_manager = isset($td_link) && isset($td_link->sections_manager)
                ? $td_link->sections_manager
                : new TD_Sections_Manager();

            // Get parameter's section (either explicitly set or auto-detected)
            $section_id = isset($data['section']) && !empty($data['section'])
                ? $data['section']
                : $sections_manager->auto_categorize_parameter($data);

            // Skip helper parameters
            $is_helper = isset($data['is_helper']) && $data['is_helper'];
            if ($is_helper || $sections_manager->is_helper_section($section_id)) {
                continue;
            }

            // Check if section is hidden
            $section_key = 'section_' . $section_id;
            $section_hidden = in_array($section_key, $hidden_params);

            // Legacy check for backward compatibility
            if (!$section_id || !$section_hidden) {
                $lc_name = strtolower($data['display_name']);
                if (strpos($lc_name, 'size') !== false ||
                    strpos($lc_name, 'height') !== false ||
                    strpos($lc_name, 'width') !== false ||
                    strpos($lc_name, 'depth') !== false ||
                    strpos($lc_name, 'length') !== false) {
                    $section_hidden = in_array('section_dimensions', $hidden_params);
                }
                else if (strpos($lc_name, 'color') !== false ||
                         strpos($lc_name, 'material') !== false) {
                    $section_hidden = in_array('section_appearance', $hidden_params);
                }
            }

            // Skip adding this parameter if its section is hidden
            if ($section_hidden) {
                continue;
            }

            // Add display metadata for color type
            if ($data['control_type'] === 'color' && !empty($data['is_rgb_group'])) {
                $item->add_meta_data($data['display_name'], $value . ' (RGB)');
            }
            // Add display metadata for dropdown
            else if ($data['control_type'] === 'dropdown') {
                // Check if the value contains both value and label
                if (strpos($value, ' - ') !== false) {
                    $parts = explode(' - ', $value, 2);
                    $item->add_meta_data($data['display_name'], $parts[1]); // Just show the label
                    // Store original value as hidden meta
                    $item->add_meta_data('_td_dropdown_original_' . $control_id, $parts[0], true);
                } else {
                    $item->add_meta_data($data['display_name'], $value);
                }
            }
            else {
                $item->add_meta_data($data['display_name'], $value . $unit);
            }
            
            // Add technical metadata (hidden from customer)
            $item->add_meta_data('_td_control_type_' . $control_id, $data['control_type'], true);
            
            if (!empty($data['is_rgb_group'])) {
                $item->add_meta_data('_td_is_rgb_group_' . $control_id, '1', true);
                $item->add_meta_data('_td_rgb_group_' . $control_id, $data['rgb_group'], true);
            }
        }
        // Store color information for color parameters
        if ($data['control_type'] === 'color') {
            $color_name = $value;
            $color_hex = '';
            $color_id = '';
            $is_default = false;

            // Try to get the color information from global colors
            if (class_exists('TD_Colors_Manager')) {
                $colors_manager = new TD_Colors_Manager();
                $global_colors = $colors_manager->get_global_colors();

                // Get the product ID to retrieve the original parameter data
                $product_id = $item->get_product_id();
                $default_color_id = '';

                if ($product_id) {
                    $parameters_manager = new TD_Parameters_Manager();
                    $parameters = $parameters_manager->get_parameters($product_id);

                    // Find the parameter that matches our control ID
                    foreach ($parameters as $parameter) {
                        if (sanitize_title($parameter['node_id']) === $control_id) {
                            // Get the default color ID from the parameter
                            $default_color_id = isset($parameter['default_color_id']) ? $parameter['default_color_id'] : '';
                            break;
                        }
                    }
                }

                foreach ($global_colors as $current_color_id => $color) {
                    if (strtolower($color['name']) === strtolower($color_name)) {
                        $color_hex = $color['hex'];
                        $color_id = $current_color_id;

                        // Check if this is the default color
                        $is_default = ($color_id === $default_color_id);

                        // Also store RGB values if needed for 3D rendering
                        $color_rgb = $color['rgb'] ?? null;
                        if ($color_rgb) {
                            $item->add_meta_data('_td_color_rgb_' . $control_id, json_encode($color_rgb), true);
                        }

                        break;
                    }
                }
            }

            // If using hex value directly (rare but possible)
            if (empty($color_hex) && preg_match('/#([a-f0-9]{3}){1,2}\b/i', $color_name)) {
                $color_hex = $color_name;
            }

            // If we found a color hex, store it with the order item
            if (!empty($color_hex)) {
                $item->add_meta_data('_td_color_hex_' . $control_id, $color_hex, true);
            }

            // Store the color ID if found
            if (!empty($color_id)) {
                $item->add_meta_data('_td_color_id_' . $control_id, $color_id, true);
            }

            // Store if this was a default color
            if ($is_default) {
                $item->add_meta_data('_td_color_is_default_' . $control_id, '1', true);
            }
        }
        
        // Store unified sync key for backend viewing
        if (!empty($values['td_sync_key'])) {
            $item->add_meta_data('_td_sync_key', $values['td_sync_key'], true);
        }
    }
    
    /**
     * Format order meta for display
     */
    public function format_order_meta($formatted_meta, $item) {
        // Get parameter visibility settings
        $hidden_params = get_option('td_hidden_parameters', array());

        foreach ($formatted_meta as $key => $meta) {
            // Skip our internal meta
            if (strpos($meta->key, '_td_') === 0) {
                unset($formatted_meta[$key]);
                continue;
            }

            // Get section information for this parameter
            $parameters = [];
            $product_id = null;
            
            // Check if the item has get_product_id method (shipping items don't)
            if (method_exists($item, 'get_product_id')) {
                $product_id = $item->get_product_id();
                
                if ($product_id) {
                    $parameters_manager = new TD_Parameters_Manager();
                    $parameters = $parameters_manager->get_parameters($product_id);
                }
            }

            // Get sections manager
            global $td_link;
            $sections_manager = isset($td_link) && isset($td_link->sections_manager)
                ? $td_link->sections_manager
                : new TD_Sections_Manager();

            // Find parameter's section and helper status
            $param_section = null;
            $is_helper = false;
            $param_name = $meta->key;

            foreach ($parameters as $param) {
                if ($param['display_name'] === $param_name) {
                    // Check if this is a helper parameter
                    $is_helper = isset($param['is_helper']) && $param['is_helper'];

                    // Get parameter's section (either explicitly set or auto-detected)
                    $section_id = isset($param['section']) && !empty($param['section'])
                        ? $param['section']
                        : $sections_manager->auto_categorize_parameter($param);

                    $param_section = $section_id;
                    break;
                }
            }

            // Skip helper parameters
            if ($is_helper || ($param_section && $sections_manager->is_helper_section($param_section))) {
                unset($formatted_meta[$key]);
                continue;
            }

            // If we found a section, check if it should be hidden
            if ($param_section) {
                $section_key = 'section_' . $param_section;
                if (in_array($section_key, $hidden_params)) {
                    unset($formatted_meta[$key]);
                    continue;
                }
            }

            // Fallback to legacy checks for backward compatibility
            if (!$param_section) {
                $lc_name = strtolower($meta->key);
                if ((in_array('section_dimensions', $hidden_params) &&
                    (strpos($lc_name, 'size') !== false ||
                     strpos($lc_name, 'height') !== false ||
                     strpos($lc_name, 'width') !== false ||
                     strpos($lc_name, 'depth') !== false ||
                     strpos($lc_name, 'length') !== false))
                   ||
                   (in_array('section_appearance', $hidden_params) &&
                    (strpos($lc_name, 'color') !== false ||
                     strpos($lc_name, 'material') !== false))) {
                    unset($formatted_meta[$key]);
                    continue;
                }
            }

            // Now get section name to use for display grouping
            if ($param_section) {
                // Get section name
                $section_name = $sections_manager->get_section_name($param_section);
                if (!empty($section_name)) {
                    $meta->display_key = $section_name;
                    // Include the original parameter name in the display value
                    $meta->display_value = $meta->key . ': ' . $meta->value;
                }
            }

            // Fallback to old behavior for backward compatibility
            if (!$param_section) {
                if (in_array($meta->key, ['Height', 'Width', 'Depth', 'Length'])) {
                    $meta->display_key = 'Dimensions';
                    $meta->display_value = $meta->key . ': ' . $meta->value;
                }
                else if (strpos($meta->key, 'Color') !== false || strpos($meta->key, 'Material') !== false) {
                    $meta->display_key = 'Appearance';
                    $meta->display_value = $meta->key . ': ' . $meta->value;
                }
            }
        }

        return $formatted_meta;
    }
    
    /**
     * Parse color options string to array
     */
    private function parse_color_options($color_options) {
        $colors = [];
        
        if (empty($color_options)) return $colors;
        
        foreach (explode('|', $color_options) as $item) {
            $parts = explode(';', trim($item));
            if (count($parts) === 2) {
                $colors[$parts[0]] = $parts[1];
            }
        }
        
        return $colors;
    }
}