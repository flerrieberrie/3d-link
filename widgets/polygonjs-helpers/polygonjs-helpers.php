<?php
/**
 * 3D Link Polygonjs Helpers - Bricks Element
 * 
 * This element provides helper controls for the Polygonjs 3D viewer
 * that can be placed anywhere in the Bricks layout.
 */

class TD_Polygonjs_Helpers extends \Bricks\Element {
    // Element properties
    public $category = 'td-link';
    public $name = 'polygonjs-helpers';
    public $icon = 'dashicons-admin-tools';
    public $css_selector = '.td-helpers-container';
    public $scripts = array('td_helpers_script');
    // No default styles - we'll use Bricks styling system
    public $nestable = false;

    /**
     * Get element label for Bricks builder
     */
    public function get_label() {
        return esc_html__('Polygonjs Helpers', 'td-link');
    }

    /**
     * Get element keywords for search in builder
     */
    public function get_keywords() {
        return ['3d', 'product', 'helpers', 'controls', 'visualization', 'polygonjs', 'td-link'];
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

        $this->control_groups['typography'] = [
            'title' => esc_html__('Typography', 'td-link'),
            'tab' => 'style',
        ];

        $this->control_groups['controls'] = [
            'title' => esc_html__('Controls', 'td-link'),
            'tab' => 'style',
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
            ],
            'default' => 'current',
            'description' => esc_html__('Select which product to show helpers for', 'td-link'),
        ];

        $this->controls['productId'] = [
            'tab' => 'content',
            'group' => 'general',
            'label' => esc_html__('Product ID', 'td-link'),
            'type' => 'text',
            'description' => esc_html__('Enter specific product ID (only if "Specific Product" is selected)', 'td-link'),
            'required' => [['productSource', '=', 'specific']],
        ];

        $this->controls['viewerId'] = [
            'tab' => 'content',
            'group' => 'general',
            'label' => esc_html__('Target Viewer ID', 'td-link'),
            'type' => 'text',
            'description' => esc_html__('Optional: Connect helpers to a specific Polygonjs viewer instance by ID', 'td-link'),
            'placeholder' => 'Leave empty to autoconnect',
        ];

        $this->controls['titleText'] = [
            'tab' => 'content',
            'group' => 'general',
            'label' => esc_html__('Title Text', 'td-link'),
            'type' => 'text',
            'default' => esc_html__('Visualization Helpers', 'td-link'),
            'description' => esc_html__('Header text for the helpers section', 'td-link'),
        ];

        // Layout controls
        $this->controls['columnsLayout'] = [
            'tab' => 'content',
            'group' => 'layout',
            'label' => esc_html__('Columns Layout', 'td-link'),
            'type' => 'select',
            'options' => [
                '1' => esc_html__('Single Column', 'td-link'),
                '2' => esc_html__('Two Columns', 'td-link'),
            ],
            'default' => '2',
            'description' => esc_html__('Number of columns for helper controls', 'td-link'),
        ];

        $this->controls['columnGap'] = [
            'tab' => 'content',
            'group' => 'layout',
            'label' => esc_html__('Column Gap', 'td-link'),
            'type' => 'number',
            'units' => true,
            'css' => [
                [
                    'property' => 'column-gap',
                    'selector' => '.td-helpers-controls',
                ],
            ],
            'default' => '20px',
            'required' => [['columnsLayout', '=', '2']],
        ];

        $this->controls['rowGap'] = [
            'tab' => 'content',
            'group' => 'layout',
            'label' => esc_html__('Row Gap', 'td-link'),
            'type' => 'number',
            'units' => true,
            'css' => [
                [
                    'property' => 'row-gap',
                    'selector' => '.td-helpers-controls',
                ],
            ],
            'default' => '15px',
        ];

        // Header controls removed - no header is displayed

        $this->controls['labelTypography'] = [
            'tab' => 'style',
            'group' => 'typography',
            'label' => esc_html__('Label Typography', 'td-link'),
            'type' => 'typography',
            'css' => [
                [
                    'property' => 'font',
                    'selector' => '.td-helpers-container label',
                ],
            ],
        ];

        // Control styles
        $this->controls['controlSpacing'] = [
            'tab' => 'style',
            'group' => 'controls',
            'label' => esc_html__('Control Spacing', 'td-link'),
            'type' => 'number',
            'units' => true,
            'css' => [
                [
                    'property' => 'margin-bottom',
                    'selector' => '.slider-control, .number-control, .text-control, .checkbox-control, .dropdown-control',
                ],
            ],
            'default' => '15px',
        ];

        $this->controls['checkboxSize'] = [
            'tab' => 'style',
            'group' => 'controls',
            'label' => esc_html__('Checkbox Size', 'td-link'),
            'type' => 'number',
            'units' => true,
            'css' => [
                [
                    'property' => 'width',
                    'selector' => '.checkbox-control input[type="checkbox"]',
                ],
                [
                    'property' => 'height',
                    'selector' => '.checkbox-control input[type="checkbox"]',
                ],
            ],
            'default' => '24px',
        ];

