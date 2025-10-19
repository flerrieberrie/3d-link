<?php
/**
 * Parameter Visibility Manager
 * 
 * Complete system for managing parameter group visibility based on variation selection
 * This is the core class that handles all parameter visibility logic
 */

defined('ABSPATH') || exit;

class TD_Parameter_Visibility_Manager {
    
    /**
     * Initialize the class
     */
    public function __construct() {
        // Frontend scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add parameter group data to frontend
        add_action('wp_footer', [$this, 'output_variation_visibility_data']);
        
        // Admin interface hooks
        add_action('woocommerce_product_after_variable_attributes', [$this, 'add_variation_parameter_groups'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_parameter_groups'], 10, 2);
        
        // Modify frontend parameter display to include group data
        add_filter('td_parameter_output_attributes', [$this, 'add_group_attributes'], 10, 2);
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_product()) return;
        
        // Get product safely
        global $product;
        if (!$product || !is_a($product, 'WC_Product')) {
            $product = wc_get_product(get_the_ID());
        }
        
        if (!$product || !is_a($product, 'WC_Product') || !$product->is_type('variable')) return;
        
        // Check if product has PolygonJS enabled
        if (get_post_meta($product->get_id(), '_enable_polygonjs', true) !== 'yes') return;
        
        wp_enqueue_script(
            'td-parameter-visibility',
            TD_LINK_URL . 'assets/js/parameter-visibility.js',
            ['jquery', 'wc-add-to-cart-variation'],
            TD_LINK_VERSION,
            true
        );
        
        wp_enqueue_style(
            'td-parameter-visibility',
            TD_LINK_URL . 'assets/css/parameter-visibility.css',
            [],
            TD_LINK_VERSION
        );
    }
    
    /**
     * Output variation to parameter group mapping data for JavaScript
     */
    public function output_variation_visibility_data() {
        if (!is_product()) return;
        
        // Get product safely
        global $product;
        if (!$product || !is_a($product, 'WC_Product')) {
            $product = wc_get_product(get_the_ID());
        }
        
        if (!$product || !is_a($product, 'WC_Product') || !$product->is_type('variable')) return;
        
        // Check if product has PolygonJS enabled
        if (get_post_meta($product->get_id(), '_enable_polygonjs', true) !== 'yes') return;
        
        $variation_mappings = $this->get_variation_group_mappings($product->get_id());
        $parameter_groups = $this->get_parameter_groups_with_parameters($product->get_id());
        
        if (empty($variation_mappings) && empty($parameter_groups)) return;
        
        ?>
        <script type="text/javascript">
        window.tdParameterVisibility = {
            variationMappings: <?php echo json_encode($variation_mappings); ?>,
            parameterGroups: <?php echo json_encode($parameter_groups); ?>,
            productId: <?php echo $product->get_id(); ?>
        };
        console.log('[TD Parameter Visibility] Initialized with data:', window.tdParameterVisibility);
        </script>
        <?php
    }
    
    /**
     * Get variation to parameter group mappings
     */
    public function get_variation_group_mappings($product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) return [];
        
        $mappings = [];
        $variations = $product->get_available_variations();
        
        foreach ($variations as $variation) {
            $variation_id = $variation['variation_id'];
            $assigned_groups = get_post_meta($variation_id, '_parameter_groups', true);
            
            if (!empty($assigned_groups) && is_array($assigned_groups)) {
                $mappings[$variation_id] = [
                    'attributes' => $variation['attributes'],
                    'groups' => $assigned_groups
                ];
            }
        }
        
        return $mappings;
    }
    
    /**
     * Get parameter groups with their parameters
     */
    public function get_parameter_groups_with_parameters($product_id) {
        $parameters_manager = new TD_Parameters_Manager();
        $parameters = $parameters_manager->get_parameters($product_id);
        
        if (empty($parameters)) return [];
        
        $groups = [];
        
        foreach ($parameters as $index => $parameter) {
            $group_name = isset($parameter['group_name']) ? trim($parameter['group_name']) : '';
            
            if (!empty($group_name)) {
                if (!isset($groups[$group_name])) {
                    $groups[$group_name] = [
                        'name' => $group_name,
                        'parameters' => []
                    ];
                }
                
                $groups[$group_name]['parameters'][] = [
                    'index' => $index,
                    'node_id' => $parameter['node_id'] ?? '',
                    'display_name' => $parameter['display_name'] ?? '',
                    'control_type' => $parameter['control_type'] ?? ''
                ];
            }
        }
        
        return $groups;
    }
    
