<?php
/**
 * PolygonJS Sections Manager
 * 
 * Handles the management of parameter sections:
 * - Stores and retrieves section metadata
 * - Provides default sections with auto-categorization
 * - Allows custom section assignments
 * - Manages section display order
 */

defined('ABSPATH') || exit;

class TD_Sections_Manager {
    /**
     * Default sections with their display info
     */
    public $default_sections = [
        'dimensions' => [
            'id' => 'dimensions',
            'name' => 'Dimensions',
            'slug' => 'dimensions',
            'order' => 10,
        ],
        'appearance' => [
            'id' => 'appearance',
            'name' => 'Appearance',
            'slug' => 'appearance',
            'order' => 20,
        ],
        'features' => [
            'id' => 'features',
            'name' => 'Features',
            'slug' => 'features',
            'order' => 30,
        ],
        'other' => [
            'id' => 'other',
            'name' => 'Other Parameters',
            'slug' => 'other',
            'order' => 40,
        ],
        'helpers' => [
            'id' => 'helpers',
            'name' => 'Helpers',
            'slug' => 'helpers',
            'order' => 50,
            'is_helper' => true, // Special flag for helper section
        ],
    ];

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('td_link_settings', 'td_parameter_sections');
    }

    /**
     * Get all sections (both default and custom)
     */
    public function get_sections() {
        $saved_sections = get_option('td_parameter_sections', []);
        $sections = $this->default_sections;

        // Merge saved section data with defaults
        if (!empty($saved_sections) && is_array($saved_sections)) {
            foreach ($saved_sections as $section_id => $section) {
                // If it's a default section, update the properties but keep it in the default order
                if (isset($sections[$section_id])) {
                    $sections[$section_id]['name'] = $section['name'];
                    $sections[$section_id]['order'] = $section['order'];
                } else {
                    // Add custom section
                    $sections[$section_id] = $section;
                }
            }
        }

        // Sort sections by order
        uasort($sections, function($a, $b) {
            return $a['order'] - $b['order'];
        });

        return $sections;
    }

    /**
     * Save sections data
     */
    public function save_sections($sections) {
        update_option('td_parameter_sections', $sections);
    }

    /**
     * Auto-categorize a parameter based on its name
     */
    public function auto_categorize_parameter($parameter) {
        $lc_name = strtolower($parameter['display_name']);

        // Check for helper parameters first
        if (strpos($lc_name, 'helper') !== false ||
            strpos($lc_name, 'show ') === 0 ||
            strpos($lc_name, 'hide ') === 0 ||
            strpos($lc_name, 'toggle') !== false ||
            strpos($lc_name, 'guide') !== false ||
            strpos($lc_name, 'indicator') !== false ||
            strpos($lc_name, 'reference') !== false ||
            strpos($lc_name, 'visual') !== false) {
            return 'helpers';
        }

        // Dimensions/sizing parameters
        if (strpos($lc_name, 'size') !== false ||
            strpos($lc_name, 'height') !== false ||
            strpos($lc_name, 'width') !== false ||
            strpos($lc_name, 'depth') !== false ||
            strpos($lc_name, 'length') !== false ||
            strpos($lc_name, 'diameter') !== false ||
            strpos($lc_name, 'radius') !== false) {
            return 'dimensions';
        }

        // Appearance parameters
        if (strpos($lc_name, 'color') !== false ||
            strpos($lc_name, 'material') !== false ||
            strpos($lc_name, 'finish') !== false ||
            strpos($lc_name, 'texture') !== false ||
            strpos($lc_name, 'style') !== false) {
            return 'appearance';
        }

        // Feature parameters
        if (strpos($lc_name, 'shelves') !== false ||
            strpos($lc_name, 'drawers') !== false ||
            strpos($lc_name, 'doors') !== false ||
            strpos($lc_name, 'handles') !== false ||
            strpos($lc_name, 'legs') !== false ||
            strpos($lc_name, 'count') !== false ||
            strpos($lc_name, 'number of') !== false) {
            return 'features';
        }

        // Default to "other" for anything else
        return 'other';
    }

    /**
     * Get sections as options array for WooCommerce select field
     * 
     * @param bool $is_helper Whether this parameter is marked as a helper
     * @return array Options array for woocommerce_wp_select
     */
    public function get_sections_as_options($is_helper = false) {
        $sections = $this->get_sections();
        $options = [];
        
        foreach ($sections as $section_id => $section) {
            $option_text = $section['name'];
            
            // Mark helper sections with an indicator
            if (isset($section['is_helper']) && $section['is_helper'] === true) {
                $option_text .= ' (Helper)'; 
            }
            
            $options[$section_id] = $option_text;
        }
        
        return $options;
    }

    /**
     * Get section HTML dropdown for admin UI
     * 
     * @param string $selected_section Currently selected section
     * @param string $field_name Name of the form field
     * @param bool $is_helper Whether this parameter is marked as a helper
     * @return string HTML dropdown
     */
    public function get_section_dropdown($selected_section = '', $field_name = '', $is_helper = false) {
        $sections = $this->get_sections();
        $html = '<select name="' . esc_attr($field_name) . '" class="parameter-section-dropdown">';
        
        foreach ($sections as $section_id => $section) {
            // Set selected state - either by matching section_id or if this is a helper parameter and we're on the helpers section
            $is_selected = ($selected_section === $section_id) || ($is_helper && $section_id === 'helpers');
            
            $html .= '<option value="' . esc_attr($section_id) . '"' . selected($is_selected, true, false);
            
            // Mark helper sections with a data attribute for JS to use
            if (isset($section['is_helper']) && $section['is_helper'] === true) {
                $html .= ' data-is-helper="true"';
            }
            
            $html .= '>' . esc_html($section['name']) . '</option>';
        }
        
        $html .= '</select>';
        return $html;
    }

    /**
     * Gets the section name for a given section ID
     */
    public function get_section_name($section_id) {
        $sections = $this->get_sections();
        return isset($sections[$section_id]) ? $sections[$section_id]['name'] : '';
    }

    /**
     * Checks if a section is a helper section
     */
    public function is_helper_section($section_id) {
        $sections = $this->get_sections();
        return isset($sections[$section_id]) && isset($sections[$section_id]['is_helper']) && $sections[$section_id]['is_helper'] === true;
    }

    /**
     * Add a new custom section
     *
     * @param string $name Section name
     * @param array $args Optional arguments for section creation
     * @return string The ID of the created section
     */
    public function add_section($name, $args = []) {
        $sections = get_option('td_parameter_sections', []);
        $section_id = sanitize_title($name);

        // Find the highest order value
        $max_order = 0;
        foreach ($this->get_sections() as $section) {
            if ($section['order'] > $max_order) {
                $max_order = $section['order'];
            }
        }

        // Check if this is a helper section
        $is_helper = isset($args['is_helper']) && $args['is_helper'] === true;

        // Add the new section with order value after the highest
        $sections[$section_id] = [
            'id' => $section_id,
            'name' => $name,
            'slug' => $section_id,
            'order' => $max_order + 10,
            'is_helper' => $is_helper,
        ];

        $this->save_sections($sections);
        return $section_id;
    }

    /**
     * Update a section
     */
    public function update_section($section_id, $name, $order) {
        $sections = get_option('td_parameter_sections', []);

        // If updating a default section that hasn't been customized yet
        if (!isset($sections[$section_id]) && isset($this->default_sections[$section_id])) {
            $sections[$section_id] = $this->default_sections[$section_id];
        }

        if (isset($sections[$section_id])) {
            // Preserve the helper flag if it exists
            $is_helper = isset($sections[$section_id]['is_helper']) ? $sections[$section_id]['is_helper'] : false;

            $sections[$section_id]['name'] = $name;
            $sections[$section_id]['order'] = intval($order);

            // Make sure helper flag is maintained
            $sections[$section_id]['is_helper'] = $is_helper;

            $this->save_sections($sections);
            return true;
        }

        return false;
    }

    /**
     * Delete a custom section (default sections cannot be deleted)
     */
    public function delete_section($section_id) {
        // Can't delete default sections
        if (isset($this->default_sections[$section_id])) {
            return false;
        }
        
        $sections = get_option('td_parameter_sections', []);
        
        if (isset($sections[$section_id])) {
            unset($sections[$section_id]);
            $this->save_sections($sections);
            
            // Also need to update any parameters that used this section
            $this->reassign_parameters_from_deleted_section($section_id);
            
            return true;
        }
        
        return false;
    }

    /**
     * Reassign parameters from a deleted section to "other"
     */
    private function reassign_parameters_from_deleted_section($deleted_section_id) {
        global $wpdb;
        
        // Get all products with parameters
        $products = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_poly_parameters' 
            AND meta_value != ''"
        );
        
        if (empty($products)) {
            return;
        }
        
        foreach ($products as $product_id) {
            $parameters = get_post_meta($product_id, '_poly_parameters', true);
            $updated = false;
            
            if (!empty($parameters) && is_array($parameters)) {
                foreach ($parameters as $key => $parameter) {
                    if (isset($parameter['section']) && $parameter['section'] === $deleted_section_id) {
                        $parameters[$key]['section'] = 'other';
                        $updated = true;
                    }
                }
                
                if ($updated) {
                    update_post_meta($product_id, '_poly_parameters', $parameters);
                }
            }
        }
    }
}