        $this->controls['sliderColor'] = [
            'tab' => 'style',
            'group' => 'controls',
            'label' => esc_html__('Slider Track Color', 'td-link'),
            'type' => 'color',
            'css' => [
                [
                    'property' => 'background-color',
                    'selector' => '.slider-control input[type="range"]',
                ],
            ],
        ];

        $this->controls['sliderThumbColor'] = [
            'tab' => 'style',
            'group' => 'controls',
            'label' => esc_html__('Slider Thumb Color', 'td-link'),
            'type' => 'color',
            'css' => [
                [
                    'property' => 'background',
                    'selector' => '.slider-control input[type="range"]::-webkit-slider-thumb',
                ],
            ],
        ];

        $this->controls['inputBorderColor'] = [
            'tab' => 'style',
            'group' => 'controls',
            'label' => esc_html__('Input Border Color', 'td-link'),
            'type' => 'color',
            'css' => [
                [
                    'property' => 'border-color',
                    'selector' => '.td-helpers-container input[type="number"], .td-helpers-container input[type="text"], .td-helpers-container select',
                ],
            ],
        ];

        $this->controls['inputBorderRadius'] = [
            'tab' => 'style',
            'group' => 'controls',
            'label' => esc_html__('Input Border Radius', 'td-link'),
            'type' => 'number',
            'units' => true,
            'css' => [
                [
                    'property' => 'border-radius',
                    'selector' => '.td-helpers-container input[type="number"], .td-helpers-container input[type="text"], .td-helpers-container select',
                ],
            ],
            'default' => '4px',
        ];
    }

    /**
     * Render helper controls for the 3D model
     * 
     * @param int $product_id Product ID to render helpers for
     * @return array Helper parameters that were rendered
     */
    protected function gather_helper_parameters($product_id) {
        // Debug comment for inspection
        echo '<!-- Gathering helper parameters for product ID: ' . esc_attr($product_id) . ' -->';
        
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
                return array($helper_params, $rgb_groups);
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
                
                // Get parameter section
                $section_id = isset($param['section']) && !empty($param['section'])
                    ? $param['section']
                    : $sections_manager->auto_categorize_parameter($param);

                // Also check if in helpers section
                $in_helper_section = $sections_manager->is_helper_section($section_id);

                // Save helper parameters
                if ($is_helper || $in_helper_section) {
                    $helper_params[] = $param;
                }
            }
        }
        
        return array($helper_params, $rgb_groups);
    }

    /**
     * Render a slider control for helper parameters
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
        echo "<label style='display: flex; align-items: center; cursor: pointer;'>";
        echo "<input type='checkbox' id='" . esc_attr($control_id) . "' name='" . esc_attr($control_id) . "' value='1' " . ($default ? 'checked' : '') . " class='polygonjs-control' style='margin-right: 10px; min-width: 24px; min-height: 24px; cursor: pointer;'>";
        echo "<span class='checkbox-label' style='line-height: 1.2;'>" . esc_html($display_name) . "</span>";
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
        
        // Get product ID 
        $product_id = null;
        $product = null;
        
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
        
        // No product found, show message in builder
        if (!$product_id) {
            if (method_exists('\Bricks\Helpers', 'is_bricks_preview') && \Bricks\Helpers::is_bricks_preview()) {
                return $this->render_element_placeholder([
                    'title' => esc_html__('No product found', 'td-link'),
                ]);
            }
            return '';
        }
        
        // Get helper parameters
        list($helper_params, $rgb_groups) = $this->gather_helper_parameters($product_id);
        
        echo '<!-- Found ' . count($helper_params) . ' helper parameters -->';
        
        // If no helper params, show message in builder
        if (empty($helper_params)) {
            if (method_exists('\Bricks\Helpers', 'is_bricks_preview') && \Bricks\Helpers::is_bricks_preview()) {
                return $this->render_element_placeholder([
                    'title' => esc_html__('No helper parameters found', 'td-link'),
                    'description' => esc_html__('Add helper parameters to the product in the admin panel', 'td-link'),
                ]);
            }
            return '';
        }
        
        // Generate unique ID for this element instance
        $instance_id = 'td-helpers-' . uniqid();

        // Get target viewer ID
        $viewer_id = !empty($settings['viewerId']) ? $settings['viewerId'] : '';

        // Get title text
        $title_text = !empty($settings['titleText']) ? $settings['titleText'] : esc_html__('Visualization Helpers', 'td-link');

        // Check for columns layout
        $columns_layout = !empty($settings['columnsLayout']) ? $settings['columnsLayout'] : '2';

        // Set column classes and styles
        $controls_style = '';
        if ($columns_layout === '2') {
            $controls_style = 'display: grid; grid-template-columns: 1fr 1fr;';
        }

        // Set data attributes
        $this->set_attribute('_root', 'class', 'td-helpers-container');
        $this->set_attribute('_root', 'id', $instance_id);
        $this->set_attribute('_root', 'data-product-id', $product_id);

        if (!empty($viewer_id)) {
            $this->set_attribute('_root', 'data-target-viewer', $viewer_id);
        }

        // Output HTML
        echo "<div {$this->render_attributes('_root')}>";

        // Controls section with grid layout - no header section
        echo '<div class="td-helpers-controls" style="' . esc_attr($controls_style) . '">';
        
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

                case 'text':
                    $this->render_text_control($node_id, $display_name, $param, $rgb_attrs);
                    break;

                case 'checkbox':
                    $this->render_checkbox_control($node_id, $display_name, $param, $rgb_attrs);
                    break;

                case 'dropdown':
                    $this->render_dropdown_control($node_id, $display_name, $param, $rgb_attrs);
                    break;
                
                // case 'color': not yet implemented for simplicity, add if needed
            }
        }
        
        echo '</div>'; // End controls
        echo '</div>'; // End container
        
        // Add inline script for initialization
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize helper element
            const helperElement = document.getElementById('<?php echo esc_js($instance_id); ?>');

            if (!helperElement) return;
            
            // Find product ID
            const productId = helperElement.dataset.productId;
            
            // Find target viewer (if specified) or auto-detect
            let targetViewer = null;
            <?php if (!empty($viewer_id)): ?>
            targetViewer = document.getElementById('<?php echo esc_js($viewer_id); ?>');
            <?php else: ?>
            // Auto-detect viewer element
            const viewerContainers = document.querySelectorAll('.td-viewer-container');
            for (const container of viewerContainers) {
                if (container.dataset.productId === productId) {
                    targetViewer = container;
                    break;
                }
            }
            <?php endif; ?>
            
            if (!targetViewer) {
                console.log('No target viewer found for helpers');
                return;
            }
            
            // Find the iframe inside the viewer
            const iframe = targetViewer.querySelector('iframe');
            if (!iframe) {
                console.log('No iframe found in target viewer');
                return;
            }
            
            console.log('Connected helpers to viewer iframe');
            
            // Connect controls
            const helperControls = helperElement.querySelectorAll('.polygonjs-control');
            
            helperControls.forEach(control => {
                const controlType = control.type || control.tagName.toLowerCase();
                const nodeId = control.closest('[data-node-id]')?.dataset.nodeId;
                
                if (!nodeId) return;
                
                // Add event listener based on control type
                switch (controlType) {
                    case 'range':
                    case 'number':
                    case 'text':
                    case 'select':
                        control.addEventListener('input', function() {
                            sendValueToIframe(iframe, nodeId, control.value);
                        });
                        break;
                        
                    case 'checkbox':
                        control.addEventListener('change', function() {
                            sendValueToIframe(iframe, nodeId, control.checked ? 1 : 0);
                        });
                        break;
                }
                
                // Trigger initial update after iframe is loaded
                iframe.addEventListener('load', function onceLoaded() {
                    // Remove this event to avoid multiple triggers
                    iframe.removeEventListener('load', onceLoaded);
                    
                    // Wait for bridge to be ready
                    setTimeout(() => {
                        // Trigger control update
                        if (controlType === 'checkbox') {
                            sendValueToIframe(iframe, nodeId, control.checked ? 1 : 0);
                        } else {
                            sendValueToIframe(iframe, nodeId, control.value);
                        }
                    }, 1000);
                });
            });
            
            // Listen for messages from iframe
            window.addEventListener('message', function(event) {
                // Check if message is from our iframe
                if (event.source !== iframe.contentWindow) return;
                
                // Handle modelReady event
                if (event.data && event.data.type === 'modelReady') {
                    console.log('Model is ready, sending all helper values');
                    
                    // Trigger all controls to send their values
                    setTimeout(() => {
                        helperControls.forEach(control => {
                            const nodeId = control.closest('[data-node-id]')?.dataset.nodeId;
                            if (!nodeId) return;
                            
                            if (control.type === 'checkbox') {
                                sendValueToIframe(iframe, nodeId, control.checked ? 1 : 0);
                            } else {
                                sendValueToIframe(iframe, nodeId, control.value);
                            }
                        });
                    }, 500);
                }
            });
            
            /**
             * Send parameter value to the iframe
             */
            function sendValueToIframe(iframe, nodeId, value) {
                // Convert to appropriate type
                if (!isNaN(value) && value !== '') {
                    value = parseFloat(value);
                }
                
                // Send message to iframe
                iframe.contentWindow.postMessage({
                    type: 'updateParam',
                    nodeId: nodeId,
                    value: value
                }, '*');
            }
        });
        </script>
        <?php
    }
}