<?php
/**
 * 3D Link Polygonjs Viewer - Bricks Element
 * 
 * This element allows embedding and configuring the Polygonjs 3D scene viewer in Bricks layouts.
 * Enhanced with responsive dimensions and Bricks variables support.
 */

class TD_Polygonjs_Viewer extends \Bricks\Element {
    // Element properties
    public $category = 'td-link';
    public $name = 'polygonjs-viewer';
    public $icon = 'dashicons-admin-customizer';
    public $css_selector = '.td-viewer-container';
    public $scripts = array('td_polygonjs_viewer_script');
    public $styles = array('td_polygonjs_viewer_style');
    public $nestable = false;

    /**
     * Get element label for Bricks builder
     */
    public function get_label() {
        return esc_html__('Polygonjs 3D Viewer', 'td-link');
    }

    /**
     * Get element keywords for search in builder
     */
    public function get_keywords() {
        return ['3d', 'product', 'viewer', 'model', 'polygonjs', 'td-link'];
    }

    /**
     * Set builder control groups
     */
    public function set_control_groups() {
        $this->control_groups['general'] = [
            'title' => esc_html__('General', 'td-link'),
            'tab' => 'content',
        ];
        
        $this->control_groups['layout'] = [
            'title' => esc_html__('Layout', 'td-link'),
            'tab' => 'content',
        ];
        
        $this->control_groups['advanced'] = [
            'title' => esc_html__('Advanced', 'td-link'),
            'tab' => 'content',
        ];
    }

