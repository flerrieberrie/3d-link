<?php
/**
 * Plugin Name: 3D Link
 * Description: Adds custom product customization for 3D products with WooCommerce and PolygonJS integration
 * Version: 0.5
 * Author: TD Link
 * Text Domain: td-link
 * Domain Path: /languages
 * 
 * GitHub Plugin URI: flerrieberrie/3d-link
 * Primary Branch: main
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TD_LINK_VERSION', '2.3.0');
define('TD_LINK_PATH', plugin_dir_path(__FILE__));
define('TD_LINK_URL', plugin_dir_url(__FILE__));
define('TD_LINK_FILE', __FILE__);

/**
 * Main plugin class
 */
class TD_Link {
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    public $parameters_manager;
    public $sections_manager;
    public $frontend_display;
    public $cart_handler;
    public $colors_manager;
    public $models_manager;
    
    /**
     * Get the singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Load translations
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        add_action('plugins_loaded', [$this, 'init'], 20);
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'td-link',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Include core files
        require_once TD_LINK_PATH . 'includes/class-universal-parameter-parser.php';
        require_once TD_LINK_PATH . 'includes/class-universal-frontend-handler.php';
        require_once TD_LINK_PATH . 'includes/class-parameters-manager.php';
        require_once TD_LINK_PATH . 'includes/class-sections-manager.php';
        require_once TD_LINK_PATH . 'includes/class-frontend-display.php';
        require_once TD_LINK_PATH . 'includes/class-frontend-cart.php';
        require_once TD_LINK_PATH . 'includes/class-assets-manager.php';
        require_once TD_LINK_PATH . 'includes/class-colors-manager.php';
        require_once TD_LINK_PATH . 'includes/class-glb-download-handler.php';
        require_once TD_LINK_PATH . 'includes/class-models-manager.php';
        require_once TD_LINK_PATH . 'includes/class-debug-helper.php';
        require_once TD_LINK_PATH . 'includes/class-color-sync.php';
        require_once TD_LINK_PATH . 'includes/class-parameter-sync.php';
        require_once TD_LINK_PATH . 'includes/class-unified-parameter-sync.php';
        
        // Dashboard widgets and admin interfaces
        require_once TD_LINK_PATH . 'admin/admin-dashboard-widget.php';
        require_once TD_LINK_PATH . 'admin/admin-universal-parameters.php';
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }
        
        // Initialize components
        $this->parameters_manager = new TD_Parameters_Manager();
        $this->sections_manager = new TD_Sections_Manager();
        $this->frontend_display = new TD_Frontend_Display();
        $this->cart_handler = new TD_Frontend_Cart();
        $this->colors_manager = new TD_Colors_Manager();
        $this->models_manager = new TD_Models_Manager();
        $assets_manager = new TD_Assets_Manager();
        $glb_download_handler = new TD_GLTF_Download_Handler();
        
        // Initialize assets manager
        $assets_manager->init();
        
        // Initialize sync systems
        TD_Color_Sync::init();
        TD_Parameter_Sync::init();
    
        // Add debug script hook
        add_action('wp_enqueue_scripts', [$this, 'enqueue_debug_script'], 99);
        
        // Register Bricks elements
        add_action('init', [$this, 'register_bricks_elements'], 11);
        
        // Add Polygon path field
        $this->add_polygonjs_path_field();
        
        // Register admin menu with improved structure
        add_action('admin_menu', [$this, 'register_admin_menus'], 59); // Priority 59 to position after WooCommerce (55)
    }
    
    /**
     * Register admin menus with improved structure
     */
    public function register_admin_menus() {
        // Main menu item with 3D cube icon - positioned after WooCommerce
        add_menu_page(
            __('3D Link', 'td-link'),
            __('3D Link', 'td-link'),
            'manage_options',
            'td-link',
            [$this, 'render_main_page'],
            'data:image/svg+xml;base64,' . base64_encode($this->get_menu_icon_svg()),
            56 // Position after WooCommerce (55)
        );
        
        // Add main dashboard as submenu
        add_submenu_page(
            'td-link',
            __('Dashboard', 'td-link'),
            __('Dashboard', 'td-link'),
            'manage_options',
            'td-link',
            [$this, 'render_main_page']
        );
        
        // Colors submenu - using the existing colors manager
        add_submenu_page(
            'td-link',
            __('Colors', 'td-link'),
            __('Colors', 'td-link'),
            'manage_options',
            'td-colors',
            [$this->colors_manager, 'render_colors_page']
        );
        
        // Customer models browser submenu
        add_submenu_page(
            'td-link',
            __('Customer Models', 'td-link'),
            __('Customer Models', 'td-link'),
            'manage_options',
            'td-models',
            [$this, 'render_models_page']
        );
        
        // Settings submenu - moved to last position
        add_submenu_page(
            'td-link',
            __('Settings', 'td-link'),
            __('Settings', 'td-link'),
            'manage_options',
            'td-link-settings',
            [$this, 'render_settings_page']
        );
        
        
        // Model viewer page (hidden from menu)
        add_submenu_page(
            null, // No parent - hidden from menu
            __('3D Model Viewer', 'td-link'),
            __('3D Model Viewer', 'td-link'),
            'manage_options',
            'td-model-viewer',
            [$this, 'render_model_viewer_page']
        );
        
        // Remove the old standalone menu for colors if it exists
        remove_menu_page('td-colors');
    }
    
