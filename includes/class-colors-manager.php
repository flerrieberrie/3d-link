<?php
/**
 * Global Colors Manager for 3D Link
 * 
 * Handles the management of global color presets that can be used across products
 */

defined('ABSPATH') || exit;

class TD_Colors_Manager {
    /**
     * Option key for storing global colors
     */
    const GLOBAL_COLORS_OPTION = 'td_global_colors';
    
    /**
     * Initialize the class
     */
    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_td_save_global_colors', [$this, 'ajax_save_global_colors']);
        add_action('wp_ajax_td_delete_global_color', [$this, 'ajax_delete_global_color']);
    }
    
    /**
     * Register admin menu item
     */
    public function register_admin_menu() {
        add_menu_page(
            __('3D Link Colors', 'td-link'),
            __('3D Link Colors', 'td-link'),
            'manage_options',
            'td-colors',
            [$this, 'render_colors_page'],
            'dashicons-art',
            30
        );
    }
    
    /**
     * Render the colors management page
     */
    public function render_colors_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the admin interface
        include_once TD_LINK_PATH . 'admin/admin-colors.php';
    }
    
    /**
     * Get all global colors
     */
    public function get_global_colors() {
        $colors = get_option(self::GLOBAL_COLORS_OPTION, []);
        
        // Ensure colors are in the correct format
        if (!is_array($colors)) {
            $colors = [];
        }
        
        return $colors;
    }
    
    /**
     * Save a new global color
     */
    public function save_global_color($name, $hex, $rgb = null) {
        $colors = $this->get_global_colors();
        
        // Generate a unique ID
        $id = sanitize_title($name . '-' . uniqid());
        
        // If RGB values not provided, calculate from hex
        if ($rgb === null) {
            $rgb = $this->hex_to_rgb($hex);
        }
        
        // Add the new color
        $colors[$id] = [
            'name' => sanitize_text_field($name),
            'hex' => sanitize_hex_color($hex),
            'rgb' => $rgb,
            'in_stock' => true,
            'created' => current_time('mysql')
        ];
        
        // Save the updated colors
        update_option(self::GLOBAL_COLORS_OPTION, $colors);
        
        return $id;
    }
    
    /**
     * Update an existing global color
     */
    public function update_global_color($id, $data) {
        $colors = $this->get_global_colors();
        
        if (!isset($colors[$id])) {
            return false;
        }
        
        // Update fields
        $colors[$id]['name'] = isset($data['name']) ? sanitize_text_field($data['name']) : $colors[$id]['name'];
        $colors[$id]['hex'] = isset($data['hex']) ? sanitize_hex_color($data['hex']) : $colors[$id]['hex'];
        
        if (isset($data['rgb'])) {
            $colors[$id]['rgb'] = $data['rgb'];
        } elseif (isset($data['hex']) && $data['hex'] !== $colors[$id]['hex']) {
            // Recalculate RGB if hex changed
            $colors[$id]['rgb'] = $this->hex_to_rgb($data['hex']);
        }
        
        $colors[$id]['in_stock'] = isset($data['in_stock']) ? (bool) $data['in_stock'] : $colors[$id]['in_stock'];
        $colors[$id]['updated'] = current_time('mysql');
        
        // Save the updated colors
        update_option(self::GLOBAL_COLORS_OPTION, $colors);
        
        return true;
    }
    
    /**
     * Delete a global color
     */
    public function delete_global_color($id) {
        $colors = $this->get_global_colors();
        
        if (!isset($colors[$id])) {
            return false;
        }
        
        unset($colors[$id]);
        
        // Save the updated colors
        update_option(self::GLOBAL_COLORS_OPTION, $colors);
        
        return true;
    }

    /**
     * Ensures product pages get the updated stock status immediately
     */
    public function flush_colors_cache() {
        // When colors change, update a timestamp to force JS/CSS cache refresh
        $timestamp = time();
        update_option('td_colors_last_updated', $timestamp);
        
        // Log the timestamp update for debugging
        if (isset($_GET['debug'])) {
            error_log("TD Link Debug: Updated colors cache timestamp to {$timestamp}");
        }
        
        // Clear any WordPress object cache for colors
        wp_cache_delete(self::GLOBAL_COLORS_OPTION, 'options');
        
        // Allow other code to hook into the cache flush
        do_action('td_colors_cache_flushed', $timestamp);
    }
    
    /**
     * AJAX handler for saving global colors
     */
    public function ajax_save_global_colors() {
        // Check nonce
        check_ajax_referer('td_colors_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'td-link')]);
        }
        
        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
        
        // Check if this is just a stock toggle
        if (!empty($id) && isset($_POST['in_stock']) && !isset($_POST['name'])) {
            // This is just a stock toggle, no name or hex needed
            $inStock = (bool)$_POST['in_stock'];
            
            $result = $this->update_global_color($id, [
                'in_stock' => $inStock
            ]);
            
            if ($result) {
                // Flush colors cache
                $this->flush_colors_cache();
                
                wp_send_json_success([
                    'message' => $inStock ? 
                        __('Color marked as in stock', 'td-link') : 
                        __('Color marked as out of stock', 'td-link')
                ]);
            } else {
                wp_send_json_error(['message' => __('Color not found', 'td-link')]);
            }
            
            return;
        }
        
        // Regular color update/creation continues below
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $hex = isset($_POST['hex']) ? sanitize_hex_color($_POST['hex']) : '';
        $red = isset($_POST['red']) ? floatval($_POST['red']) : 0;
        $green = isset($_POST['green']) ? floatval($_POST['green']) : 0;
        $blue = isset($_POST['blue']) ? floatval($_POST['blue']) : 0;
        
        // Validation
        if (empty($name)) {
            wp_send_json_error(['message' => __('Color name is required', 'td-link')]);
        }
        
        if (empty($hex) || $hex[0] !== '#' || strlen($hex) !== 7) {
            wp_send_json_error(['message' => __('Valid hex color code is required', 'td-link')]);
        }
        
        $rgb = [$red, $green, $blue];
        
        // Update or create
        if (!empty($id)) {
            $result = $this->update_global_color($id, [
                'name' => $name,
                'hex' => $hex,
                'rgb' => $rgb
            ]);
            
            if ($result) {
                // Flush colors cache
                $this->flush_colors_cache();
                
                wp_send_json_success(['message' => __('Color updated successfully', 'td-link')]);
            } else {
                wp_send_json_error(['message' => __('Color not found', 'td-link')]);
            }
        } else {
            $id = $this->save_global_color($name, $hex, $rgb);
            
            // Flush colors cache
            $this->flush_colors_cache();
            
            wp_send_json_success([
                'message' => __('Color added successfully', 'td-link'),
                'id' => $id
            ]);
        }
    }
    
    /**
     * AJAX handler for deleting global colors
     */
    public function ajax_delete_global_color() {
        // Check nonce
        check_ajax_referer('td_colors_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'td-link')]);
        }
        
        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
        
        if (empty($id)) {
            wp_send_json_error(['message' => __('Color ID is required', 'td-link')]);
        }
        
        $result = $this->delete_global_color($id);
        
        if ($result) {
            // Flush colors cache when a color is deleted
            $this->flush_colors_cache();
            
            wp_send_json_success(['message' => __('Color deleted successfully', 'td-link')]);
        } else {
            wp_send_json_error(['message' => __('Color not found', 'td-link')]);
        }
    }
    
    /**
     * Format colors for the select field in the product editor
     * 
     * @param bool $include_out_of_stock Whether to include out-of-stock colors
     * @return array Array of formatted colors
     */
    public function get_colors_for_select($include_out_of_stock = false) {
        $colors = $this->get_global_colors();
        $formatted = [];
        
        foreach ($colors as $id => $color) {
            $in_stock = !isset($color['in_stock']) || $color['in_stock'];
            
            // Only include colors that are in stock, unless specifically requested
            if ($in_stock || $include_out_of_stock) {
                $color_data = [
                    'id' => $id,
                    'name' => $color['name'],
                    'hex' => $color['hex'],
                    'rgb' => $color['rgb'],
                    'in_stock' => $in_stock
                ];
                
                $formatted[] = $color_data;
            }
        }
        
        return $formatted;
    }
    /**
     * Convert hex color to RGB values (0-1 range for PolygonJS)
     */
    public function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        
        // Convert to 0-1 range for PolygonJS
        return [
            $r / 255,
            $g / 255,
            $b / 255
        ];
    }
    
    /**
     * Convert RGB array to hex color
     */
    public function rgb_to_hex($rgb) {
        // Convert from 0-1 range to 0-255
        $r = round($rgb[0] * 255);
        $g = round($rgb[1] * 255);
        $b = round($rgb[2] * 255);
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Get the color options string for product parameters
     * 
     * @param bool $include_out_of_stock Whether to include out-of-stock colors
     * @return string Formatted color options string
     */
    public function get_color_options_string($include_out_of_stock = false) {
        $colors = $this->get_colors_for_select($include_out_of_stock);
        $options_string = '';
        
        foreach ($colors as $index => $color) {
            $options_string .= $color['name'] . ';' . $color['hex'];
            
            // IMPORTANT: Always include the stock status flag for ALL colors
            // This is the key change needed
            $in_stock = isset($color['in_stock']) ? $color['in_stock'] : true;
            $options_string .= ';' . ($in_stock ? '1' : '0');
            
            if ($index < count($colors) - 1) {
                $options_string .= '|';
            }
        }
        
        return $options_string;
    }
}