    /**
     * Set builder controls
     */
    public function set_controls() {
        // General controls
        $this->controls['productSource'] = [
            'tab' => 'content',
            'group' => 'general',
            'label' => esc_html__('Product Source', 'td-link'),
            'type' => 'select',
            'options' => [
                'current' => esc_html__('Current Product', 'td-link'),
                'specific' => esc_html__('Specific Product', 'td-link'),
                'none' => esc_html__('None (Direct Viewer)', 'td-link'),
            ],
            'default' => 'current',
            'description' => esc_html__('Select product source or none for direct viewer', 'td-link'),
        ];
        
        $this->controls['productId'] = [
            'tab' => 'content',
            'group' => 'general',
            'label' => esc_html__('Product ID', 'td-link'),
            'type' => 'text',
            'description' => esc_html__('Enter specific product ID (only if "Specific Product" is selected)', 'td-link'),
            'required' => [['productSource', '=', 'specific']],
        ];
        
        // Layout controls
        $this->controls['sizeMode'] = [
            'tab' => 'content',
            'group' => 'layout',
            'label' => esc_html__('Size Mode', 'td-link'),
            'type' => 'select',
            'options' => [
                'responsive' => esc_html__('Responsive (Aspect Ratio)', 'td-link'),
                'fixed' => esc_html__('Fixed Dimensions', 'td-link'),
            ],
            'default' => 'responsive',
            'description' => esc_html__('Choose how to size the viewer', 'td-link'),
        ];
        
        // RESPONSIVE MODE CONTROLS
        $this->controls['aspectRatio'] = [
            'tab' => 'content',
            'group' => 'layout',
            'label' => esc_html__('Aspect Ratio', 'td-link'),
            'type' => 'select',
            'options' => [
                '4:3' => '4:3 (Standard)',
                '16:9' => '16:9 (Widescreen)',
                '1:1' => '1:1 (Square)',
                '21:9' => '21:9 (Ultrawide)',
                '4:5' => '4:5 (Portrait)',
                'custom' => esc_html__('Custom Ratio', 'td-link'),
            ],
            'default' => '16:9',
            'description' => esc_html__('Select aspect ratio for viewer', 'td-link'),
            'required' => [['sizeMode', '=', 'responsive']],
            'responsive' => true,
        ];
        
        $this->controls['customRatio'] = [
            'tab' => 'content',
            'group' => 'layout',
            'label' => esc_html__('Custom Ratio', 'td-link'),
            'type' => 'text',
            'placeholder' => '16:10',
            'description' => esc_html__('Enter custom aspect ratio (width:height)', 'td-link'),
            'required' => [
                ['sizeMode', '=', 'responsive'],
                ['aspectRatio', '=', 'custom']
            ],
            'responsive' => true,
        ];
        
        // FIXED MODE CONTROLS
        $this->controls['viewerWidth'] = [
            'tab' => 'content',
            'group' => 'layout',
            'label' => esc_html__('Width', 'td-link'),
            'type' => 'text',
            'css' => [
                [
                    'property' => 'width',
                    'selector' => '.td-viewer-container',
                ]
            ],
            'default' => '100%',
            'description' => esc_html__('Set viewer width. Supports CSS variables like var(--viewer-width)', 'td-link'),
            'required' => [['sizeMode', '=', 'fixed']],
            'responsive' => true,
            'placeholder' => 'var(--viewer-width)'
        ];
        
        $this->controls['viewerHeight'] = [
            'tab' => 'content',
            'group' => 'layout',
            'label' => esc_html__('Height', 'td-link'),
            'type' => 'text',
            'css' => [
                [
                    'property' => 'height',
                    'selector' => '.td-viewer-container',
                ]
            ],
            'default' => '500px',
            'description' => esc_html__('Set viewer height. Supports CSS variables like var(--viewer-height)', 'td-link'),
            'required' => [['sizeMode', '=', 'fixed']],
            'responsive' => true,
            'placeholder' => 'var(--viewer-height)'
        ];
             

        // Advanced controls
        $this->controls['sceneMode'] = [
            'tab' => 'content',
            'group' => 'advanced',
            'label' => esc_html__('Scene Mode', 'td-link'),
            'type' => 'select',
            'options' => [
                'multi' => esc_html__('Multi-Scene (Default)', 'td-link'),
                'single' => esc_html__('Single Scene', 'td-link'),
            ],
            'default' => 'multi',
            'description' => esc_html__('Select scene mode: Multi-scene uses ?scene=NAME parameter, Single scene loads directly', 'td-link'),
        ];
        
        $this->controls['debug'] = [
            'tab' => 'content',
            'group' => 'advanced',
            'label' => esc_html__('Debug Mode', 'td-link'),
            'type' => 'checkbox',
            'description' => esc_html__('Enable console logging for debugging', 'td-link'),
        ];
        
        $this->controls['customDistPath'] = [
            'tab' => 'content',
            'group' => 'advanced',
            'label' => esc_html__('Custom Dist Path', 'td-link'),
            'type' => 'text',
            'placeholder' => '3d/dist',
            'description' => esc_html__('Override default dist path (e.g., 3d/dist)', 'td-link'),
        ];
        
        $this->controls['sceneName'] = [
            'tab' => 'content',
            'group' => 'advanced',
            'label' => esc_html__('Custom Scene Name', 'td-link'),
            'type' => 'text',
            'placeholder' => '',
            'description' => esc_html__('Override scene name from product settings (used in multi-scene mode)', 'td-link'),
            'required' => [['sceneMode', '=', 'multi']],
        ];
        
        $this->controls['additionalParams'] = [
            'tab' => 'content',
            'group' => 'advanced',
            'label' => esc_html__('Additional URL Parameters', 'td-link'),
            'type' => 'text',
            'placeholder' => 'param1=value1&param2=value2',
            'description' => esc_html__('Optional: Add custom URL parameters to the iframe URL', 'td-link'),
        ];
    }