    /**
     * Render the main plugin dashboard page
     */
    public function render_main_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the dashboard template
        include_once TD_LINK_PATH . 'admin/admin-dashboard.php';
    }
    
    /**
     * Render the settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the settings template
        include_once TD_LINK_PATH . 'admin/admin-settings.php';
    }
    
    /**
     * Render the customer models browser page
     */
    public function render_models_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the models browser template
        include_once TD_LINK_PATH . 'admin/admin-customer-models.php';
    }
    
    
    /**
     * Render the 3D model viewer page
     */
    public function render_model_viewer_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get model ID and order ID from URL parameters
        $model_id = isset($_GET['model_id']) ? intval($_GET['model_id']) : 0;
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
        
        if (!$model_id) {
            wp_die(__('Model ID is required.', 'td-link'));
        }
        
        // Get the model data
        $models_manager = new TD_Models_Manager();
        $model_data = $models_manager->get_model($model_id);
        
        if (!$model_data) {
            wp_die(__('Model not found.', 'td-link'));
        }
        
        // Enqueue admin styles and scripts
        wp_enqueue_style(
            'td-admin-model-viewer-style',
            TD_LINK_URL . 'assets/css/admin-customer-models.css',
            [],
            filemtime(TD_LINK_PATH . 'assets/css/admin-customer-models.css')
        );
        
        // Add the JavaScript for the model viewer
        wp_enqueue_script(
            'td-admin-model-preview',
            TD_LINK_URL . 'assets/js/admin-model-preview.js',
            ['jquery'],
            filemtime(TD_LINK_PATH . 'assets/js/admin-model-preview.js'),
            true
        );
        
        // Include the model viewer template
        include_once TD_LINK_PATH . 'admin/admin-model-viewer.php';
    }
    
    /**
     * Get the SVG for the 3D cube menu icon
     */
    public function get_menu_icon_svg() {
        // Simple 3D cube SVG icon
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
            <line x1="12" y1="22.08" x2="12" y2="12"></line>
        </svg>';
    }
    
    /**
     * Add PolygonJS path field
     */
    private function add_polygonjs_path_field() {
        add_action('woocommerce_product_options_general_product_data', function() {
            global $post;
            
            echo '<div class="options_group">';
            echo '<h4 style="padding-left: 12px;">' . esc_html__('PolygonJS Integration', 'td-link') . '</h4>';
            
            woocommerce_wp_checkbox([
                'id' => "_enable_polygonjs",
                'label' => __('Enable PolygonJS', 'td-link'),
                'value' => get_post_meta($post->ID, '_enable_polygonjs', true) ?: 'no',
                'cbvalue' => 'yes',
            ]);
            
            woocommerce_wp_text_input([
                'id' => "_td_dist_path",
                'label' => __('PolygonJS Dist Path', 'td-link'),
                'desc_tip' => true,
                'description' => __('Path to the PolygonJS dist folder relative to site root. Default: 3d/dist', 'td-link'),
                'placeholder' => '3d/dist',
                'value' => get_post_meta($post->ID, '_td_dist_path', true) ?: '3d/dist',
            ]);
            
            woocommerce_wp_text_input([
                'id' => "_td_scene_name",
                'label' => __('Scene Name', 'td-link'),
                'desc_tip' => true,
                'description' => __('Name of the scene to load (used as ?scene=NAME in URL). If left empty, product slug will be used.', 'td-link'),
                'placeholder' => 'my-scene-name',
                'value' => get_post_meta($post->ID, '_td_scene_name', true),
            ]);
            
            // Add exporter node path field
            woocommerce_wp_text_input([
                'id' => "_td_exporter_node_path",
                'label' => __('Exporter Node Path', 'td-link'),
                'desc_tip' => true,
                'description' => __('Path to the exporterGLTF node in your PolygonJS scene. If left empty, the global default or auto-detected path will be used.', 'td-link'),
                'placeholder' => '/geo1/exporterGLTF1',
                'value' => get_post_meta($post->ID, '_td_exporter_node_path', true),
            ]);
            
            // Keep the old field for backward compatibility
            woocommerce_wp_text_input([
                'id' => "_td_polygonjs_folder",
                'label' => __('Legacy 3D Model Folder Path (Deprecated)', 'td-link'),
                'desc_tip' => true,
                'description' => __('Old path format. Recommended to use the new fields above instead.', 'td-link'),
                'placeholder' => '3d/{product-slug}',
                'value' => get_post_meta($post->ID, '_td_polygonjs_folder', true),
            ]);
            
            echo '</div>';
        });
        
        add_action('woocommerce_process_product_meta', function($post_id) {
            // Save PolygonJS enabled setting
            update_post_meta($post_id, '_enable_polygonjs', isset($_POST['_enable_polygonjs']) ? 'yes' : 'no');
            
            // Save dist path
            $dist_path = isset($_POST['_td_dist_path']) ? 
                sanitize_text_field($_POST['_td_dist_path']) : '3d/dist';
            update_post_meta($post_id, '_td_dist_path', $dist_path);
            
            // Save scene name
            $scene_name = isset($_POST['_td_scene_name']) ? 
                sanitize_text_field($_POST['_td_scene_name']) : '';
            update_post_meta($post_id, '_td_scene_name', $scene_name);
            
            // Save exporter node path
            $exporter_path = isset($_POST['_td_exporter_node_path']) ? 
                sanitize_text_field($_POST['_td_exporter_node_path']) : '';
            update_post_meta($post_id, '_td_exporter_node_path', $exporter_path);
            
            // Save legacy folder path (for backward compatibility)
            $folder_path = isset($_POST['_td_polygonjs_folder']) ? 
                sanitize_text_field($_POST['_td_polygonjs_folder']) : '';
            update_post_meta($post_id, '_td_polygonjs_folder', $folder_path);
        });
    }
    
    /**
     * Register Bricks elements
     */
    public function register_bricks_elements() {
        // Only register if Bricks is active
        if (class_exists('Bricks\Elements')) {
            // Register styles and scripts for the Bricks elements
            add_action('wp_enqueue_scripts', function() {
                // Register styles
                wp_register_style(
                    'td_polygonjs_viewer_style',
                    TD_LINK_URL . 'widgets/polygonjs-viewer/css/polygonjs-viewer.css',
                    [],
                    TD_LINK_VERSION
                );

                wp_register_style(
                    'td_helpers_style',
                    TD_LINK_URL . 'widgets/polygonjs-helpers/css/polygonjs-helpers.css',
                    [],
                    time() // Force cache invalidation during development
                );

                // Register scripts
                wp_register_script(
                    'td_polygonjs_viewer_script',
                    TD_LINK_URL . 'widgets/polygonjs-viewer/js/polygonjs-viewer.js',
                    ['jquery'],
                    TD_LINK_VERSION,
                    true
                );

                wp_register_script(
                    'td_helpers_script',
                    TD_LINK_URL . 'widgets/polygonjs-helpers/js/polygonjs-helpers.js',
                    ['jquery'],
                    time(), // Force cache invalidation during development
                    true
                );
            });

            // Register Bricks elements
            $element_files = [
                TD_LINK_PATH . 'widgets/polygonjs-viewer/polygonjs-viewer.php',
                TD_LINK_PATH . 'widgets/polygonjs-helpers/polygonjs-helpers.php',
            ];

            foreach ($element_files as $file) {
                if (file_exists($file)) {
                    \Bricks\Elements::register_element($file);
                }
            }
        }
    }
    
    /**
     * Display admin notice if WooCommerce is missing
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('3D Link requires WooCommerce to be installed and active.', 'td-link'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create necessary directories
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/td-link';
        
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        // Create custom models directory
        $models_dir = $upload_dir['basedir'] . '/wp-content/3d/custom-models';
        if (!file_exists($models_dir)) {
            wp_mkdir_p($models_dir);
            
            // Create index.php file
            $index_file = trailingslashit($models_dir) . 'index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden');
            }
        }
        
        // Set default options
        add_option('td_link_default_dist_path', '3d/dist');
        add_option('td_link_enable_debug', 'no');
        add_option('td_link_default_aspect_ratio', '16:9');
        
        // Create the database table for models
        if (class_exists('TD_Models_Manager')) {
            $models_manager = new TD_Models_Manager();
            $models_manager->create_table();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear the scheduled cleanup event
        wp_clear_scheduled_hook('td_cleanup_abandoned_models');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Enqueue debug script on product pages
     */
    public function enqueue_debug_script() {
        if (!is_product() || !isset($_GET['debug'])) return;
        
        wp_enqueue_script(
            'td-debug-connector',
            TD_LINK_URL . 'assets/js/frontend-debug.js',
            ['jquery'],
            filemtime(TD_LINK_PATH . 'assets/js/frontend-debug.js'),
            true
        );
    }
    
    /**
     * Get PolygonJS node mappings for a product
     * Used by Bricks elements and other integration points
     */
    public function get_polygonjs_node_mappings($product_id) {
        $mappings = [];
        
        // Get parameters
        $parameters = $this->parameters_manager->get_parameters($product_id);
        
        // Collect RGB group mappings
        $rgb_groups = [];
        foreach ($parameters as $param) {
            if (!empty($param['is_rgb_component']) && !empty($param['rgb_group'])) {
                if (!isset($rgb_groups[$param['rgb_group']])) {
                    $rgb_groups[$param['rgb_group']] = [
                        'components' => [],
                        'display_name' => $param['display_name']
                    ];
                }
                $rgb_groups[$param['rgb_group']]['components'][$param['is_rgb_component']] = [
                    'node_id' => $param['node_id'],
                    'path' => '/' . str_replace('-', '/', $param['node_id']),
                    'param' => $param['is_rgb_component'] === 'r' ? 'colorr' : 
                              ($param['is_rgb_component'] === 'g' ? 'colorg' : 'colorb')
                ];
                
                // If this is the main (R) component, update the group display name
                if ($param['is_rgb_component'] === 'r') {
                    $rgb_groups[$param['rgb_group']]['display_name'] = $param['display_name'];
                }
            }
        }
        
        // Create mappings for RGB groups
        foreach ($rgb_groups as $group_id => $group) {
            if (count($group['components']) === 3) {
                $mappings['case_color'] = [
                    'type' => 'rgb_group',
                    'is_rgb_group' => true,
                    'display_name' => $group['display_name'],
                    'components' => [
                        'r' => $group['components']['r']['path'],
                        'g' => $group['components']['g']['path'],
                        'b' => $group['components']['b']['path']
                    ]
                ];
                break; // Just use the first complete RGB group for now
            }
        }
        
        // Add mappings for other common parameters
        foreach ($parameters as $param) {
            $node_id = $param['node_id'];
            $display_name = $param['display_name'];
            
            // Skip if already part of an RGB group
            if (!empty($param['is_rgb_component'])) {
                continue;
            }
            
            $lc_name = strtolower($display_name);
            
            // Add mappings for common parameters
            if (strpos($lc_name, 'height') !== false) {
                $mappings['height'] = [
                    'type' => 'node',
                    'path' => '/' . str_replace('-', '/', $node_id),
                    'param' => 'value'
                ];
            }
            else if (strpos($lc_name, 'width') !== false) {
                $mappings['width'] = [
                    'type' => 'node',
                    'path' => '/' . str_replace('-', '/', $node_id),
                    'param' => 'value'
                ];
            }
            else if (strpos($lc_name, 'depth') !== false) {
                $mappings['depth'] = [
                    'type' => 'node',
                    'path' => '/' . str_replace('-', '/', $node_id),
                    'param' => 'value'
                ];
            }
            else if (strpos($lc_name, 'shelves') !== false || strpos($lc_name, 'count') !== false) {
                $mappings['shelves'] = [
                    'type' => 'node',
                    'path' => '/' . str_replace('-', '/', $node_id),
                    'param' => 'value'
                ];
            }
            else if (strpos($lc_name, 'text') !== false) {
                $mappings['custom_text'] = [
                    'type' => 'node',
                    'path' => '/' . str_replace('-', '/', $node_id),
                    'param' => 'value'
                ];
            }
        }
        
        return $mappings;
    }
}

// Initialize the plugin
function td_link() {
    return TD_Link::instance();
}

// Start the plugin
$GLOBALS['td_link'] = td_link();