    /**
     * Add parameter group fields to variation admin interface
     */
    public function add_variation_parameter_groups($loop, $variation_data, $variation) {
        $product_id = $variation->post_parent;
        $groups = $this->get_available_parameter_groups($product_id);
        
        if (empty($groups)) {
            echo '<p class="description">' . esc_html__('No parameter groups found. Create parameter groups by assigning "Group Name" to parameters in the 3D Parameters section.', 'td-link') . '</p>';
            return;
        }
        
        $variation_groups = get_post_meta($variation->ID, '_parameter_groups', true);
        if (!is_array($variation_groups)) {
            $variation_groups = [];
        }
        
        ?>
        <div class="form-row form-row-full td-parameter-visibility-section">
            <label><strong><?php esc_html_e('Parameter Group Visibility', 'td-link'); ?></strong></label>
            <div class="parameter-groups-assignment">
                <p class="description" style="margin-bottom: 10px;">
                    <?php esc_html_e('Select which parameter groups should be visible when this variation is selected. Only checked groups will appear to customers.', 'td-link'); ?>
                </p>
                
                <?php foreach ($groups as $group_name => $group_data): ?>
                    <label class="parameter-group-checkbox" style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" 
                               name="variable_parameter_groups[<?php echo $loop; ?>][]" 
                               value="<?php echo esc_attr($group_name); ?>"
                               <?php checked(in_array($group_name, $variation_groups)); ?>
                               style="margin-right: 8px;">
                        <strong><?php echo esc_html($group_data['name']); ?></strong>
                        <span style="color: #666; font-size: 0.9em;">
                            (<?php echo sprintf(_n('%d parameter', '%d parameters', $group_data['count'], 'td-link'), $group_data['count']); ?>)
                        </span>
                    </label>
                <?php endforeach; ?>
                
                <div style="margin-top: 12px; padding: 8px; background: #f0f6ff; border-left: 3px solid #4a90e2; font-size: 0.9em;">
                    <strong><?php esc_html_e('How it works:', 'td-link'); ?></strong><br>
                    <?php esc_html_e('When customers select this variation, only the checked parameter groups will be visible. Ungrouped parameters are always visible.', 'td-link'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save variation parameter groups
     */
    public function save_variation_parameter_groups($variation_id, $loop) {
        if (isset($_POST['variable_parameter_groups'][$loop])) {
            $groups = array_map('sanitize_text_field', $_POST['variable_parameter_groups'][$loop]);
            update_post_meta($variation_id, '_parameter_groups', $groups);
        } else {
            delete_post_meta($variation_id, '_parameter_groups');
        }
    }
    
    /**
     * Get available parameter groups for a product
     */
    public function get_available_parameter_groups($product_id) {
        $parameters_manager = new TD_Parameters_Manager();
        $parameters = $parameters_manager->get_parameters($product_id);
        
        if (empty($parameters)) return [];
        
        $groups = [];
        
        foreach ($parameters as $parameter) {
            $group_name = isset($parameter['group_name']) ? trim($parameter['group_name']) : '';
            
            if (!empty($group_name)) {
                if (!isset($groups[$group_name])) {
                    $groups[$group_name] = [
                        'name' => $group_name,
                        'count' => 0
                    ];
                }
                $groups[$group_name]['count']++;
            }
        }
        
        return $groups;
    }
    
    /**
     * Add group attributes to parameter output
     */
    public function add_group_attributes($attributes, $parameter) {
        $group_name = isset($parameter['group_name']) ? trim($parameter['group_name']) : '';
        
        if (!empty($group_name)) {
            $attributes['data-parameter-group'] = esc_attr($group_name);
            $attributes['data-has-group'] = 'true';
        } else {
            $attributes['data-has-group'] = 'false';
        }
        
        return $attributes;
    }
    
    /**
     * Check if a parameter should be visible for a variation
     */
    public function is_parameter_visible_for_variation($product_id, $variation_id, $parameter) {
        // Ungrouped parameters are always visible
        $group_name = isset($parameter['group_name']) ? trim($parameter['group_name']) : '';
        if (empty($group_name)) {
            return true;
        }
        
        // Check if this group is assigned to the variation
        $variation_groups = get_post_meta($variation_id, '_parameter_groups', true);
        if (!is_array($variation_groups)) {
            return false;
        }
        
        return in_array($group_name, $variation_groups);
    }
    
    /**
     * Get visible parameters for a specific variation
     */
    public function get_visible_parameters_for_variation($product_id, $variation_id) {
        $parameters_manager = new TD_Parameters_Manager();
        $all_parameters = $parameters_manager->get_parameters($product_id);
        
        if (empty($all_parameters)) return [];
        
        $visible_parameters = [];
        
        foreach ($all_parameters as $index => $parameter) {
            if ($this->is_parameter_visible_for_variation($product_id, $variation_id, $parameter)) {
                $parameter['index'] = $index;
                $visible_parameters[] = $parameter;
            }
        }
        
        return $visible_parameters;
    }
}