    /**
     * Get responsive setting with fallback
     */
    protected function get_responsive_setting($setting_key) {
        $settings = $this->settings;

        // If not an array or if simple value, just return it
        if (!isset($settings[$setting_key]) || !is_array($settings[$setting_key])) {
            return isset($settings[$setting_key]) ? $settings[$setting_key] : null;
        }

        // Get current active breakpoint
        $breakpoint = method_exists('\Bricks\Helpers', 'get_active_breakpoint')
            ? \Bricks\Helpers::get_active_breakpoint()
            : null;

        // Desktop value (default)
        if (isset($settings[$setting_key]['desktop'])) {
            $desktop_value = $settings[$setting_key]['desktop'];
        } else {
            $desktop_value = null;
        }

        // If we have a breakpoint and a value for it, use that
        if ($breakpoint && isset($settings[$setting_key][$breakpoint])) {
            return $settings[$setting_key][$breakpoint];
        }

        // Fallback: if we are on mobile but have tablet, use tablet
        if ($breakpoint === 'mobile' && isset($settings[$setting_key]['tablet'])) {
            return $settings[$setting_key]['tablet'];
        }

        // Otherwise use desktop/default value
        return $desktop_value;
    }

    /**
     * Render helper controls for the 3D model
     */
    protected function render_helper_controls($product_id) {
        // Debug comment
        echo '<!-- Starting render_helper_controls for product ID: ' . esc_attr($product_id) . ' -->';

        // First, check if we have globally stored helper parameters from frontend display
        global $td_link_helper_params;

        // Initialize helper parameters array
        $helper_params = [];
        $rgb_groups = [];

        // If we have globally stored parameters, use them
        if (isset($td_link_helper_params) && !empty($td_link_helper_params)) {
            echo '<!-- Using globally stored helper parameters (' . count($td_link_helper_params) . ' parameters) -->';
            $helper_params = $td_link_helper_params;

            // Re-build RGB groups
            foreach ($helper_params as $param) {
                if (!empty($param['is_rgb_component']) && !empty($param['rgb_group'])) {
                    if (!isset($rgb_groups[$param['rgb_group']])) {
                        $rgb_groups[$param['rgb_group']] = [];
                    }
                    $rgb_groups[$param['rgb_group']][$param['is_rgb_component']] = $param;
                }
            }
        }
        // Otherwise, we need to fetch and identify helper parameters
        else {
            // Get parameters
            $parameters_manager = new TD_Parameters_Manager();
            $parameters = $parameters_manager->get_parameters($product_id);

            if (empty($parameters)) {
                echo '<!-- No parameters found for product ID: ' . esc_attr($product_id) . ' -->';
                return;
            }

            echo '<!-- Found ' . count($parameters) . ' parameters for product ID: ' . esc_attr($product_id) . ' -->';

            // Get sections manager for helper section check
            global $td_link;
            $sections_manager = isset($td_link) && isset($td_link->sections_manager)
                ? $td_link->sections_manager
                : new TD_Sections_Manager();

            // Identify helper parameters
            echo '<!-- Starting to identify helper parameters -->';
            foreach ($parameters as $index => $param) {
                echo '<!-- Checking parameter ' . $index . ': ' . (isset($param['display_name']) ? esc_attr($param['display_name']) : 'unnamed') . ' -->';

                if (empty($param['node_id']) || empty($param['control_type'])) {
                    echo '<!-- Skipping parameter due to missing node_id or control_type -->';
                    continue;
                }

                // Skip hidden components of RGB groups (only show the main R component)
                if (!empty($param['is_rgb_component']) && $param['is_rgb_component'] !== 'r') {
                    echo '<!-- Skipping RGB component ' . esc_attr($param['is_rgb_component']) . ' -->';
                    if (!empty($param['rgb_group'])) {
                        if (!isset($rgb_groups[$param['rgb_group']])) {
                            $rgb_groups[$param['rgb_group']] = [];
                        }
                        $rgb_groups[$param['rgb_group']][$param['is_rgb_component']] = $param;
                    }
                    continue;
                }

                // Skip hidden controls
                if ($param['control_type'] === 'hidden') {
                    echo '<!-- Skipping hidden control -->';
                    continue;
                }

                // Check if this is a helper parameter
                $is_helper = isset($param['is_helper']) && $param['is_helper'];
                echo '<!-- Is helper parameter? ' . ($is_helper ? 'YES' : 'NO') . ' -->';

                if (isset($param['is_helper'])) {
                    echo '<!-- is_helper raw value: ' . esc_attr(var_export($param['is_helper'], true)) . ' -->';
                } else {
                    echo '<!-- is_helper parameter not set -->';
                }

                // Get parameter section
                $section_id = isset($param['section']) && !empty($param['section'])
                    ? $param['section']
                    : $sections_manager->auto_categorize_parameter($param);

                echo '<!-- Parameter section: ' . esc_attr($section_id) . ' -->';

                // Also check if in helpers section
                $in_helper_section = $sections_manager->is_helper_section($section_id);
                echo '<!-- Is helper section? ' . ($in_helper_section ? 'YES' : 'NO') . ' -->';

                // Save helper parameters
                if ($is_helper || $in_helper_section) {
                    $helper_params[] = $param;
                    echo '<!-- ADDED to helper parameters list -->';
                } else {
                    echo '<!-- NOT added to helper parameters list -->';
                }
            }
        }

        echo '<!-- Found ' . count($helper_params) . ' helper parameters -->';

        // If no helper params, exit early
        if (empty($helper_params)) {
            echo '<!-- No helper parameters found, exiting early -->';
            return;
        }

        echo '<!-- Rendering helper controls for ' . count($helper_params) . ' parameters -->';

        // Display helper controls container
        echo '<div class="td-helpers-inside-bricks" data-product-id="' . esc_attr($product_id) . '">';
        echo '<div class="td-helpers-header">';
        echo '<h4>' . esc_html__('Visualization Helpers', 'td-link') . '</h4>';
        echo '<button type="button" class="td-helpers-toggle"><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
        echo '</div>';

        echo '<div class="td-helpers-controls">';

        // Display all helper parameters
        foreach ($helper_params as $param) {
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
                $g_component = isset($rgb_groups[$rgb_group]['g']) ? $rgb_groups[$rgb_group]['g'] : null;
                $b_component = isset($rgb_groups[$rgb_group]['b']) ? $rgb_groups[$rgb_group]['b'] : null;

                if ($g_component && $b_component) {
                    $rgb_attrs .= ' data-rgb-g="' . esc_attr($g_component['node_id']) . '"';
                    $rgb_attrs .= ' data-rgb-b="' . esc_attr($b_component['node_id']) . '"';
                }
            }

            // Add helper class for styling
            $rgb_attrs .= ' data-helper="true"';

            // Render the appropriate control based on type
            switch ($param['control_type']) {
                case 'slider':
                    $this->render_slider_control($node_id, $display_name, $param, $rgb_attrs);
                    break;

                case 'number':
                    $this->render_number_control($node_id, $display_name, $param, $rgb_attrs);
                    break;

                case 'checkbox':
                    $this->render_checkbox_control($node_id, $display_name, $param, $rgb_attrs);
                    break;

                case 'dropdown':
                    $this->render_dropdown_control($node_id, $display_name, $param, $rgb_attrs);
                    break;

                default:
                    // For other control types, use a generic text input
                    $this->render_text_control($node_id, $display_name, $param, $rgb_attrs);
                    break;
            }
        }

