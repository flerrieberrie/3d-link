<?php
/**
 * PolygonJS Parameters Manager
 * 
 * Handles the management of PolygonJS parameters including:
 * - Storing and retrieving HTML snippets
 * - Parsing parameter information from snippets
 * - Providing UI controls in WooCommerce product admin
 * - Converting between WooCommerce UI controls and PolygonJS parameters
 */

defined('ABSPATH') || exit;

class TD_Parameters_Manager {
    /**
     * Sections Manager instance
     */
    private $sections_manager;

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_parameter_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_parameter_fields']);
        
        // Add AJAX handlers for auto-population (admin only)
        add_action('wp_ajax_td_auto_populate_parameter', [$this, 'ajax_auto_populate_parameter']);
        
        // Show debug info after save
        add_action('admin_notices', [$this, 'show_parameter_debug']);

        // Get the sections manager instance
        global $td_link;
        if (isset($td_link) && isset($td_link->sections_manager)) {
            $this->sections_manager = $td_link->sections_manager;
        } else {
            $this->sections_manager = new TD_Sections_Manager();
        }
    }

    /**
     * Add parameter fields to product data metabox
     */
    public function add_parameter_fields() {
        global $post;
        $product_id = $post->ID;
        
        // Only show parameters if PolygonJS is enabled
        if (get_post_meta($product_id, '_enable_polygonjs', true) !== 'yes') {
            return;
        }
        
        echo '<div class="options_group polygonjs-parameters">';
        echo '<h4 style="padding-left: 12px; position: relative;">
                ' . esc_html__('PolygonJS Parameters', 'td-link') . '
                <a href="#" class="td-expand-all" title="' . esc_attr__('Expand All', 'td-link') . '"><span class="dashicons dashicons-arrow-down-alt2"></span></a>
                <a href="#" class="td-collapse-all" title="' . esc_attr__('Collapse All', 'td-link') . '"><span class="dashicons dashicons-arrow-up-alt2"></span></a>
              </h4>';
        
        // Get existing parameters
        $parameters = $this->get_parameters($product_id);
        
        // Display existing parameters
        if (!empty($parameters)) {
            foreach ($parameters as $index => $parameter) {
                $this->render_parameter_fields($index, $parameter);
            }
        }
        
        // Add parameter buttons
        echo '<div class="polygonjs-add-parameter">';
        echo '<button type="button" class="button add-parameter"><span class="dashicons dashicons-plus"></span> ' . esc_html__('Add Parameter', 'td-link') . '</button>';
        
        // RGB Color button
        echo '<button type="button" class="button add-rgb-color" style="margin-left: 10px; background: #9333ea; color: white; border-color: #7e22ce;"><span class="dashicons dashicons-art"></span> ' . esc_html__('Add Color', 'td-link') . '</button>';
        
        echo '</div>';
        
        // Parameter template for JavaScript
        ob_start();
        $this->render_parameter_fields('{{INDEX}}', [
            'html_snippet' => '',
            'display_name' => '',
            'control_type' => 'number',
            'default_value' => '',
            'min' => '',
            'max' => '',
            'step' => '1',
            'max_length' => '20',
            'hide_unit' => false,
            'group_id' => '',
        ], true);
        $template = ob_get_clean();
        
        echo '</div>'; // Close options_group
        
        // Add JavaScript for dynamic parameter fields
        $this->add_parameter_scripts($template);
    }
    
    /**
     * Render fields for a single parameter
     */
    private function render_parameter_fields($index, $parameter, $is_template = false) {
        $html_snippet = isset($parameter['html_snippet']) ? $parameter['html_snippet'] : '';
        $display_name = isset($parameter['display_name']) ? $parameter['display_name'] : '';
        $control_type = isset($parameter['control_type']) ? $parameter['control_type'] : 'number';
        $default_value = isset($parameter['default_value']) ? $parameter['default_value'] : '';
        $min = isset($parameter['min']) ? $parameter['min'] : '';
        $max = isset($parameter['max']) ? $parameter['max'] : '';
        $step = isset($parameter['step']) ? $parameter['step'] : '1';
        $max_length = isset($parameter['max_length']) ? $parameter['max_length'] : '20';
        $dropdown_options = isset($parameter['dropdown_options']) ? $parameter['dropdown_options'] : '';

        // RGB component info (for color groups)
        $is_rgb_component = isset($parameter['is_rgb_component']) ? $parameter['is_rgb_component'] : '';
        $rgb_group = isset($parameter['rgb_group']) ? $parameter['rgb_group'] : '';

        // Helper parameter flag
        $is_helper = isset($parameter['is_helper']) ? (bool)$parameter['is_helper'] : false;

        // Get section (auto-detect if not set)
        $section = isset($parameter['section']) ? $parameter['section'] : '';

        // Force helper parameters to be in the helpers section
        // (This is redundant now as the section dropdown handles it, but keep for backward compatibility)
        if ($is_helper) {
            $section = 'helpers';
        }

        // Extract node_id from HTML snippet if available and not defined
        $node_id = isset($parameter['node_id']) ? $parameter['node_id'] : '';
        if (empty($node_id) && !empty($html_snippet)) {
            preg_match('/id=[\'"]([^\'"]+)[\'"]/', $html_snippet, $matches);
            if (!empty($matches[1])) {
                $node_id = $matches[1];
            }
        }
        
        $style = $is_template ? 'display:none;' : '';
        
        // Special style for hidden RGB components
        if (!empty($is_rgb_component) && $is_rgb_component !== 'r') {
            $style = 'display:none;';
        }
        
        // Set class for parameter
        $classes = 'polygonjs-parameter card';
        if (!empty($is_rgb_component)) {
            $classes .= ' rgb-component rgb-component-' . $is_rgb_component;
        }
        
        echo '<div class="' . esc_attr($classes) . '" style="' . $style . '" data-index="' . $index . '">';
        
        // Parameter header with toggle
        echo '<div class="parameter-header">';
        echo '<div class="parameter-title">';
        echo '<span class="parameter-toggle"><span class="dashicons dashicons-arrow-down-alt2"></span></span>';
        echo '<h4 class="parameter-name">' . esc_html($display_name ? $display_name : ($is_template ? __('Parameter', 'td-link') : __('Parameter', 'td-link'))) . '</h4>';
        echo '</div>';
        echo '<div class="parameter-actions">';
        echo '<button type="button" class="button move-up" title="' . esc_attr__('Move Up', 'td-link') . '"><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
        echo '<button type="button" class="button move-down" title="' . esc_attr__('Move Down', 'td-link') . '"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
        echo '<button type="button" class="button duplicate-parameter" title="' . esc_attr__('Duplicate', 'td-link') . '"><span class="dashicons dashicons-admin-page"></span></button>';
        echo '<button type="button" class="button remove-parameter" title="' . esc_attr__('Remove', 'td-link') . '"><span class="dashicons dashicons-trash"></span></button>';
        echo '</div>';
        echo '</div>';
        
        // Parameter content (collapsible)
        echo '<div class="parameter-content">';

        // Add hidden RGB component data
        if (!empty($is_rgb_component)) {
            echo '<input type="hidden" name="_poly_params[' . $index . '][is_rgb_component]" value="' . esc_attr($is_rgb_component) . '">';
        }
        if (!empty($rgb_group)) {
            echo '<input type="hidden" name="_poly_params[' . $index . '][rgb_group]" value="' . esc_attr($rgb_group) . '">';
        }

        // Helper parameter flag (hidden field - selected via section dropdown)
        echo '<input type="hidden" name="_poly_params[' . $index . '][is_helper]" value="' . ($is_helper ? 'yes' : 'no') . '" class="parameter-is-helper">';
        
        // Group assignment field (V3 - using group_name instead of group_id)
        $group_name = isset($parameter['group_name']) ? $parameter['group_name'] : '';
        echo '<input type="hidden" name="_poly_params[' . $index . '][group_name]" value="' . esc_attr($group_name) . '" class="parameter-group-name">';
        
        // Legacy group_id field for backward compatibility
        $group_id = isset($parameter['group_id']) ? $parameter['group_id'] : '';
        echo '<input type="hidden" name="_poly_params[' . $index . '][group_id]" value="' . esc_attr($group_id) . '" class="parameter-group-id">';
        
        // Parameter section dropdown using WooCommerce field styling
        woocommerce_wp_select([
            'id' => "_poly_params[{$index}][section]",
            'label' => __('Section', 'td-link'),
            'options' => $this->sections_manager->get_sections_as_options($is_helper),
            'value' => $section,
            'desc_tip' => true,
            'description' => __('Group this parameter in a specific section. Selecting "Helpers" will mark this as a helper parameter.', 'td-link'),
            'class' => 'parameter-section-dropdown',
        ]);
        
        // HTML Snippet
        woocommerce_wp_textarea_input([
            'id' => "_poly_params[{$index}][html_snippet]",
            'label' => __('HTML Snippet', 'td-link'),
            'desc_tip' => true,
            'description' => __('Paste the HTML snippet from PolygonJS. This will be parsed to extract parameter information.', 'td-link'),
            'value' => $html_snippet,
            'class' => 'html-snippet-field',
            'custom_attributes' => ['rows' => 5],
        ]);
        
        // Display name
        woocommerce_wp_text_input([
            'id' => "_poly_params[{$index}][display_name]",
            'label' => __('Display Name', 'td-link'),
            'desc_tip' => true,
            'description' => __('Friendly name to show to customers (e.g., "Number of Shelves")', 'td-link'),
            'value' => $display_name,
            'class' => 'display-name-field',
        ]);

        // Parameter section dropdown
        if (empty($section) && !empty($display_name)) {
            // Auto-detect section based on parameter name
            $section = $this->sections_manager->auto_categorize_parameter($parameter);
        }

        // Section dropdown moved to top of form

        // Node ID (hidden) - extracted from HTML snippet
        echo '<input type="hidden" name="_poly_params[' . $index . '][node_id]" value="' . esc_attr($node_id) . '" class="node-id-field">';
        
        // Control type
        $options = [
            'number' => __('Number Input', 'td-link'),
            'slider' => __('Slider', 'td-link'),
            'text' => __('Text Input', 'td-link'),
            'color' => __('Color Selection', 'td-link'),
            'checkbox' => __('Checkbox', 'td-link'),
            'dropdown' => __('Dropdown Select', 'td-link'),
        ];
        
        // Add hidden option only if needed
        if ($control_type === 'hidden') {
            $options['hidden'] = __('Hidden Field', 'td-link');
        }
        
        woocommerce_wp_select([
            'id' => "_poly_params[{$index}][control_type]",
            'label' => __('Control Type', 'td-link'),
            'options' => $options,
            'value' => $control_type,
            'class' => 'control-type-field',
        ]);
        
        // Control settings (show/hide based on control type)
        echo '<div class="control-settings">';
        
        // Common hidden field for all default values to preserve them when switching
        echo '<input type="hidden" class="all-default-values" value="' . esc_attr(json_encode([
            'number' => is_numeric($default_value) ? $default_value : '',
            'text' => $default_value,
            'checkbox' => $default_value === '1' ? 'yes' : 'no',
            'color' => strpos($default_value, '#') === 0 ? $default_value : '#000000',
            'dropdown' => $default_value
        ])) . '">';
        
        // Settings for number/slider
        echo '<div class="settings-number-slider" style="' . (in_array($control_type, ['number', 'slider']) ? '' : 'display:none;') . '">';
        
        // Add option to hide unit display
        $hide_unit = isset($param['hide_unit']) ? $param['hide_unit'] : false;
        woocommerce_wp_checkbox([
            'id' => "_poly_params[{$index}][hide_unit]",
            'label' => __('Hide Unit Display', 'td-link'),
            'desc_tip' => true,
            'description' => __('If checked, measurement units will not be displayed for this parameter.', 'td-link'),
            'value' => $hide_unit ? 'yes' : 'no',
            'cbvalue' => 'yes',
        ]);
        
        // Use unique ID for each field type to avoid conflicts
        woocommerce_wp_text_input([
            'id' => "_poly_params_{$index}_number_default",
            'label' => __('Default Value', 'td-link'),
            'type' => 'text',
            'value' => is_numeric($default_value) ? $default_value : '',
            'class' => 'number-default-value type-specific-field',
            'wrapper_class' => 'form-field',
            'custom_attributes' => ['data-target' => "_poly_params[{$index}][default_value]"],
        ]);
        
        woocommerce_wp_text_input([
            'id' => "_poly_params[{$index}][min]",
            'label' => __('Min Value', 'td-link'),
            'type' => 'text',
            'value' => $min,
        ]);
        
        woocommerce_wp_text_input([
            'id' => "_poly_params[{$index}][max]",
            'label' => __('Max Value', 'td-link'),
            'type' => 'text',
            'value' => $max,
        ]);
        
        woocommerce_wp_text_input([
            'id' => "_poly_params[{$index}][step]",
            'label' => __('Step', 'td-link'),
            'type' => 'text',
            'value' => $step,
            'custom_attributes' => ['step' => '0.01'],
        ]);
        echo '</div>'; // End settings-number-slider
        
        // Settings for text
        echo '<div class="settings-text" style="' . ($control_type === 'text' ? '' : 'display:none;') . '">';
        
        woocommerce_wp_text_input([
            'id' => "_poly_params_{$index}_text_default",
            'label' => __('Default Text', 'td-link'),
            'type' => 'text',
            'value' => $default_value,
            'class' => 'text-default-value type-specific-field',
            'wrapper_class' => 'form-field',
            'custom_attributes' => ['data-target' => "_poly_params[{$index}][default_value]"],
        ]);
        
        woocommerce_wp_text_input([
            'id' => "_poly_params[{$index}][max_length]",
            'label' => __('Max Length', 'td-link'),
            'type' => 'text',
            'value' => $max_length,
        ]);
        echo '</div>'; // End settings-text
        
        // Settings for checkbox
        echo '<div class="settings-checkbox" style="' . ($control_type === 'checkbox' ? '' : 'display:none;') . '">';
        
        woocommerce_wp_checkbox([
            'id' => "_poly_params_{$index}_checkbox_default",
            'label' => __('Default Value', 'td-link'),
            'value' => $default_value === '1' ? 'yes' : 'no',
            'cbvalue' => 'yes',
            'class' => 'checkbox-default-value type-specific-field',
            'wrapper_class' => 'form-field',
            'custom_attributes' => ['data-target' => "_poly_params[{$index}][default_value]"],
        ]);
        echo '</div>'; // End settings-checkbox
        
        // Settings for dropdown
        echo '<div class="settings-dropdown" style="' . ($control_type === 'dropdown' ? '' : 'display:none;') . '">';
        
        woocommerce_wp_text_input([
            'id' => "_poly_params_{$index}_dropdown_default",
            'label' => __('Default Value', 'td-link'),
            'type' => 'text',
            'value' => $default_value,
            'class' => 'dropdown-default-value type-specific-field',
            'wrapper_class' => 'form-field',
            'custom_attributes' => ['data-target' => "_poly_params[{$index}][default_value]"],
        ]);
        
        // Add a field for dropdown options (key=value pairs, one per line)
        woocommerce_wp_textarea_input([
            'id' => "_poly_params[{$index}][dropdown_options]",
            'label' => __('Dropdown Options', 'td-link'),
            'desc_tip' => true,
            'description' => __('Enter options as value=label, one per line. Example: 0=Option One', 'td-link'),
            'value' => $dropdown_options,
            'rows' => 5,
        ]);
        
        echo '</div>'; // End settings-dropdown
        
        // Settings for color - FIXED IMPLEMENTATION WITH COLOR IDS
        echo '<div class="settings-color" style="' . ($control_type === 'color' ? '' : 'display:none;') . '">';

        // Get selected color IDs
        $selected_color_ids = [];
        if (isset($parameter['color_ids']) && !empty($parameter['color_ids'])) {
            $selected_color_ids = is_array($parameter['color_ids']) ? $parameter['color_ids'] : explode(',', $parameter['color_ids']);
        }

        // Get default color ID if set
        $default_color_id = isset($parameter['default_color_id']) ? $parameter['default_color_id'] : '';

        // Legacy format conversion - only needed during transition
        if (empty($selected_color_ids) && isset($parameter['color_options']) && !empty($parameter['color_options'])) {
            // We'll keep this for backward compatibility until all products are updated
            $legacy_colors = explode('|', $parameter['color_options']);
            echo '<input type="hidden" name="_poly_params[' . $index . '][color_options]" value="' . esc_attr($parameter['color_options']) . '">';
        }

        // Add a hidden field to store the selected color IDs
        echo '<input type="hidden" name="_poly_params[' . $index . '][color_ids]" class="selected-color-ids" value="' . esc_attr(is_array($selected_color_ids) ? implode(',', $selected_color_ids) : $selected_color_ids) . '">';

        // Add a hidden field to store the default color ID
        echo '<input type="hidden" name="_poly_params[' . $index . '][default_color_id]" class="default-color-id" value="' . esc_attr($default_color_id) . '">';

        echo '<div class="color-selection-heading">';
        echo '<h4>' . __('Select Available Colors', 'td-link') . '</h4>';
        echo '<p class="description">' . __('Check the colors you want to make available. Select one color as default.', 'td-link') . '</p>';
        echo '<div class="color-selection-actions">';
        echo '<a href="#" class="select-all-colors">' . __('Select All', 'td-link') . '</a> | ';
        echo '<a href="#" class="deselect-all-colors">' . __('Deselect All', 'td-link') . '</a>';
        echo '</div>';
        echo '</div>';

        // Get all global colors
        $colors_manager = new TD_Colors_Manager();
        $colors = $colors_manager->get_global_colors();

        if (empty($colors)) {
            echo '<p class="no-colors-message">' . __('No global colors available. ', 'td-link');
            echo '<a href="' . admin_url('admin.php?page=td-colors') . '">' . __('Add some colors first', 'td-link') . '</a></p>';
        } else {
            // UPDATED COLOR SELECTION GRID - Fixed implementation with default color support
            echo '<style>
                /* Force grid layout and prevent shifting */
                .color-selection-grid {
                    display: grid !important;
                    grid-template-columns: repeat(auto-fill, minmax(100px, 150px)) !important;
                    grid-gap: 8px !important;
                    align-items: start !important;
                }

                /* Ensure each color item maintains its position */
                .color-selection-item {
                    display: flex !important;
                    flex-direction: column !important;
                    padding: 8px !important;
                    border: 1px solid #e5e5e5 !important;
                    border-radius: 4px !important;
                    background: #fff !important;
                    min-height: 50px !important;
                    position: relative !important;
                    box-sizing: border-box !important;
                    margin: 0 !important;
                }

                /* Highlight default color selection */
                .color-selection-item.is-default {
                    border-color: #2271b1 !important;
                    box-shadow: 0 0 0 1px #2271b1 !important;
                    background-color: rgba(34, 113, 177, 0.05) !important;
                }

                /* Keep color items visible */
                .color-selection-item.out-of-stock {
                    opacity: 0.85 !important;
                    background-color: rgba(214, 54, 56, 0.05) !important;
                    border-color: rgba(214, 54, 56, 0.2) !important;
                }

                /* Ensure label alignment */
                .color-selection-item label {
                    display: flex !important;
                    align-items: center !important;
                    cursor: pointer !important;
                    width: 100% !important;
                    margin: 0 !important;
                }

                /* Keep swatches visible */
                .color-selection-item .color-swatch {
                    display: block !important;
                    width: 20px !important;
                    height: 20px !important;
                    min-width: 20px !important;
                    border-radius: 50% !important;
                    margin-right: 8px !important;
                    border: 1px solid #ddd !important;
                    flex-shrink: 0 !important;
                }

                /* Prevent text overflow */
                .color-selection-item .color-name {
                    white-space: nowrap !important;
                    overflow: hidden !important;
                    text-overflow: ellipsis !important;
                    max-width: calc(100% - 30px) !important;
                }

                /* Style out of stock labels */
                .color-selection-item .out-of-stock-label {
                    display: block !important;
                    font-size: 9px !important;
                    color: #d63638 !important;
                    font-weight: 600 !important;
                    margin-top: 4px !important;
                    text-transform: uppercase !important;
                    padding-left: 28px !important;
                }

                /* Default color selection styles */
                .color-selection-item .default-selector {
                    margin-top: 4px !important;
                    display: flex !important;
                    align-items: center !important;
                }

                .color-selection-item .default-selector label {
                    font-size: 10px !important;
                    margin-left: 4px !important;
                    color: #2271b1 !important;
                }

                .color-selection-item.out-of-stock .default-selector {
                    opacity: 0.5 !important;
                    pointer-events: none !important;
                }

                /* Default badge */
                .color-selection-item .default-badge {
                    position: absolute !important;
                    top: -8px !important;
                    right: -5px !important;
                    background: #2271b1 !important;
                    color: white !important;
                    font-size: 9px !important;
                    font-weight: bold !important;
                    padding: 2px 6px !important;
                    border-radius: 3px !important;
                    box-shadow: 0 1px 2px rgba(0,0,0,0.2) !important;
                }
            </style>';
            
            echo '<div class="color-selection-grid">';
            
            // Debug information
            echo '<!-- Total colors: ' . count($colors) . ' -->';
            $color_index = 0;
            
            foreach ($colors as $color_id => $color) {
                // Debug information
                echo '<!-- Color ' . $color_index . ': ' . $color['name'] . ' (ID: ' . $color_id . ') -->';
                
                // Check if this color was previously selected
                // If no colors were previously selected, select all by default
                $is_selected = empty($selected_color_ids) || in_array($color_id, $selected_color_ids);
                $is_in_stock = isset($color['in_stock']) ? (bool)$color['in_stock'] : true;
                
                $is_default = $default_color_id === $color_id;

                $item_class = 'color-selection-item';
                if (!$is_in_stock) {
                    $item_class .= ' out-of-stock';
                }
                if ($is_default) {
                    $item_class .= ' is-default';
                }

                echo '<div class="' . esc_attr($item_class) . '" data-color-id="' . esc_attr($color_id) . '" data-color-index="' . $color_index . '">';

                // Add default badge if this is the default color
                if ($is_default) {
                    echo '<span class="default-badge">' . __('Default', 'td-link') . '</span>';
                }

                echo '<label>';
                echo '<input type="checkbox" name="_poly_params[' . $index . '][color_checkboxes][]" value="' . esc_attr($color_id) . '" ' . ($is_selected ? 'checked="checked"' : '') . ' class="color-checkbox">';
                echo '<span class="color-swatch" style="background-color: ' . esc_attr($color['hex']) . ';"></span>';
                echo '<span class="color-name">' . esc_html($color['name']) . '</span>';
                echo '</label>';

                // Only show out-of-stock label or default selector (not both)
                if (!$is_in_stock) {
                    echo '<span class="out-of-stock-label">' . __('Out of Stock', 'td-link') . '</span>';
                } else {
                    // Add default selector radio button
                    echo '<div class="default-selector">';
                    echo '<input type="radio" name="_poly_params[' . $index . '][default_color_radio]" value="' . esc_attr($color_id) . '" ' . ($is_default ? 'checked="checked"' : '') . ' class="default-color-radio">';
                    echo '<label for="default-color-' . esc_attr($color_id) . '">' . __('Set as Default', 'td-link') . '</label>';
                    echo '</div>';
                }

                echo '</div>';
                $color_index++;
            }
            
            echo '</div>';
        }

        echo '<p class="description">' . __('Select which colors will be available for this parameter. Changes to global colors will automatically be reflected in products.', 'td-link') . '</p>';

        echo '</div>'; // End settings-color
        
        // Settings for hidden fields
        if ($control_type === 'hidden') {
            echo '<div class="settings-hidden">';
            
            woocommerce_wp_text_input([
                'id' => "_poly_params_{$index}_hidden_default",
                'label' => __('Default Value', 'td-link'),
                'type' => 'text',
                'value' => $default_value,
                'class' => 'hidden-default-value type-specific-field',
                'wrapper_class' => 'form-field',
                'custom_attributes' => ['data-target' => "_poly_params[{$index}][default_value]"],
            ]);
            
            echo '</div>'; // End settings-hidden
        }
        
        // Actual hidden field that will be submitted
        echo '<input type="hidden" name="_poly_params[' . $index . '][default_value]" value="' . esc_attr($default_value) . '" class="default-value-combined">';
        
        echo '</div>'; // End control-settings
        
        echo '</div>'; // End parameter-content
        echo '</div>'; // End polygonjs-parameter
    }
    
    /**
     * Add JavaScript for dynamic parameter fields
     */
    private function add_parameter_scripts($template) {
        // JavaScript will be loaded from admin.js via Assets Manager
        
        // Store template in data attribute
        ?>
        <script>
            // Store parameter template for admin.js
            window.parameterTemplate = <?php echo json_encode($template); ?>;

            // Add RGB color functionality
            jQuery(document).ready(function($) {
                
                // Auto-populate parameter fields when HTML snippet changes
                $(document).on('blur', '.html-snippet-field', function() {
                    const $snippet = $(this);
                    const $parameter = $snippet.closest('.polygonjs-parameter');
                    const snippetValue = $snippet.val().trim();
                    
                    if (!snippetValue) return;
                    
                    // Check if fields are already populated (don't overwrite user data)
                    const $displayName = $parameter.find('.display-name-field');
                    const $nodeId = $parameter.find('.node-id-field');
                    
                    if ($displayName.val() && $nodeId.val()) {
                        return; // Already populated, don't auto-populate
                    }
                    
                    // Use the universal parser via AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'td_auto_populate_parameter',
                            html_snippet: snippetValue,
                            nonce: '<?php echo wp_create_nonce('td_parameter_auto_populate'); ?>'
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                const parsed = response.data;
                                
                                // Auto-populate empty fields
                                if (!$displayName.val() && parsed.suggested_display_name) {
                                    $displayName.val(parsed.suggested_display_name).trigger('input');
                                }
                                
                                if (!$nodeId.val() && parsed.node_id) {
                                    $nodeId.val(parsed.node_id);
                                }
                                
                                // Update control type if not set
                                const $controlType = $parameter.find('.control-type-field');
                                if (!$controlType.val() || $controlType.val() === 'number') {
                                    $controlType.val(parsed.control_type).trigger('change');
                                }
                                
                                // Auto-populate min/max/step for numeric controls
                                if (parsed.control_type === 'number' || parsed.control_type === 'slider') {
                                    if (parsed.min) $parameter.find('input[name*="[min]"]').val(parsed.min);
                                    if (parsed.max) $parameter.find('input[name*="[max]"]').val(parsed.max);
                                    if (parsed.step) $parameter.find('input[name*="[step]"]').val(parsed.step);
                                    if (parsed.value) $parameter.find('.number-default-value').val(parsed.value);
                                }
                                
                                // Show success feedback
                                $snippet.css('border-color', '#46b450').css('background-color', '#f7fff7');
                                setTimeout(function() {
                                    $snippet.css('border-color', '').css('background-color', '');
                                }, 2000);
                            }
                        },
                        error: function() {
                            // Show error feedback
                            $snippet.css('border-color', '#dc3232').css('background-color', '#ffeaea');
                            setTimeout(function() {
                                $snippet.css('border-color', '').css('background-color', '');
                            }, 2000);
                        }
                    });
                });
                // Global RGB counter for unique group IDs
                let rgbCounter = 0;

                // Handle section dropdown changes to update helper status
                $(document).on('change', '.parameter-section-dropdown', function() {
                    const $dropdown = $(this);
                    const $parameterContent = $dropdown.closest('.parameter-content');
                    const $helperField = $parameterContent.find('.parameter-is-helper');
                    
                    // Check if selected option is the helpers section
                    const isHelper = $dropdown.val() === 'helpers';
                    
                    // Update the hidden helper field value
                    $helperField.val(isHelper ? 'yes' : 'no');
                });

                // Handle default color selection
                $(document).on('change', '.default-color-radio', function() {
                    const $radio = $(this);
                    const colorId = $radio.val();
                    const $parameter = $radio.closest('.parameter-content');

                    // Update the hidden default color ID field
                    $parameter.find('.default-color-id').val(colorId);

                    // Remove default styling from all items
                    $parameter.find('.color-selection-item').removeClass('is-default');
                    $parameter.find('.default-badge').remove();

                    // Add default styling to the selected item
                    const $selectedItem = $radio.closest('.color-selection-item');
                    $selectedItem.addClass('is-default');

                    // Add default badge if it doesn't exist
                    if ($selectedItem.find('.default-badge').length === 0) {
                        $selectedItem.prepend('<span class="default-badge">' + <?php echo json_encode(__('Default', 'td-link')); ?> + '</span>');
                    }
                });

                // Make sure default option is checked when a new color is selected
                $(document).on('change', '.color-checkbox', function() {
                    const $checkbox = $(this);
                    const isChecked = $checkbox.prop('checked');
                    const $item = $checkbox.closest('.color-selection-item');
                    const $radio = $item.find('.default-color-radio');

                    // If unchecking a color that is default, we need to clear the default
                    if (!isChecked && $item.hasClass('is-default')) {
                        $radio.prop('checked', false);
                        $item.removeClass('is-default');
                        $item.find('.default-badge').remove();

                        // Clear the default color ID
                        $checkbox.closest('.parameter-content').find('.default-color-id').val('');
                    }

                    // If it's the first checked color and we have no default yet, auto-select it
                    if (isChecked) {
                        const $parameter = $checkbox.closest('.parameter-content');
                        const hasDefault = $parameter.find('.color-selection-item.is-default').length > 0;

                        if (!hasDefault) {
                            // Count checked checkboxes
                            const checkedCount = $parameter.find('.color-checkbox:checked').length;

                            // If this is the first checked box, make it default
                            if (checkedCount === 1) {
                                $radio.prop('checked', true).trigger('change');
                            }
                        }
                    }
                });

                // Handle RGB Color button click
                $('.add-rgb-color').on('click', function() {
                    // Generate a unique RGB group ID
                    const rgbGroupId = 'rgb-' + Date.now();
                    const paramCounter = $('.polygonjs-parameter').length;

                    // Add R component
                    const rTemplate = parameterAddTemplate(paramCounter, 'R');
                    $(this).closest('.polygonjs-add-parameter').before(rTemplate);

                    // Add G component (hidden)
                    const gTemplate = parameterAddTemplate(paramCounter + 1, 'G');
                    $(this).closest('.polygonjs-add-parameter').before(gTemplate);
                    $('.polygonjs-parameter').last().hide();

                    // Add B component (hidden)
                    const bTemplate = parameterAddTemplate(paramCounter + 2, 'B');
                    $(this).closest('.polygonjs-add-parameter').before(bTemplate);
                    $('.polygonjs-parameter').last().hide();

                    // Set up the parameters
                    setupRGBParameters(rgbGroupId);

                    // Reinitialize tooltips
                    if (typeof woocommerce_admin_meta_boxes !== 'undefined' &&
                        typeof woocommerce_admin_meta_boxes.init_tiptip === 'function') {
                        woocommerce_admin_meta_boxes.init_tiptip();
                    }

                    // Update R component to have a meaningful name
                    $('.polygonjs-parameter').eq(paramCounter).find('.display-name-field').val('Product Color').trigger('input');
                });

                // Helper function to create a parameter template with RGB snippets
                function parameterAddTemplate(index, component) {
                    let template = window.parameterTemplate;

                    // Replace index placeholder
                    template = template.replace(/\{\{INDEX\}\}/g, index);

                    return template;
                }

                // Set up RGB parameter values and connections
                function setupRGBParameters(rgbGroupId) {
                    const parameters = $('.polygonjs-parameter').slice(-3);

                    // Set R component
                    parameters.eq(0).addClass('rgb-component rgb-component-r');
                    parameters.eq(0).find('.html-snippet-field').val('<label for="geo1-MAT-meshBasicBuilder1-constant1-colorr">geo1-MAT-meshBasicBuilder1-constant1-colorr</label><input type="number" id="geo1-MAT-meshBasicBuilder1-constant1-colorr" name="geo1-MAT-meshBasicBuilder1-constant1-colorr" min=0 max=1 step=0.01 value=1></input>').trigger('change');
                    parameters.eq(0).find('.control-type-field').val('color').trigger('change');
                    parameters.eq(0).append('<input type="hidden" name="_poly_params[' + parameters.eq(0).data('index') + '][is_rgb_component]" value="r">');
                    parameters.eq(0).append('<input type="hidden" name="_poly_params[' + parameters.eq(0).data('index') + '][rgb_group]" value="' + rgbGroupId + '">');

                    // Set G component
                    parameters.eq(1).addClass('rgb-component rgb-component-g');
                    parameters.eq(1).find('.html-snippet-field').val('<label for="geo1-MAT-meshBasicBuilder1-constant1-colorg">geo1-MAT-meshBasicBuilder1-constant1-colorg</label><input type="number" id="geo1-MAT-meshBasicBuilder1-constant1-colorg" name="geo1-MAT-meshBasicBuilder1-constant1-colorg" min=0 max=1 step=0.01 value=0></input>').trigger('change');
                    parameters.eq(1).find('.display-name-field').val('Color (Green Channel)').trigger('input');
                    parameters.eq(1).find('.control-type-field').append('<option value="hidden">Hidden Field</option>').val('hidden').trigger('change');
                    parameters.eq(1).append('<input type="hidden" name="_poly_params[' + parameters.eq(1).data('index') + '][is_rgb_component]" value="g">');
                    parameters.eq(1).append('<input type="hidden" name="_poly_params[' + parameters.eq(1).data('index') + '][rgb_group]" value="' + rgbGroupId + '">');

                    // Set B component
                    parameters.eq(2).addClass('rgb-component rgb-component-b');
                    parameters.eq(2).find('.html-snippet-field').val('<label for="geo1-MAT-meshBasicBuilder1-constant1-colorb">geo1-MAT-meshBasicBuilder1-constant1-colorb</label><input type="number" id="geo1-MAT-meshBasicBuilder1-constant1-colorb" name="geo1-MAT-meshBasicBuilder1-constant1-colorb" min=0 max=1 step=0.01 value=0></input>').trigger('change');
                    parameters.eq(2).find('.display-name-field').val('Color (Blue Channel)').trigger('input');
                    parameters.eq(2).find('.control-type-field').append('<option value="hidden">Hidden Field</option>').val('hidden').trigger('change');
                    parameters.eq(2).append('<input type="hidden" name="_poly_params[' + parameters.eq(2).data('index') + '][is_rgb_component]" value="b">');
                    parameters.eq(2).append('<input type="hidden" name="_poly_params[' + parameters.eq(2).data('index') + '][rgb_group]" value="' + rgbGroupId + '">');
                }
            });
        </script>
        <?php
    }
    
    /**
     * Save parameter fields
     */
    public function save_parameter_fields($post_id) {
        if (isset($_POST['_poly_params'])) {
            $parameters = [];
            
            foreach ($_POST['_poly_params'] as $param) {
                // Use universal parser to auto-populate missing fields
                $auto_populated = TD_Universal_Parameter_Parser::auto_populate_parameter($param, $param['html_snippet'] ?? '');
                
                // Basic sanitization - preserve all fields from auto_populated
                $parameter = [
                    'html_snippet' => isset($auto_populated['html_snippet']) ? $auto_populated['html_snippet'] : '',
                    'display_name' => sanitize_text_field($auto_populated['display_name'] ?? ''),
                    'control_type' => sanitize_text_field($auto_populated['control_type'] ?? 'number'),
                    'node_id' => sanitize_text_field($auto_populated['node_id'] ?? ''),
                    'default_value' => isset($auto_populated['default_value']) ? $auto_populated['default_value'] : '',
                    'section' => sanitize_text_field($auto_populated['section'] ?? ''),
                    'group_id' => isset($param['group_id']) ? sanitize_text_field($param['group_id']) : '',
                    'group_name' => isset($param['group_name']) ? sanitize_text_field($param['group_name']) : '',
                ];
                
                // RGB component info
                if (isset($param['is_rgb_component'])) {
                    $parameter['is_rgb_component'] = sanitize_text_field($param['is_rgb_component']);
                }

                if (isset($param['rgb_group'])) {
                    $parameter['rgb_group'] = sanitize_text_field($param['rgb_group']);
                }

                // Helper parameter flag
                $parameter['is_helper'] = isset($param['is_helper']) && $param['is_helper'] === 'yes';
                
                // Control-specific settings
                switch ($param['control_type']) {
                    case 'number':
                    case 'slider':
                        $parameter['min'] = isset($param['min']) && $param['min'] !== '' ? floatval($param['min']) : '';
                        $parameter['max'] = isset($param['max']) && $param['max'] !== '' ? floatval($param['max']) : '';
                        $parameter['step'] = isset($param['step']) && $param['step'] !== '' ? floatval($param['step']) : 1;
                        $parameter['hide_unit'] = isset($param['hide_unit']) && $param['hide_unit'] === 'yes';
                        break;
                        
                    case 'text':
                        $parameter['max_length'] = sanitize_text_field($param['max_length'] ?? '20');
                        break;
                        
                    case 'color':
                        // Save legacy format for backward compatibility
                        $parameter['color_options'] = isset($param['color_options']) ? $param['color_options'] : '';

                        // Save the selected color IDs
                        if (isset($param['color_ids'])) {
                            $parameter['color_ids'] = sanitize_text_field($param['color_ids']);
                        } else if (isset($param['color_checkboxes']) && is_array($param['color_checkboxes'])) {
                            // If we have checkbox values but no color_ids, create the color_ids from checkboxes
                            $selected_ids = array_map('sanitize_text_field', $param['color_checkboxes']);
                            $parameter['color_ids'] = implode(',', $selected_ids);
                        }

                        // Save the default color ID
                        if (isset($param['default_color_id'])) {
                            $parameter['default_color_id'] = sanitize_text_field($param['default_color_id']);
                        } else if (isset($param['default_color_radio'])) {
                            // If we have a radio button selection, use that
                            $parameter['default_color_id'] = sanitize_text_field($param['default_color_radio']);
                        }
                        break;
                        
                    case 'dropdown':
                        $parameter['dropdown_options'] = isset($param['dropdown_options']) ? $param['dropdown_options'] : '';
                        break;
                }
                
                $parameters[] = $parameter;
            }
            
            // Temporary debug: Log to JavaScript console via admin notice
            $debug_info = [];
            foreach ($parameters as $idx => $param) {
                $debug_info[] = "Param {$idx} ({$param['display_name']}): group_id = " . ($param['group_id'] ?? 'none');
            }
            
            if (!empty($debug_info)) {
                set_transient('td_parameter_debug_' . get_current_user_id(), implode('<br>', $debug_info), 30);
            }
            
            update_post_meta($post_id, '_poly_parameters', $parameters);
        }
    }
    
    /**
     * Get all parameters for a product
     */
    public function get_parameters($product_id) {
        $parameters = get_post_meta($product_id, '_poly_parameters', true);
        return is_array($parameters) ? $parameters : [];
    }
    
    /**
     * Get a specific parameter by node ID
     */
    public function get_parameter_by_node_id($product_id, $node_id) {
        $parameters = $this->get_parameters($product_id);
        
        foreach ($parameters as $parameter) {
            if ($parameter['node_id'] === $node_id) {
                return $parameter;
            }
        }
        
        return null;
    }
    
    /**
     * Get parameters by RGB group ID
     */
    public function get_parameters_by_rgb_group($product_id, $rgb_group) {
        $parameters = $this->get_parameters($product_id);
        $result = [];
        
        foreach ($parameters as $parameter) {
            if (isset($parameter['rgb_group']) && $parameter['rgb_group'] === $rgb_group) {
                $component = $parameter['is_rgb_component'] ?? '';
                if ($component) {
                    $result[$component] = $parameter;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Generate dynamic JavaScript controls for a product using universal parser
     * 
     * @param int $product_id Product ID
     * @return string JavaScript code
     */
    public function generate_dynamic_controls_js($product_id) {
        $parameters = $this->get_parameters($product_id);
        return TD_Universal_Parameter_Parser::generate_dynamic_controls_js($parameters);
    }
    
    /**
     * Get universal parameter mapping for a product
     * 
     * @param int $product_id Product ID
     * @return array Universal mapping structure
     */
    public function get_universal_mapping($product_id) {
        $parameters = $this->get_parameters($product_id);
        return TD_Universal_Parameter_Parser::create_universal_mapping($parameters);
    }
    
    /**
     * Validate all parameters for a product
     * 
     * @param int $product_id Product ID
     * @return array Validation results
     */
    public function validate_product_parameters($product_id) {
        $parameters = $this->get_parameters($product_id);
        return TD_Universal_Parameter_Parser::validate_parameters($parameters);
    }
    
    /**
     * Auto-populate parameter from HTML snippet (AJAX handler)
     */
    public function ajax_auto_populate_parameter() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'td_parameter_auto_populate')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $html_snippet = stripslashes($_POST['html_snippet'] ?? '');
        
        if (empty($html_snippet)) {
            wp_send_json_error('No HTML snippet provided');
            return;
        }
        
        $parsed = TD_Universal_Parameter_Parser::parse_html_snippet($html_snippet);
        
        if ($parsed) {
            wp_send_json_success($parsed);
        } else {
            wp_send_json_error('Could not parse HTML snippet');
        }
    }
    
    /**
     * Show parameter debug info after save
     */
    public function show_parameter_debug() {
        $debug_info = get_transient('td_parameter_debug_' . get_current_user_id());
        if ($debug_info) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>Parameter Save Debug:</strong><br>' . $debug_info . '</p>';
            echo '</div>';
            delete_transient('td_parameter_debug_' . get_current_user_id());
        }
    }
}