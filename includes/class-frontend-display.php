<?php
/**
 * Frontend Display Handler for PolygonJS Parameters
 * 
 * Handles displaying parameters as UI controls on product pages
 * and integrates with the PolygonJS scene
 */

defined('ABSPATH') || exit;

class TD_Frontend_Display {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Clean system - no need to interfere with WooCommerce variation forms
        
        // Add our unified customizer interface
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_unified_customizer'], 5);
        
        add_action('wp_head', [$this, 'inject_admin_preview_script']);
        add_action('wp_footer', [$this, 'add_debug_info']);
        
        // Measurement unit setting
        $this->measurement_unit = get_option('td_measurement_unit', 'cm');
        
        // Make sure it's not empty
        if (empty($this->measurement_unit)) {
            $this->measurement_unit = 'cm'; // Default fallback
            
            // Try to save the default if it doesn't exist
            add_option('td_measurement_unit', 'cm');
        }
    }
    
    
    /**
     * Add debug information to the footer
     */
    public function add_debug_info() {
        if (!isset($_GET['debug'])) return;
        
        echo '<!-- TD Link Debug Info -->
        <!-- Measurement Unit: ' . esc_html($this->measurement_unit) . ' -->
        <!-- This info should be visible in the browser source code -->';
        
        // Only show debug panel when explicitly requested with ?debug=1
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            ?>
            <div style="position:fixed; top:10px; right:10px; z-index:99999; background:white; border:2px solid #333; padding:10px; font-size:12px; max-width:300px; box-shadow:0 0 10px rgba(0,0,0,0.3);">
                <h4 style="margin:0 0 5px 0; font-size:14px;">TD Link Unit Debug</h4>
                <div>Current unit: <strong><?php echo !empty($this->measurement_unit) ? esc_html($this->measurement_unit) : 'NOT SET!'; ?></strong></div>
                <button onclick="document.querySelectorAll('.measurement-unit').forEach(el => { el.style.backgroundColor = 'red'; el.style.color = 'white'; });">Highlight Units</button>
                <button onclick="this.parentNode.style.display = 'none';">Close</button>
            </div>
            <?php
        }
    }
    
    /**
     * Get the measurement unit for dimensional parameters
     */
    private function get_measurement_unit() {
        // ALWAYS ensure we have a measurement unit, fallback to cm if not set
        if (empty($this->measurement_unit)) {
            $this->measurement_unit = 'cm';
        }
        
        return $this->measurement_unit;
    }
    
    /**
     * Check for admin preview script parameter and inject it if needed
     */
    public function inject_admin_preview_script() {
        if (isset($_GET['admin_preview']) && isset($_GET['script'])) {
            $script_url = esc_url($_GET['script']);
            echo '<script src="' . $script_url . '"></script>';
        }
        
        // Inject universal controls for any product with PolygonJS enabled
        if (is_product()) {
            global $product;
            if ($product && get_post_meta($product->get_id(), '_enable_polygonjs', true) === 'yes') {
                TD_Universal_Frontend_Handler::inject_universal_controls($product->get_id());
            }
        }
    }
    
    /**
     * Display unified customizer interface
     */
    public function display_unified_customizer() {
        global $product;
        if (!$product || !is_a($product, 'WC_Product')) {
            $product = wc_get_product(get_the_ID());
        }
        if (!$product) return;
        
        // Only proceed if PolygonJS is enabled for this product
        if (get_post_meta($product->get_id(), '_enable_polygonjs', true) !== 'yes') return;
        
        // Start unified customizer wrapper
        echo '<div class="td-unified-customizer">';
        
        
        // Display parameters
        $this->display_parameters();
        
        echo '</div>'; // End unified customizer
    }
    
    /**
     * Display parameters as UI controls
     */
    public function display_parameters() {
        global $product;
        if (!$product || !is_a($product, 'WC_Product')) {
            $product = wc_get_product(get_the_ID());
        }
        if (!$product) return;

        $product_id = $product->get_id();

        // Only proceed if PolygonJS is enabled for this product
        if (get_post_meta($product_id, '_enable_polygonjs', true) !== 'yes') return;
        
        // Get parameters if not defined yet
        if (!isset($parameters)) {
            $parameters_manager = new TD_Parameters_Manager();
            $parameters = $parameters_manager->get_parameters($product_id);
        }
        
        // Add index to each parameter
        if (is_array($parameters)) {
            foreach ($parameters as $index => &$param) {
                $param['index'] = $index;
            }
        }
        
        // Collect all parameters with hidden units
        $parameters_with_hidden_units = [];
        if (is_array($parameters)) {
            foreach ($parameters as $param) {
                if (isset($param['hide_unit']) && $param['hide_unit'] && !empty($param['node_id'])) {
                    $parameters_with_hidden_units[] = $param['node_id'];
                }
            }
        }
        
        // Convert to JSON for JavaScript
        $hidden_units_json = json_encode($parameters_with_hidden_units);
        
        // Get universal mapping for this product
        $parameters_manager = new TD_Parameters_Manager();
        $universal_mapping = $parameters_manager->get_universal_mapping($product_id);
        ?>
        <script>
        // Initialize the global tdPolygonjs object with comprehensive data
        if (!window.tdPolygonjs) window.tdPolygonjs = {};
        window.tdPolygonjs.measurementUnit = "<?php echo esc_js($this->measurement_unit); ?>";
        window.tdPolygonjs.parametersWithHiddenUnits = <?php echo $hidden_units_json; ?>;
        window.tdPolygonjs.productId = <?php echo json_encode($product_id); ?>;
        window.tdPolygonjs.parameters = <?php echo json_encode($parameters); ?>;
        
        // Add universal mapping data
        window.tdPolygonjs.universalMapping = <?php echo json_encode($universal_mapping, JSON_PRETTY_PRINT); ?>;
        
        // Make RGB groups available (legacy compatibility)
        if (window.tdPolygonjs.universalMapping && window.tdPolygonjs.universalMapping.rgb_groups) {
            window.tdPolygonjs.rgbGroups = window.tdPolygonjs.universalMapping.rgb_groups;
        }
        
        // Store color options if available
        <?php
        $colors_manager = new TD_Colors_Manager();
        $colors = $colors_manager->get_global_colors();
        $color_options = [];
        foreach ($colors as $color_id => $color) {
            $color_key = strtolower(str_replace(' ', '-', $color['name']));
            $color_options[$color_key] = [
                'name' => $color['name'],
                'hex' => $color['hex'],
                'rgb' => $color['rgb'],
                'in_stock' => $color['in_stock'] ?? true
            ];
        }
        ?>
        window.tdPolygonjs.colorOptions = <?php echo json_encode($color_options); ?>;
        
        console.log('[TD Frontend] Initialized universal system with:', {
            productId: window.tdPolygonjs.productId,
            parametersCount: Object.keys(window.tdPolygonjs.parameters || {}).length,
            mappingKeys: Object.keys(window.tdPolygonjs.universalMapping?.node_mappings || {}),
            rgbGroups: Object.keys(window.tdPolygonjs.rgbGroups || {}),
            colorOptions: Object.keys(window.tdPolygonjs.colorOptions || {})
        });
        </script>
        <?php

        // Get parameters
        $parameters_manager = new TD_Parameters_Manager();
        $parameters = $parameters_manager->get_parameters($product_id);

        // If no parameters, don't display anything
        if (empty($parameters)) return;

        // Track RGB groups to avoid displaying hidden components
        $rgb_groups = [];
        foreach ($parameters as $param) {
            if (!empty($param['is_rgb_component']) && !empty($param['rgb_group'])) {
                if (!isset($rgb_groups[$param['rgb_group']])) {
                    $rgb_groups[$param['rgb_group']] = [];
                }
                $rgb_groups[$param['rgb_group']][$param['is_rgb_component']] = $param;
            }
        }

        // Start output - Main product parameters
        echo '<div class="td-product-customizer" data-product-id="' . esc_attr($product_id) . '">';
        echo '<h3>' . esc_html__('Customization Options', 'td-link') . '</h3>';

        // Get sections manager
        global $td_link;
        $sections_manager = isset($td_link) && isset($td_link->sections_manager)
            ? $td_link->sections_manager
            : new TD_Sections_Manager();

        // Get all available sections
        $sections = $sections_manager->get_sections();

        // Initialize grouped parameters with all defined sections
        $grouped_params = [];
        $helper_params = []; // Special array just for helper parameters
        foreach ($sections as $section_id => $section) {
            $grouped_params[$section_id] = [];
        }

        // Sort parameters into groups
        foreach ($parameters as $param) {
            if (empty($param['node_id']) || empty($param['control_type'])) continue;

            // Skip hidden components of RGB groups (only show the main R component)
            if (!empty($param['is_rgb_component']) && $param['is_rgb_component'] !== 'r') {
                continue;
            }

            // Skip hidden controls
            if ($param['control_type'] === 'hidden') {
                continue;
            }

            $node_id = $param['node_id'];
            $display_name = !empty($param['display_name']) ? $param['display_name'] : $node_id;

            // Special handling for helper parameters
            $is_helper = isset($param['is_helper']) && $param['is_helper'];

            if ($is_helper) {
                // Store helper params separately
                $helper_params[] = $param;
                continue; // Skip adding to regular groups
            }

            // Get parameter section (either explicitly set or auto-detected)
            $section_id = isset($param['section']) && !empty($param['section'])
                ? $param['section']
                : $sections_manager->auto_categorize_parameter($param);

            // Skip helper section parameters from main display
            if ($sections_manager->is_helper_section($section_id)) {
                $helper_params[] = $param;
                continue;
            }

            // Make sure section exists (fallback to other if not)
            if (!isset($grouped_params[$section_id])) {
                $section_id = 'other';
            }

            $grouped_params[$section_id][] = $param;
        }

        
        // LEGACY: Still display section-based groups for backwards compatibility
        // Display each section group if it has parameters
        $group_titles = [];
        foreach ($sections as $section_id => $section) {
            $group_titles[$section_id] = $section['name'];
        }

        // Display regular parameter groups first (excluding helpers)
        foreach ($grouped_params as $group => $params) {
            if (empty($params) || $group === 'helpers') continue;

            echo '<div class="customizer-group customizer-group-' . esc_attr($group) . '">';
            echo '<h4>' . esc_html($group_titles[$group]) . '</h4>';

            // Display parameters in this group
            foreach ($params as $param) {
                $node_id = $param['node_id'];
                $display_name = !empty($param['display_name']) ? $param['display_name'] : $node_id;

                // Check if this is part of an RGB group
                $rgb_group = !empty($param['rgb_group']) ? $param['rgb_group'] : '';
                $is_rgb_component = !empty($param['is_rgb_component']) ? $param['is_rgb_component'] : '';

                // Add special RGB group attributes if needed
                $rgb_attrs = '';
                if ($rgb_group && $is_rgb_component === 'r') {
                    $rgb_attrs = ' data-rgb-group="' . esc_attr($rgb_group) . '"';

                    // Find G and B components
                    $g_component = $rgb_groups[$rgb_group]['g'] ?? null;
                    $b_component = $rgb_groups[$rgb_group]['b'] ?? null;

                    if ($g_component && $b_component) {
                        $rgb_attrs .= ' data-rgb-g="' . esc_attr($g_component['node_id']) . '"';
                        $rgb_attrs .= ' data-rgb-b="' . esc_attr($b_component['node_id']) . '"';
                    }
                }
                
                // Add parameter wrapper with visibility attributes
                $param_classes = 'polygonjs-parameter';
                
                // Add group visibility attributes
                $group_attributes = '';
                $group_name = isset($param['group_name']) ? trim($param['group_name']) : '';
                if (!empty($group_name)) {
                    $group_attributes .= ' data-parameter-group="' . esc_attr($group_name) . '"';
                    $group_attributes .= ' data-has-group="true"';
                } else {
                    $group_attributes .= ' data-has-group="false"';
                }
                
                echo '<div class="' . esc_attr($param_classes) . '" data-index="' . esc_attr($param['index'] ?? '') . '"' . $group_attributes . '>';

                // Render the appropriate control based on type
                switch ($param['control_type']) {
                    case 'slider':
                        $this->render_slider($node_id, $display_name, $param, $rgb_attrs);
                        break;

                    case 'number':
                        $this->render_number_input($node_id, $display_name, $param, $rgb_attrs);
                        break;

                    case 'text':
                        $this->render_text_input($node_id, $display_name, $param, $rgb_attrs);
                        break;

                    case 'checkbox':
                        $this->render_checkbox($node_id, $display_name, $param, $rgb_attrs);
                        break;

                    case 'color':
                        $this->render_color_options($node_id, $display_name, $param, $rgb_attrs);
                        break;

                    case 'dropdown':
                        $this->render_dropdown($node_id, $display_name, $param, $rgb_attrs);
                        break;
                }
                
                echo '</div>'; // End polygonjs-parameter
            }

            echo '</div>'; // End customizer-group
        }

        echo '</div>'; // End td-product-customizer

        // No longer display helper parameters here - they will only be shown in the Bricks element
        // Helper parameters are now handled by the TD_Polygonjs_Viewer::render_helper_controls method

        // Store helper params globally so they can be accessed by the Bricks element if needed
        global $td_link_helper_params;
        $td_link_helper_params = $helper_params;
    }
    
    /**
     * Render a single parameter
     */
    private function render_parameter($param, $rgb_groups) {
        if (empty($param['node_id']) || empty($param['control_type'])) return;
        
        // Skip hidden components of RGB groups (only show the main R component)
        if (!empty($param['is_rgb_component']) && $param['is_rgb_component'] !== 'r') {
            return;
        }
        
        // Skip hidden controls
        if ($param['control_type'] === 'hidden') {
            return;
        }
        
        $node_id = $param['node_id'];
        $display_name = !empty($param['display_name']) ? $param['display_name'] : $node_id;
        
        // Check if this is part of an RGB group
        $rgb_group = !empty($param['rgb_group']) ? $param['rgb_group'] : '';
        $is_rgb_component = !empty($param['is_rgb_component']) ? $param['is_rgb_component'] : '';
        
        // Add special RGB group attributes if needed
        $rgb_attrs = '';
        if ($rgb_group && $is_rgb_component === 'r') {
            $rgb_attrs = ' data-rgb-group="' . esc_attr($rgb_group) . '"';
            
            // Find G and B components
            $g_component = $rgb_groups[$rgb_group]['g'] ?? null;
            $b_component = $rgb_groups[$rgb_group]['b'] ?? null;
            
            if ($g_component && $b_component) {
                $rgb_attrs .= ' data-rgb-g="' . esc_attr($g_component['node_id']) . '"';
                $rgb_attrs .= ' data-rgb-b="' . esc_attr($b_component['node_id']) . '"';
            }
        }
        
        // Add parameter wrapper
        $param_classes = 'polygonjs-parameter';
        
        echo '<div class="' . esc_attr($param_classes) . '" data-index="' . esc_attr($param['index'] ?? '') . '">';
        
        // Render the appropriate control based on type
        switch ($param['control_type']) {
            case 'slider':
                $this->render_slider($node_id, $display_name, $param, $rgb_attrs);
                break;
            
            case 'number':
                $this->render_number_input($node_id, $display_name, $param, $rgb_attrs);
                break;
            
            case 'text':
                $this->render_text_input($node_id, $display_name, $param, $rgb_attrs);
                break;
            
            case 'checkbox':
                $this->render_checkbox($node_id, $display_name, $param, $rgb_attrs);
                break;
            
            case 'color':
                $this->render_color_options($node_id, $display_name, $param, $rgb_attrs);
                break;
            
            case 'dropdown':
                $this->render_dropdown($node_id, $display_name, $param, $rgb_attrs);
                break;
        }
        
        echo '</div>'; // End polygonjs-parameter

        if (isset($_GET['debug']) && !empty($helper_params)) {
            echo '<!-- Found ' . count($helper_params) . ' helper parameters, but not displaying them here -->';
            echo '<!-- Helper parameters will only be displayed inside the Bricks Polygonjs Viewer element -->';
        }
    }
    
    /**
     * Render a slider control
     */
    private function render_slider($node_id, $display_name, $param, $rgb_attrs = '') {
        $min = isset($param['min']) ? $param['min'] : 0;
        $max = isset($param['max']) ? $param['max'] : 100;
        $default = isset($param['default_value']) ? $param['default_value'] : $min;
        $step = isset($param['step']) ? $param['step'] : 1;
        
        // Determine if this parameter should display a unit
        $show_unit = false;
        $unit = '';
        $lc_name = strtolower($display_name);
        
        // Check for dimensional keywords
        $dimensional_keywords = [
            'size', 'height', 'width', 'depth', 'length', 'thickness', 
            'radius', 'diameter', 'distance', 'dimension', 'gap', 'offset'
        ];
        
        // Check if any dimensional keyword exists in the parameter name
        $has_dimensional_keyword = false;
        foreach ($dimensional_keywords as $keyword) {
            if (strpos($lc_name, $keyword) !== false) {
                $has_dimensional_keyword = true;
                break;
            }
        }
        
        // Check for numeric values that should display units
        $is_numeric_only = is_numeric($min) && is_numeric($max) && !isset($param['dropdown_options']);
        
        // Check if the hide_unit option is set
        $hide_unit = isset($param['hide_unit']) && $param['hide_unit'];
        
        if (($has_dimensional_keyword || $is_numeric_only) && !$hide_unit) {
            $show_unit = true;
            $unit = $this->get_measurement_unit();
        }
        
        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);
        
        // Add data attribute for hidden units to help with CSS targeting
        $has_hidden_unit = isset($param['hide_unit']) && $param['hide_unit'] ? 'true' : 'false';
        
        echo "<div class='slider-control' data-node-id='" . esc_attr($node_id) . "' data-has-hidden-unit='" . $has_hidden_unit . "'" . $rgb_attrs . ">";
        
        // Create a header row for the label and value input
        echo "<div class='slider-control-header'>";
        echo "<label for='" . esc_attr($control_id) . "'>" . esc_html($display_name) . ":" . "</label>";
        
        // Current value display with unit (now in the header for inline display)
        echo "<div class='slider-value-display'>";
        echo "<input type='text' id='" . esc_attr($control_id) . "-text' value='" . esc_attr($default) . "' class='slider-value-input'>";
        
        // Add the unit display for dimensional parameters
        $unit_display = '';
        
        if ($show_unit) {
            $unit_display = esc_html($unit);
        }
        
        // Use a consistent style to ensure reliable display
        $style = "display:inline-flex !important; align-items:center !important; justify-content:center !important;";
        $style .= "min-width:30px !important; height:34px !important; margin-left:8px !important;";
        $style .= "padding:3px 8px !important; background:rgba(255,255,255,0.9) !important;";
        $style .= "border:1px solid #999 !important; border-radius:3px !important;";
        $style .= "font-size:14px !important; font-weight:600 !important; color:#333 !important;";
        $style .= "box-shadow:0 1px 3px rgba(0,0,0,0.1) !important; z-index:999 !important;";
        
        // Add hide_unit parameter as a data attribute for easier targeting in JS
        $hide_unit = isset($param['hide_unit']) && $param['hide_unit'] ? 'true' : 'false';
        
        // Make units wider and ensure content is centered with inline styles for consistent display
        echo "<span class='measurement-unit' data-unit='" . $unit_display . "' data-hide-unit='" . $hide_unit . "' style='" . $style . "'>" . $unit_display . "</span>";
        
        echo "</div>"; // End slider-value-display
        echo "</div>"; // End slider-control-header
        
        // Display the slider range values on its own row
        echo "<div class='slider-range-container'>";
        
        // Create a track wrapper to ensure consistent width and handle touch events properly
        echo "<div class='slider-track-wrapper' style='width:100%; padding:10px 0; position:relative;'>";
        
        // Min value
        echo "<span class='slider-min-value'>" . esc_html($min) . ($show_unit ? ' ' . $unit : '') . "</span>";
        
        // IMPORTANT: This is what's actually bound to the PolygonJS scene - we've moved it outside our new container
        // but it's just hidden. The slider in the UI will update this value.
        echo "<input type='range' id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' 
             min='" . esc_attr($min) . "' max='" . esc_attr($max) . "' value='" . esc_attr($default) . "' 
             step='" . esc_attr($step) . "' class='polygonjs-control' style='position:absolute; opacity:0; pointer-events:none;'>";
             
        // Visible slider that users interact with - it will update the hidden polygonjs-control
        // Added touch-action: none to prevent accidental touch interactions with the track
        echo "<input type='range' id='" . esc_attr($control_id) . "-slider' 
             min='" . esc_attr($min) . "' max='" . esc_attr($max) . "' value='" . esc_attr($default) . "' 
             step='" . esc_attr($step) . "' class='visible-slider' style='touch-action: none;'>";
        
        // Max value
        echo "<span class='slider-max-value'>" . esc_html($max) . ($show_unit ? ' ' . $unit : '') . "</span>";
        
        echo "</div>"; // End slider-track-wrapper
        echo "</div>"; // End slider-range-container
        
        echo "</div>"; // End slider-control div
    }
    
    /**
     * Render a number input control
     */
    private function render_number_input($node_id, $display_name, $param, $rgb_attrs = '') {
        $min = isset($param['min']) ? $param['min'] : 0;
        $max = isset($param['max']) ? $param['max'] : 100;
        $default = isset($param['default_value']) ? $param['default_value'] : $min;
        $step = isset($param['step']) ? $param['step'] : 1;
        
        // Determine if this parameter should display a unit
        $show_unit = false;
        $unit = '';
        $lc_name = strtolower($display_name);
        
        // Check for dimensional keywords
        $dimensional_keywords = [
            'size', 'height', 'width', 'depth', 'length', 'thickness', 
            'radius', 'diameter', 'distance', 'dimension', 'gap', 'offset'
        ];
        
        // Check if any dimensional keyword exists in the parameter name
        $has_dimensional_keyword = false;
        foreach ($dimensional_keywords as $keyword) {
            if (strpos($lc_name, $keyword) !== false) {
                $has_dimensional_keyword = true;
                break;
            }
        }
        
        // Check for numeric values that should display units
        $is_numeric_only = is_numeric($min) && is_numeric($max) && !isset($param['dropdown_options']);
        
        // Check if the hide_unit option is set
        $hide_unit = isset($param['hide_unit']) && $param['hide_unit'];
        
        if (($has_dimensional_keyword || $is_numeric_only) && !$hide_unit) {
            $show_unit = true;
            $unit = $this->get_measurement_unit();
        }
        
        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);
        
        // Add data attribute for hidden units to help with CSS targeting
        $has_hidden_unit = isset($param['hide_unit']) && $param['hide_unit'] ? 'true' : 'false';
        
        echo "<div class='number-control' data-node-id='" . esc_attr($node_id) . "' data-has-hidden-unit='" . $has_hidden_unit . "'" . $rgb_attrs . ">";
        
        // Create a header row for label and input
        echo "<div class='number-control-header'>";
        echo "<label for='" . esc_attr($control_id) . "'>" . esc_html($display_name) . ":</label>";
        
        echo "<div class='number-input-container'>";
        // This is the visible number input
        echo "<input type='number' id='" . esc_attr($control_id) . "-visible' 
             min='" . esc_attr($min) . "' max='" . esc_attr($max) . "' value='" . esc_attr($default) . "' 
             step='" . esc_attr($step) . "' class='visible-number-input'>";
             
        // Add the unit display for dimensional parameters
        $unit_display = '';
        
        if ($show_unit) {
            $unit_display = esc_html($unit);
        }
        
        // Use a consistent style to ensure reliable display
        $style = "display:inline-flex !important; align-items:center !important; justify-content:center !important;";
        $style .= "min-width:30px !important; height:34px !important; margin-left:8px !important;";
        $style .= "padding:3px 8px !important; background:rgba(255,255,255,0.9) !important;";
        $style .= "border:1px solid #999 !important; border-radius:3px !important;";
        $style .= "font-size:14px !important; font-weight:600 !important; color:#333 !important;";
        $style .= "box-shadow:0 1px 3px rgba(0,0,0,0.1) !important; z-index:999 !important;";
        
        // Add hide_unit parameter as a data attribute for easier targeting in JS
        $hide_unit = isset($param['hide_unit']) && $param['hide_unit'] ? 'true' : 'false';
        
        // Make units wider and ensure content is centered with inline styles for consistent display
        echo "<span class='measurement-unit' data-unit='" . $unit_display . "' data-hide-unit='" . $hide_unit . "' style='" . $style . "'>" . $unit_display . "</span>";
        
        echo "</div>"; // End number-input-container
        echo "</div>"; // End number-control-header
        
        // THIS IS THE ACTUAL POLYGONJS CONTROL - hidden but functional
        echo "<input type='number' id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' 
             min='" . esc_attr($min) . "' max='" . esc_attr($max) . "' value='" . esc_attr($default) . "' 
             step='" . esc_attr($step) . "' class='polygonjs-control' style='position:absolute; opacity:0; pointer-events:none;'>";
        
        // Show min/max range information
        echo "<span class='number-range-info'>(Range: " . esc_html($min) . " - " . esc_html($max) . ($show_unit ? ' ' . $unit : '') . ")</span>";
        
        echo "</div>"; // End number-control
    }
    
    /**
     * Render a text input control
     */
    private function render_text_input($node_id, $display_name, $param, $rgb_attrs = '') {
        $default = isset($param['default_value']) ? $param['default_value'] : '';
        $max_length = isset($param['max_length']) ? $param['max_length'] : 20;
        
        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);
        
        echo "<div class='text-control' data-node-id='" . esc_attr($node_id) . "'" . $rgb_attrs . ">";
        echo "<label for='" . esc_attr($control_id) . "'>" . esc_html($display_name) . ":</label>";
        echo "<input type='text' id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' value='" . esc_attr($default) . "' maxlength='" . esc_attr($max_length) . "' class='polygonjs-control'>";
        echo "</div>";
    }
    
    /**
     * Render a checkbox control
     */
    private function render_checkbox($node_id, $display_name, $param, $rgb_attrs = '') {
        $default = isset($param['default_value']) && $param['default_value'] === '1';
        
        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);
        
        echo "<div class='checkbox-control' data-node-id='" . esc_attr($node_id) . "'" . $rgb_attrs . ">";
        echo "<label>";
        echo "<input type='checkbox' id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' value='1' " . ($default ? 'checked' : '') . " class='polygonjs-control'>";
        echo esc_html($display_name);
        echo "</label>";
        echo "</div>";
    }
    
    /**
     * Render a dropdown control
     */
    private function render_dropdown($node_id, $display_name, $param, $rgb_attrs = '') {
        $default = isset($param['default_value']) ? $param['default_value'] : '';
        $dropdown_options = isset($param['dropdown_options']) ? $param['dropdown_options'] : '';
        
        // Parse options
        $options = [];
        if (!empty($dropdown_options)) {
            foreach (explode("\n", $dropdown_options) as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Parse each line as value=label
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $value = trim($parts[0]);
                    $label = trim($parts[1]);
                    $options[$value] = $label;
                }
            }
        }
        
        // If no options defined, show a placeholder
        if (empty($options)) {
            $options = ['0' => __('No options defined', 'td-link')];
        }
        
        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);
        
        echo "<div class='dropdown-control' data-node-id='" . esc_attr($node_id) . "'" . $rgb_attrs . ">";
        echo "<label for='" . esc_attr($control_id) . "'>" . esc_html($display_name) . ":</label>";
        echo "<select id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' class='polygonjs-control'>";
        
        foreach ($options as $value => $label) {
            echo "<option value='" . esc_attr($value) . "' " . selected($default, $value, false) . ">" . esc_html($label) . "</option>";
        }
        
        echo "</select>";
        echo "</div>";
    }
    
    /**
     * Render color options with enhanced out-of-stock indicators and debugging
     */
    private function render_color_options($node_id, $display_name, $param, $rgb_attrs = '') {
        // Get color options using color IDs
        $color_ids = [];
        $colors = [];
        
        // Check if we have color IDs stored
        if (!empty($param['color_ids'])) {
            $color_ids = explode(',', $param['color_ids']);
            
            // Get colors from global colors manager
            if (class_exists('TD_Colors_Manager')) {
                $colors_manager = new TD_Colors_Manager();
                $global_colors = $colors_manager->get_global_colors();
                
                // Use only the selected colors
                foreach ($color_ids as $color_id) {
                    if (isset($global_colors[$color_id])) {
                        $colors[$color_id] = $global_colors[$color_id];
                    }
                }
            }
        } 
        // Fallback to legacy format
        else if (!empty($param['color_options'])) {
            $color_options = $param['color_options'];
            foreach (explode('|', $color_options) as $item) {
                $parts = explode(';', trim($item));
                if (count($parts) >= 2) {
                    $name = $parts[0];
                    $hex = $parts[1];
                    $in_stock = true; // Default to in stock
                    
                    // Check if stock status is specified (format: name;hex;stock)
                    if (count($parts) >= 3) {
                        $in_stock = ($parts[2] === '1');
                    }
                    
                    // Create a temporary ID for legacy colors
                    $temp_id = 'legacy-' . sanitize_title($name);
                    $colors[$temp_id] = [
                        'name' => $name,
                        'hex' => $hex,
                        'in_stock' => $in_stock
                    ];
                }
            }
        } 
        // Get all global colors if nothing is specified
        else {
            if (class_exists('TD_Colors_Manager')) {
                $colors_manager = new TD_Colors_Manager();
                $colors = $colors_manager->get_global_colors();
            } else {
                // Default colors if none specified
                $colors = [
                    'default-red' => ['name' => 'Red', 'hex' => '#FF0000', 'in_stock' => true],
                    'default-green' => ['name' => 'Green', 'hex' => '#00FF00', 'in_stock' => true],
                    'default-blue' => ['name' => 'Blue', 'hex' => '#0000FF', 'in_stock' => true]
                ];
            }
        }
        
        if (empty($colors)) return;
        
        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);
        
        // Add RGB-specific class if needed
        $extra_class = !empty($param['is_rgb_component']) ? ' rgb-color-control' : '';
        
        echo "<div class='color-control" . $extra_class . "' data-node-id='" . esc_attr($node_id) . "'" . $rgb_attrs . ">";
        echo "<label>" . esc_html($display_name) . ":</label>";
        echo "<div class='color-swatches'>";
        
        // Log debug info about colors
        if (isset($_GET['debug'])) {
            error_log("TD Link Debug: Rendering " . count($colors) . " colors for " . $display_name);
        }
        
        // Get the default color ID (if set)
        $default_color_id = isset($param['default_color_id']) ? $param['default_color_id'] : '';

        // Track if a default was found and the first in-stock color as fallback
        $default_found = false;
        $default_name = '';
        $first_in_stock_found = false;
        $first_in_stock_name = '';

        // Debug logging
        if (isset($_GET['debug'])) {
            error_log("TD Link Debug: Default color ID: {$default_color_id}");
        }

        foreach ($colors as $color_id => $color_data) {
            $name = $color_data['name'];
            $hex = $color_data['hex'];
            $in_stock = isset($color_data['in_stock']) ? (bool)$color_data['in_stock'] : true;
            $color_key = strtolower(str_replace(' ', '-', $name));

            // Debug logging
            if (isset($_GET['debug'])) {
                error_log("TD Link Debug: Color '{$name}' (ID: {$color_id}) in_stock: " . ($in_stock ? 'true' : 'false'));
            }

            // Use a unique HTML structure for each item that's fully self-contained
            echo "<div class='color-swatch-container'>";

            // Add appropriate classes
            $swatch_class = 'color-swatch';

            // Is this the default color?
            $is_default = ($color_id === $default_color_id) && $in_stock;

            // If this is the default and in stock, select it
            if ($is_default) {
                $swatch_class .= ' active';
                $default_found = true;
                $default_name = $name;

                if (isset($_GET['debug'])) {
                    error_log("TD Link Debug: Found default color: {$name} (ID: {$color_id})");
                }
            }
            // Otherwise, if no default found yet and this is the first in-stock color, mark it for potential selection
            else if ($in_stock && !$first_in_stock_found) {
                // Don't add active class yet, we'll only do this if no default is found
                $first_in_stock_found = true;
                $first_in_stock_name = $name;
            }

            // Add out-of-stock class if needed
            if (!$in_stock) {
                $swatch_class .= ' out-of-stock';
            }
            
            // Start building the swatch HTML with inline styles
            echo "<label class='" . esc_attr($swatch_class) . "' style='" . 
                 (!$in_stock ? "position:relative; border:1px solid #d63638; background-color:rgba(214,54,56,0.05); border-radius:4px; padding:5px; cursor:not-allowed; opacity:0.9;" : "") . 
                 "'>";
            
            // Add a radio button only if the color is in stock
            if ($in_stock) {
                echo "<input type='radio' name='" . esc_attr($control_id) . "_radio' value='" . esc_attr($color_key) . "' " .
                     (($swatch_class === 'color-swatch active') ? 'checked' : '') .
                     " class='color-radio polygonjs-control' data-color-id='" . esc_attr($color_id) . "'>";
            } else {
                // No radio button for out-of-stock items, but add a marker
                echo "<span class='out-of-stock-marker'></span>";
                
                // Add OUT OF STOCK badge
                echo "<span style='position:absolute; top:-8px; right:-8px; background:#d63638; color:white; font-size:9px; font-weight:bold; padding:2px 6px; border-radius:3px; letter-spacing:0.5px; box-shadow:0 1px 2px rgba(0,0,0,0.2); z-index:2;'>" . esc_html__('Out of Stock', 'td-link') . "</span>";
            }
            
            echo "<span class='swatch-color' style='background-color: " . esc_attr($hex) . "; position:relative; " .
                 ($in_stock ? "" : "opacity:0.7;") . "' title='" . esc_attr($name) . ($in_stock ? '' : ' (Out of Stock)') . "'>";
            
            // Add diagonal line and overlay for out-of-stock colors
            if (!$in_stock) {
                echo "<span style='position:absolute; top:0; left:0; width:100%; height:100%; border-radius:50%; background:linear-gradient(to top right, transparent calc(50% - 1px), #d63638, transparent calc(50% + 1px)); z-index:1;'></span>";
                echo "<span style='position:absolute; top:0; left:0; right:0; bottom:0; border-radius:50%; background:rgba(255,255,255,0.4); z-index:0;'></span>";
            }
            
            echo "</span>";
            
            echo "<span class='swatch-name'>" . esc_html($name) . "</span>";
            
            // Add "Out of Stock" label for out-of-stock items
            if (!$in_stock) {
                echo "<span class='out-of-stock-label'>" . esc_html__('Out of Stock', 'td-link') . "</span>";
            }
            
            echo "</label>";
            echo "</div>"; // End color-swatch-container
        }
        
        echo "</div>"; // End color-swatches
        
        // Use the default color if found, otherwise fallback to first in-stock or any color
        if ($default_found) {
            $default_value = $default_name;
            if (isset($_GET['debug'])) {
                error_log("TD Link Debug: Using default color as default value: {$default_value}");
            }
        } else {
            // If we didn't find the default color (it might be out of stock or not in the list),
            // but we did find a first in-stock color, apply active class to it
            if ($first_in_stock_found) {
                $default_value = $first_in_stock_name;

                // Find the first in-stock container and add active class
                foreach (new \RecursiveIteratorIterator(new \RecursiveArrayIterator($colors)) as $k => $v) {
                    if ($k === 'name' && $v === $first_in_stock_name) {
                        // This is our first in-stock color, we should retroactively mark it active
                        if (isset($_GET['debug'])) {
                            error_log("TD Link Debug: Using first in-stock color as default: {$first_in_stock_name}");
                        }
                        break;
                    }
                }
            } else if (!empty($colors)) {
                // If no in-stock colors found, try the first color regardless of stock
                $first_color = reset($colors);
                $default_value = $first_color['name'] ?? '';
                if (isset($_GET['debug'])) {
                    error_log("TD Link Debug: No in-stock colors, using first color: {$default_value}");
                }
            } else {
                $default_value = '';
            }
        }
        
        echo "<input type='hidden' name='" . esc_attr($control_id) . "' id='" . esc_attr($control_id) . "' value='" . esc_attr($default_value) . "' class='polygonjs-control'>";
        
        // Add debug info in a comment
        echo "<!-- Colors used: " . count($colors) . " total, " . ($first_in_stock_found ? "In-stock found" : "No in-stock colors") . " -->";
        
        echo "</div>"; // End color-control
    }
}