        echo '</div>'; // End td-helpers-controls
        echo '</div>'; // End td-helpers-inside-bricks
    }

    /**
     * Render slider control for helper parameters
     */
    private function render_slider_control($node_id, $display_name, $param, $attrs = '') {
        $min = isset($param['min']) ? $param['min'] : 0;
        $max = isset($param['max']) ? $param['max'] : 100;
        $default = isset($param['default_value']) ? $param['default_value'] : $min;
        $step = isset($param['step']) ? $param['step'] : 1;

        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);

        echo "<div class='slider-control' data-node-id='" . esc_attr($node_id) . "'" . $attrs . ">";
        echo "<label for='" . esc_attr($control_id) . "-slider'>" . esc_html($display_name) . ":" . "</label>";
        echo "<input type='range' id='" . esc_attr($control_id) . "-slider' min='" . esc_attr($min) . "' max='" . esc_attr($max) . "' value='" . esc_attr($default) . "' step='" . esc_attr($step) . "' class='polygonjs-control'>";
        echo "<input type='hidden' name='" . esc_attr($control_id) . "' id='" . esc_attr($control_id) . "' value='" . esc_attr($default) . "'>";
        echo "</div>";
    }

    /**
     * Render number control for helper parameters
     */
    private function render_number_control($node_id, $display_name, $param, $attrs = '') {
        $min = isset($param['min']) ? $param['min'] : 0;
        $max = isset($param['max']) ? $param['max'] : 100;
        $default = isset($param['default_value']) ? $param['default_value'] : $min;
        $step = isset($param['step']) ? $param['step'] : 1;

        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);

        echo "<div class='number-control' data-node-id='" . esc_attr($node_id) . "'" . $attrs . ">";
        echo "<label for='" . esc_attr($control_id) . "'>" . esc_html($display_name) . ":</label>";
        echo "<input type='number' id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' min='" . esc_attr($min) . "' max='" . esc_attr($max) . "' value='" . esc_attr($default) . "' step='" . esc_attr($step) . "' class='polygonjs-control'>";
        echo "</div>";
    }

    /**
     * Render text control for helper parameters
     */
    private function render_text_control($node_id, $display_name, $param, $attrs = '') {
        $default = isset($param['default_value']) ? $param['default_value'] : '';
        $max_length = isset($param['max_length']) ? $param['max_length'] : 20;

        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);

        echo "<div class='text-control' data-node-id='" . esc_attr($node_id) . "'" . $attrs . ">";
        echo "<label for='" . esc_attr($control_id) . "'>" . esc_html($display_name) . ":</label>";
        echo "<input type='text' id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' value='" . esc_attr($default) . "' maxlength='" . esc_attr($max_length) . "' class='polygonjs-control'>";
        echo "</div>";
    }

    /**
     * Render checkbox control for helper parameters
     */
    private function render_checkbox_control($node_id, $display_name, $param, $attrs = '') {
        $default = isset($param['default_value']) && $param['default_value'] === '1';

        // Create a control ID based on the node ID
        $control_id = sanitize_title($node_id);

        echo "<div class='checkbox-control' data-node-id='" . esc_attr($node_id) . "'" . $attrs . ">";
        echo "<label>";
        echo "<input type='checkbox' id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' value='1' " . ($default ? 'checked' : '') . " class='polygonjs-control'>";
        echo esc_html($display_name);
        echo "</label>";
        echo "</div>";
    }

    /**
     * Render dropdown control for helper parameters
     */
    private function render_dropdown_control($node_id, $display_name, $param, $attrs = '') {
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

        echo "<div class='dropdown-control' data-node-id='" . esc_attr($node_id) . "'" . $attrs . ">";
        echo "<label for='" . esc_attr($control_id) . "'>" . esc_html($display_name) . ":</label>";
        echo "<select id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' class='polygonjs-control'>";

        foreach ($options as $value => $label) {
            $selected = $default === $value ? 'selected' : '';
            echo "<option value='" . esc_attr($value) . "' " . $selected . ">" . esc_html($label) . "</option>";
        }

        echo "</select>";
        echo "</div>";
    }

    /**
     * Render element HTML
     */
    public function render() {
        $settings = $this->settings;
        $debug = !empty($settings['debug']);
        
        // Check if we're using direct viewer mode (no product)
        $direct_viewer = !empty($settings['productSource']) && $settings['productSource'] === 'none';
        
        // Get product ID (if needed)
        $product_id = null;
        $product = null;
        $product_slug = '';
        
        if (!$direct_viewer) {
            if (empty($settings['productSource']) || $settings['productSource'] === 'current') {
                global $product;
                if (!$product && function_exists('wc_get_product')) {
                    $product = wc_get_product(get_the_ID());
                }
                if ($product) {
                    $product_id = $product->get_id();
                }
            } else {
                $product_id = !empty($settings['productId']) ? intval($settings['productId']) : null;
                if ($product_id) {
                    $product = wc_get_product($product_id);
                }
            }
            
            // No product found but not in direct viewer mode - show message in builder
            if (!$product_id && !$direct_viewer) {
                if (method_exists('\Bricks\Helpers', 'is_bricks_preview') && \Bricks\Helpers::is_bricks_preview()) {
                    return $this->render_element_placeholder([
                        'title' => esc_html__('No product found', 'td-link'),
                    ]);
                }
                return '';
            }
            
            // Get the product slug
            if ($product) {
                $product_slug = $product->get_slug();
            }
        }
        
        // Get custom scene name from settings or product meta
        $scene_name = '';
        if (!empty($settings['sceneName'])) {
            $scene_name = $settings['sceneName'];
        } else if ($product_id) {
            // Try to get scene name from product meta
            $scene_name = get_post_meta($product_id, '_td_scene_name', true);
            
            // If no scene name is set, use the product slug as fallback
            if (empty($scene_name) && !empty($product_slug)) {
                $scene_name = $product_slug;
            }
        }
        
        // Get dist path from settings or use default
        $dist_path = !empty($settings['customDistPath']) 
            ? $settings['customDistPath'] 
            : "3d/dist";
            
        // Remove trailing slashes
        $dist_path = rtrim($dist_path, '/');
        
        // Create iframe URL based on scene mode
        $site_url = site_url();
        
        if (empty($settings['sceneMode']) || $settings['sceneMode'] === 'multi') {
            // Multi-scene mode (with ?scene= parameter)
            $iframe_url = "{$site_url}/{$dist_path}/?scene={$scene_name}";
        } else {
            // Single-scene mode (direct path to build folder)
            $iframe_url = "{$site_url}/{$dist_path}/";
        }
        
        // If we're in direct viewer mode and there's no scene parameter, make sure the URL is valid
        if ($direct_viewer && empty($scene_name) && strpos($iframe_url, '?scene=') !== false) {
            $iframe_url = str_replace('?scene=', '', $iframe_url);
        }
        
        // Add bricks parameter
        $iframe_url .= (strpos($iframe_url, '?') !== false) ? "&bricks=1" : "?bricks=1";
        
        // Add debug parameter if needed
        if ($debug) {
            $iframe_url .= "&debug=1";
        }
        
        // Add any additional custom parameters
        if (!empty($settings['additionalParams'])) {
            $iframe_url .= "&" . $settings['additionalParams'];
        }
        
        // Handle sizing based on mode
        $size_mode = !empty($settings['sizeMode']) ? $settings['sizeMode'] : 'responsive';
        $root_classes = ['td-viewer-container'];
        $inline_css = "position: relative; margin-bottom: 2em;";
        
        // Get current breakpoint if available
        $current_breakpoint = method_exists('\Bricks\Helpers', 'get_active_breakpoint') 
            ? \Bricks\Helpers::get_active_breakpoint() 
            : null;
        
        // Add device class if breakpoint available
        if ($current_breakpoint) {
            $root_classes[] = 'bricks-' . $current_breakpoint;
        }
        
        // Handle different sizing modes
        if ($size_mode === 'responsive') {
            // For responsive mode, use aspect ratio
            $padding_bottom = '56.25%'; // Default 16:9 ratio
            
            // Get aspect ratio setting
            $aspect_ratio = $this->get_responsive_setting('aspectRatio');
            
            if ($aspect_ratio) {
                switch ($aspect_ratio) {
                    case '4:3':
                        $padding_bottom = '75%';
                        break;
                    case '16:9':
                        $padding_bottom = '56.25%';
                        break;
                    case '1:1':
                        $padding_bottom = '100%';
                        break;
                    case '21:9':
                        $padding_bottom = '42.85%';
                        break;
                    case '4:5':
                        $padding_bottom = '125%';
                        break;
                    case 'custom':
                        $custom_ratio = $this->get_responsive_setting('customRatio');
                        if (!empty($custom_ratio) && preg_match('/^(\d+):(\d+)$/', $custom_ratio, $matches)) {
                            $width = intval($matches[1]);
                            $height = intval($matches[2]);
                            if ($width > 0 && $height > 0) {
                                $padding_bottom = ($height / $width * 100) . '%';
                            }
                        }
                        break;
                }
            }
            
            $inline_css .= " width: 100%; padding-bottom: {$padding_bottom};";
            
        } else if ($size_mode === 'fixed') {
            // For fixed mode, use specific width and height
            $width_value = $this->get_responsive_setting('viewerWidth');
            $height_value = $this->get_responsive_setting('viewerHeight');
            
            if (empty($width_value)) {
                $width_value = '100%';
            }
            
            if (empty($height_value)) {
                $height_value = '500px';
            }
            
            $inline_css .= " width: {$width_value}; height: {$height_value}; padding-bottom: 0;";
            
        } else if ($size_mode === 'custom') {
            // For custom mode, use CSS variables
            $width_var = !empty($settings['cssVarWidth']) ? $settings['cssVarWidth'] : 'var(--viewer-width, 100%)';
            $height_var = !empty($settings['cssVarHeight']) ? $settings['cssVarHeight'] : 'var(--viewer-height, 500px)';
            
            $inline_css .= " width: {$width_var}; height: {$height_var}; padding-bottom: 0;";
        }
        
        // Add max width if specified
        $max_width = $this->get_responsive_setting('maxWidth');
        if (!empty($max_width)) {
            $inline_css .= " max-width: {$max_width};";
        }
        
        // Add bricks class for targeting
        $root_classes[] = 'bricks-viewer';
        $root_classes[] = 'bricks-responsive-viewer';
        
        // Get Polygonjs setting from product (if we have one)
        if ($product_id) {
            $enable_polygonjs = get_post_meta($product_id, '_enable_polygonjs', true);
            if ($enable_polygonjs === 'yes') {
                $root_classes[] = 'polygonjs-enabled';
            }
        } else {
            // For direct viewer, assume polygonjs is enabled
            $root_classes[] = 'polygonjs-enabled';
            $root_classes[] = 'direct-viewer';
        }
        
        // Get node mappings (only if we have a product)
        $node_mappings = [];
        if ($product_id) {
            // Try global td_link function for mappings
            global $td_link;
            if ($td_link && method_exists($td_link, 'get_polygonjs_node_mappings')) {
                $node_mappings = $td_link->get_polygonjs_node_mappings($product_id);
            }
        }

        // Add the mappings to the root element data attribute (if we have any)
        if (!empty($node_mappings)) {
            $this->set_attribute('_root', 'data-node-mappings', esc_attr(json_encode($node_mappings)));
        }
        
        // Set data attributes for size mode and device info
        $this->set_attribute('_root', 'data-size-mode', $size_mode);
        if ($current_breakpoint) {
            $this->set_attribute('_root', 'data-device', $current_breakpoint);
        }
        
        // Set element attributes
        $this->set_attribute('_root', 'class', $root_classes);
        $this->set_attribute('_root', 'style', $inline_css);
        $this->set_attribute('_root', 'data-debug', $debug ? 'true' : 'false');
        $this->set_attribute('_root', 'data-scene-mode', !empty($settings['sceneMode']) ? $settings['sceneMode'] : 'multi');
        
        // Only set product-specific attributes if we have a product
        if ($product_id) {
            $this->set_attribute('_root', 'data-product-id', $product_id);
            $this->set_attribute('_root', 'data-scene-name', $scene_name);
        } else {
            $this->set_attribute('_root', 'data-direct-viewer', 'true');
        }
        
        // Iframe attributes - use a different ID to avoid conflicts
        $iframe_id = 'bricks-product-3d-viewer-' . uniqid();
        $this->set_attribute('iframe', 'id', $iframe_id);
        $this->set_attribute('iframe', 'src', $iframe_url);
        $this->set_attribute('iframe', 'style', 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;');
        $this->set_attribute('iframe', 'allowfullscreen', '');
        $this->set_attribute('iframe', 'loading', 'lazy');
        
        // Output HTML - Viewer only (helpers now in separate element)
        echo "<div {$this->render_attributes('_root')}>";
        echo "<iframe {$this->render_attributes('iframe')}></iframe>";
        echo '</div>';
        
        // Add a smaller inline script that will connect controls
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Debug mode
            const debug = <?php echo $debug ? 'true' : 'false'; ?>;
            
            if (debug) console.log('Bricks viewer script loaded for iframe: <?php echo $iframe_id; ?>');
            
            // Find the Bricks iframe
            const bricksIframe = document.getElementById('<?php echo $iframe_id; ?>');
            
            if (!bricksIframe) {
                if (debug) console.error('Bricks iframe not found');
                return;
            }
            
            // Find UI controls
            const heightSlider = document.getElementById('height-slider');
            const widthSlider = document.getElementById('width-slider');
            const depthSlider = document.getElementById('depth-slider');
            const shelvesSlider = document.getElementById('shelves-slider');
            const textInput = document.getElementById('custom-text');
            const colorOptions = document.querySelectorAll('.color-option');
            
            // Connect event listeners
            if (heightSlider) heightSlider.addEventListener('input', function() {
                updateModel();
            });
            
            if (widthSlider) widthSlider.addEventListener('input', function() {
                updateModel();
            });
            
            if (depthSlider) depthSlider.addEventListener('input', function() {
                updateModel();
            });
            
            if (shelvesSlider) shelvesSlider.addEventListener('input', function() {
                updateModel();
            });
            
            if (textInput) textInput.addEventListener('input', function() {
                updateModel();
            });
            
            // Connect color options
            colorOptions.forEach(option => {
                option.addEventListener('click', function() {
                    updateModel();
                });
            });
            
            // Function to send messages to iframe
            function updateModel() {
                const data = {
                    type: 'updateModel'
                };
                
                // Add values from all sliders
                if (heightSlider) data.height = parseFloat(heightSlider.value);
                if (widthSlider) data.width = parseFloat(widthSlider.value);
                if (depthSlider) data.depth = parseFloat(depthSlider.value);
                if (shelvesSlider) data.shelvesCount = parseInt(shelvesSlider.value);
                if (textInput) data.customText = textInput.value;
                
                // Get active color
                const activeColor = document.querySelector('.color-option.active');
                if (activeColor) data.color = activeColor.dataset.color;
                
                // Send message to iframe
                if (debug) console.log('Sending update to Bricks iframe:', data);
                bricksIframe.contentWindow.postMessage(data, '*');
            }
            
            // Wait for iframe to load
            bricksIframe.addEventListener('load', function() {
                if (debug) console.log('Bricks iframe loaded');
            });
            
            // Listen for messages from the iframe
            window.addEventListener('message', function(event) {
                // Check that the message is from our iframe
                if (event.source !== bricksIframe.contentWindow) return;
                
                if (debug) console.log('Received message from Bricks iframe:', event.data);
                
                if (event.data && event.data.type === 'modelReady') {
                    if (debug) console.log('3D Model in Bricks iframe is ready');
                    
                    // Trigger events
                    setTimeout(() => {
                        if (heightSlider) heightSlider.dispatchEvent(new Event('input'));
                        if (widthSlider) widthSlider.dispatchEvent(new Event('input'));
                        if (depthSlider) depthSlider.dispatchEvent(new Event('input'));
                        if (shelvesSlider) shelvesSlider.dispatchEvent(new Event('input'));
                        if (textInput) textInput.dispatchEvent(new Event('input'));
                        
                        // Trigger color option
                        const activeColor = document.querySelector('.color-option.active');
                        if (activeColor) activeColor.click();
                    }, 500);
                }
            });
        });
        </script>
        <?php
    }
}