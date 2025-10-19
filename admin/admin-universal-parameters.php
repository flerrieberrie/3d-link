<?php
/**
 * Admin interface for Universal Parameter System
 * 
 * Provides tools for testing, validating, and managing the universal parameter system
 */

defined('ABSPATH') || exit;

class TD_Admin_Universal_Parameters {
    
    /**
     * Initialize the admin interface
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_test_universal_parser', [$this, 'ajax_test_parser']);
    }
    
    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'td-dashboard',
            __('Universal Parameters', 'td-link'),
            __('Universal Parameters', 'td-link'),
            'manage_options',
            'td-universal-parameters',
            [$this, 'render_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'td-dashboard_page-td-universal-parameters') {
            return;
        }
        
        wp_enqueue_script('td-universal-admin', TD_LINK_URL . 'assets/js/admin-universal.js', ['jquery'], TD_LINK_VERSION, true);
        wp_localize_script('td-universal-admin', 'tdUniversalAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('td_universal_admin')
        ]);
    }
    
    /**
     * Render the admin page
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Universal Parameter System', 'td-link'); ?></h1>
            
            <div class="td-universal-admin">
                <!-- HTML Snippet Parser Testing -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Test HTML Snippet Parser', 'td-link'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Test the universal parser by pasting HTML snippets from PolygonJS:', 'td-link'); ?></p>
                        
                        <form id="test-parser-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('HTML Snippet', 'td-link'); ?></th>
                                    <td>
                                        <textarea id="test-html-snippet" rows="5" cols="80" placeholder="<label for='example-node-param'>example-node-param</label><input type='number' id='example-node-param' min='1' max='10' step='0.1' value='5'></input>"></textarea>
                                        <p class="description"><?php _e('Paste an HTML snippet from your PolygonJS project to see how it will be parsed.', 'td-link'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="button" id="test-parser-btn" class="button button-primary"><?php _e('Test Parser', 'td-link'); ?></button>
                                <button type="button" id="add-examples-btn" class="button"><?php _e('Load Examples', 'td-link'); ?></button>
                            </p>
                        </form>
                        
                        <div id="parser-results" style="display:none;">
                            <h3><?php _e('Parser Results', 'td-link'); ?></h3>
                            <div id="parser-output"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Product Validation -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Product Parameter Validation', 'td-link'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Validate parameter configurations for products with PolygonJS enabled:', 'td-link'); ?></p>
                        
                        <?php $this->render_product_validation(); ?>
                    </div>
                </div>
                
                <!-- System Overview -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('System Overview', 'td-link'); ?></h2>
                    <div class="inside">
                        <?php $this->render_system_overview(); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .td-universal-admin .postbox {
            margin-bottom: 20px;
        }
        
        .parser-result {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 15px;
            border-radius: 4px;
        }
        
        .parser-result pre {
            background: white;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
            overflow-x: auto;
            font-size: 12px;
        }
        
        .validation-item {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .validation-item h4 {
            margin: 0 0 10px 0;
        }
        
        .validation-success { border-color: #46b450; background: #f7fff7; }
        .validation-warning { border-color: #ffb900; background: #fffbf0; }
        .validation-error { border-color: #dc3232; background: #ffeaea; }
        
        .system-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * Render product validation section
     */
    private function render_product_validation() {
        // Get all products with PolygonJS enabled
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_enable_polygonjs',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ]
        ]);
        
        if (empty($products)) {
            echo '<p>' . __('No products with PolygonJS enabled found.', 'td-link') . '</p>';
            return;
        }
        
        $parameters_manager = new TD_Parameters_Manager();
        
        foreach ($products as $product) {
            $validation = $parameters_manager->validate_product_parameters($product->ID);
            $parameters = $parameters_manager->get_parameters($product->ID);
            
            $class = 'validation-success';
            $status = '✅ Valid';
            
            if (!empty($validation['errors'])) {
                $class = 'validation-error';
                $status = '❌ Has Errors';
            } elseif (!empty($validation['warnings'])) {
                $class = 'validation-warning';
                $status = '⚠️ Has Warnings';
            }
            
            echo '<div class="validation-item ' . $class . '">';
            echo '<h4>' . esc_html($product->post_title) . ' <span style="font-weight:normal;">(' . $status . ')</span></h4>';
            echo '<p><strong>Parameters:</strong> ' . count($parameters) . ' | ';
            echo '<strong>Product ID:</strong> ' . $product->ID . ' | ';
            echo '<a href="' . get_permalink($product->ID) . '" target="_blank">View Product</a> | ';
            echo '<a href="' . admin_url('post.php?post=' . $product->ID . '&action=edit') . '">Edit</a></p>';
            
            if (!empty($validation['errors']) || !empty($validation['warnings']) || !empty($validation['suggestions'])) {
                echo TD_Universal_Frontend_Handler::generate_validation_report($product->ID);
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Render system overview
     */
    private function render_system_overview() {
        // Collect statistics
        $total_products = wp_count_posts('product')->publish;
        
        $polygonjs_products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_enable_polygonjs',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ],
            'fields' => 'ids'
        ]);
        
        $total_parameters = 0;
        $parameters_manager = new TD_Parameters_Manager();
        
        foreach ($polygonjs_products as $product_id) {
            $parameters = $parameters_manager->get_parameters($product_id);
            $total_parameters += count($parameters);
        }
        
        echo '<div class="system-stats">';
        echo '<div class="stat-box">';
        echo '<div class="stat-number">' . count($polygonjs_products) . '</div>';
        echo '<div class="stat-label">Products with PolygonJS</div>';
        echo '</div>';
        
        echo '<div class="stat-box">';
        echo '<div class="stat-number">' . $total_parameters . '</div>';
        echo '<div class="stat-label">Total Parameters</div>';
        echo '</div>';
        
        echo '<div class="stat-box">';
        echo '<div class="stat-number">' . ($total_parameters > 0 ? round($total_parameters / count($polygonjs_products), 1) : 0) . '</div>';
        echo '<div class="stat-label">Avg Parameters per Product</div>';
        echo '</div>';
        
        echo '<div class="stat-box">';
        echo '<div class="stat-number">' . TD_LINK_VERSION . '</div>';
        echo '<div class="stat-label">Plugin Version</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<h4>' . __('Universal Parameter System Features', 'td-link') . '</h4>';
        echo '<ul>';
        echo '<li>✅ ' . __('Automatic parameter parsing from HTML snippets', 'td-link') . '</li>';
        echo '<li>✅ ' . __('Dynamic JavaScript control generation', 'td-link') . '</li>';
        echo '<li>✅ ' . __('Universal frontend/backend parameter mapping', 'td-link') . '</li>';
        echo '<li>✅ ' . __('Real-time parameter validation', 'td-link') . '</li>';
        echo '<li>✅ ' . __('Support for any PolygonJS scene structure', 'td-link') . '</li>';
        echo '<li>✅ ' . __('Backward compatibility with existing configurations', 'td-link') . '</li>';
        echo '</ul>';
        
        echo '<h4>' . __('How It Works', 'td-link') . '</h4>';
        echo '<ol>';
        echo '<li>' . __('Admin pastes HTML snippets from PolygonJS into product parameters', 'td-link') . '</li>';
        echo '<li>' . __('Universal parser automatically extracts node paths, parameter names, and control types', 'td-link') . '</li>';
        echo '<li>' . __('System generates appropriate UI controls and JavaScript bindings', 'td-link') . '</li>';
        echo '<li>' . __('Frontend automatically works with any parameter structure without hardcoding', 'td-link') . '</li>';
        echo '</ol>';
    }
    
    /**
     * AJAX handler for testing the parser
     */
    public function ajax_test_parser() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'td_universal_admin')) {
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
            wp_send_json_success([
                'parsed' => $parsed,
                'formatted' => json_encode($parsed, JSON_PRETTY_PRINT)
            ]);
        } else {
            wp_send_json_error('Could not parse HTML snippet. Please check the format.');
        }
    }
}

// Initialize the admin interface
new TD_Admin_Universal_Parameters();