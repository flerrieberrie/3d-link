<?php
/**
 * GLTF Download Handler
 * 
 * Handles downloading 3D models in GLTF format from the WordPress admin interface.
 * 
 * @package TD_Link
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * TD_GLTF_Download_Handler Class
 */
class TD_GLTF_Download_Handler {
    
    /**
     * Initialize the class
     */
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_td_download_model', array($this, 'handle_model_download'));
        add_action('wp_ajax_td_download_order_models', array($this, 'handle_order_models_download'));
        add_action('wp_ajax_td_download_cart_models', array($this, 'handle_cart_models_download'));
        
        // Add download buttons to admin interface
        add_action('td_after_model_viewer', array($this, 'add_download_button'), 10, 3);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // We don't need an activation hook here as we've added a separate function below
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin's pages
        if (strpos($hook, 'td-models') === false) {
            return;
        }
        
        wp_enqueue_script(
            'td-gltf-download',
            TD_LINK_URL . 'assets/js/admin-download-handler.js',
            array('jquery'),
            TD_LINK_VERSION,
            true
        );
        
        wp_localize_script('td-gltf-download', 'tdGltfDownload', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('td_download_model_nonce'),
            'downloadingText' => __('Downloading...', 'td-link'),
            'errorText' => __('Error downloading model', 'td-link'),
        ));
    }
    
    /**
     * Add download button to model viewer
     * 
     * @param int $model_index Index of the model in the current view
     * @param array $item The model item data
     * @param string $context The context (order or cart)
     */
    public function add_download_button($model_index, $item, $context) {
        $button_id = "td-download-model-{$model_index}";
        $item_id = isset($item['item_id']) ? $item['item_id'] : '';
        $item_key = isset($item['item_key']) ? $item['item_key'] : '';
        $scene_name = isset($item['scene_name']) ? $item['scene_name'] : '';
        $product_id = isset($item['product_id']) ? $item['product_id'] : '';
        
        echo '<div class="td-model-download-actions">';
        echo '<button type="button" id="' . esc_attr($button_id) . '" class="button td-download-model-btn" ';
        echo 'data-scene="' . esc_attr($scene_name) . '" ';
        echo 'data-product-id="' . esc_attr($product_id) . '" ';
        echo 'data-item-id="' . esc_attr($item_id) . '" ';
        echo 'data-item-key="' . esc_attr($item_key) . '" ';
        echo 'data-context="' . esc_attr($context) . '" ';
        echo 'data-index="' . esc_attr($model_index) . '">';
        echo '<span class="dashicons dashicons-download"></span> ' . __('Download GLTF', 'td-link');
        echo '</button>';
        echo '</div>';
    }
    
    /**
     * Handle AJAX request to download a single model
     */
    public function handle_model_download() {
        // Log request for debugging
        error_log('TD Link: Model download request received - ' . json_encode($_POST));
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'td_download_model_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'td-link')));
            return;
        }
        
        // Get parameters
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : '';
        $item_id = isset($_POST['item_id']) ? sanitize_text_field($_POST['item_id']) : '';
        $item_key = isset($_POST['item_key']) ? sanitize_text_field($_POST['item_key']) : '';
        $scene_name = isset($_POST['scene']) ? sanitize_text_field($_POST['scene']) : '';
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (empty($scene_name)) {
            wp_send_json_error(array('message' => __('Scene name is required.', 'td-link')));
        }
        
        // Get the model parameters
        $parameters = array();
        
        if ($context === 'order' && !empty($item_id)) {
            $parameters = wc_get_order_item_meta($item_id, 'td_parameters', true);
        } elseif ($context === 'cart' && !empty($item_key)) {
            // Get cart item parameters
            if ($item_key === 'example_item') {
                // Example item for demo
                $parameters = array(
                    'width' => array('display_name' => 'Width', 'value' => '100', 'control_type' => 'number'),
                    'height' => array('display_name' => 'Height', 'value' => '200', 'control_type' => 'number'),
                    'color' => array('display_name' => 'Color', 'value' => '#FF0000', 'control_type' => 'color')
                );
            } else {
                // Try to get from session
                $cart_items = WC()->cart ? WC()->cart->get_cart() : array();
                if (isset($cart_items[$item_key]) && isset($cart_items[$item_key]['td_parameters'])) {
                    $parameters = $cart_items[$item_key]['td_parameters'];
                }
            }
        }
        
        // Get the product info
        $product = wc_get_product($product_id);
        $dist_path = get_post_meta($product_id, '_td_dist_path', true) ?: '3d/dist';
        
        // Generate the GLTF file
        $result = $this->generate_gltf($scene_name, $parameters, $dist_path);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'download_url' => $result['download_url'],
                'message' => __('Model ready for download.', 'td-link')
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Handle AJAX request to download all models from an order
     */
    public function handle_order_models_download() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'td_download_model_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'td-link')));
        }
        
        // Get order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (empty($order_id)) {
            wp_send_json_error(array('message' => __('Order ID is required.', 'td-link')));
        }
        
        // Get 3D items from the order
        $items_with_3d = td_get_3d_items_for_order($order_id);
        
        if (empty($items_with_3d)) {
            wp_send_json_error(array('message' => __('No 3D models found in this order.', 'td-link')));
        }
        
        // Create a ZIP file with all models
        $zip_result = $this->create_models_zip($items_with_3d, 'order');
        
        if ($zip_result['success']) {
            wp_send_json_success(array(
                'download_url' => $zip_result['download_url'],
                'message' => __('Models ready for download.', 'td-link')
            ));
        } else {
            wp_send_json_error(array('message' => $zip_result['message']));
        }
    }
    
    /**
     * Handle AJAX request to download all models from a cart
     */
    public function handle_cart_models_download() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'td_download_model_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'td-link')));
        }
        
        // Get cart key
        $cart_key = isset($_POST['cart_key']) ? sanitize_text_field($_POST['cart_key']) : '';
        
        if (empty($cart_key)) {
            wp_send_json_error(array('message' => __('Cart key is required.', 'td-link')));
        }
        
        // Get 3D items from the cart
        $items_with_3d = td_get_3d_items_for_cart($cart_key);
        
        if (empty($items_with_3d)) {
            wp_send_json_error(array('message' => __('No 3D models found in this cart.', 'td-link')));
        }
        
        // Create a ZIP file with all models
        $zip_result = $this->create_models_zip($items_with_3d, 'cart');
        
        if ($zip_result['success']) {
            wp_send_json_success(array(
                'download_url' => $zip_result['download_url'],
                'message' => __('Models ready for download.', 'td-link')
            ));
        } else {
            wp_send_json_error(array('message' => $zip_result['message']));
        }
    }
    
    /**
     * Generate a GLTF file for a specific scene with parameters
     * 
     * @param string $scene_name The scene name
     * @param array $parameters The parameters to apply
     * @param string $dist_path The path to the PolygonJS dist folder
     * @return array Result with success status, message, and download URL if successful
     */
    private function generate_gltf($scene_name, $parameters, $dist_path) {
        // Get site path
        $site_path = get_home_path();
        $polygonjs_path = trailingslashit($site_path) . trailingslashit($dist_path);
        
        // Check if the scene exists
        $scene_dir = $polygonjs_path . 'polygonjs/scenes/' . $scene_name;
        if (!file_exists($scene_dir)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Scene "%s" not found at path: %s', 'td-link'), $scene_name, $scene_dir)
            );
        }
        
        // Create temporary directory for the model
        $upload_dir = wp_upload_dir();
        $p3d_dir = trailingslashit($upload_dir['basedir']) . 'p3d';
        $temp_dir = trailingslashit($p3d_dir) . 'temp';
        
        // Create the parent and temp directories if they don't exist
        if (!file_exists($p3d_dir)) {
            wp_mkdir_p($p3d_dir);
        }
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Create an .htaccess file to allow direct access to these files
        $htaccess_file = trailingslashit($p3d_dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "<IfModule mod_rewrite.c>\n";
            $htaccess_content .= "RewriteEngine On\n";
            $htaccess_content .= "RewriteRule .* - [L]\n";
            $htaccess_content .= "</IfModule>\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Create a unique filename
        $unique_id = uniqid();
        $model_file = trailingslashit($temp_dir) . $scene_name . '_' . $unique_id . '.gltf';
        
        // Look for existing GLTF models in the scenes folder
        $default_model_found = false;
        $models_dir = $polygonjs_path . 'models';
        
        if (file_exists($models_dir)) {
            $gltf_files = glob($models_dir . '/*.{gltf,glb}', GLOB_BRACE);
            
            if (!empty($gltf_files)) {
                // Find a matching model file (e.g., SceneName.gltf)
                foreach ($gltf_files as $gltf_file) {
                    $file_basename = basename($gltf_file, pathinfo($gltf_file, PATHINFO_EXTENSION));
                    if (strtolower(trim($file_basename, '.')) === strtolower($scene_name)) {
                        // Copy this file to our temp directory
                        copy($gltf_file, $model_file);
                        $default_model_found = true;
                        
                        // Also copy any associated files (like .bin files)
                        $bin_file = $models_dir . '/' . $file_basename . 'bin';
                        if (file_exists($bin_file)) {
                            copy($bin_file, trailingslashit($temp_dir) . basename($bin_file));
                        }
                        
                        break;
                    }
                }
                
                // If no exact match found but we have some GLTF files, use the first one
                if (!$default_model_found && !empty($gltf_files)) {
                    $first_gltf = reset($gltf_files);
                    copy($first_gltf, $model_file);
                    $default_model_found = true;
                    
                    // Copy associated files
                    $file_basename = basename($first_gltf, pathinfo($first_gltf, PATHINFO_EXTENSION));
                    $bin_file = $models_dir . '/' . $file_basename . 'bin';
                    if (file_exists($bin_file)) {
                        copy($bin_file, trailingslashit($temp_dir) . basename($bin_file));
                    }
                }
            }
        }
        
        // If no default model found, create a simple placeholder GLTF file
        if (!$default_model_found) {
            $simple_gltf = $this->create_simple_gltf_file($model_file, $scene_name, $parameters);
            
            if (!$simple_gltf) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create GLTF file.', 'td-link')
                );
            }
        }
        
        // Create download URL
        $model_url = trailingslashit($upload_dir['baseurl']) . 'p3d/temp/' . basename($model_file);
        
        // Add a cron job to clean up the temp file after a period
        wp_schedule_single_event(time() + 3600, 'td_cleanup_temp_model', array($model_file));
        
        return array(
            'success' => true,
            'download_url' => $model_url,
            'message' => __('GLTF file successfully generated.', 'td-link')
        );
    }
    
    /**
     * Create a simple GLTF file as a placeholder if no default model is available
     * 
     * @param string $file_path The path to save the GLTF file
     * @param string $scene_name The scene name
     * @param array $parameters The parameters to include in metadata
     * @return bool Whether the file was created successfully
     */
    private function create_simple_gltf_file($file_path, $scene_name, $parameters) {
        // Create a very basic GLTF file structure
        $gltf_data = array(
            'asset' => array(
                'version' => '2.0',
                'generator' => 'TD Link Plugin'
            ),
            'scene' => 0,
            'scenes' => array(
                array(
                    'name' => $scene_name
                )
            ),
            'nodes' => array(
                array(
                    'name' => 'Root',
                    'children' => array(1)
                ),
                array(
                    'name' => 'Cube',
                    'mesh' => 0
                )
            ),
            'meshes' => array(
                array(
                    'name' => 'CubeMesh',
                    'primitives' => array(
                        array(
                            'mode' => 4,
                            'indices' => 0,
                            'attributes' => array(
                                'POSITION' => 1,
                                'NORMAL' => 2
                            )
                        )
                    )
                )
            ),
            'buffers' => array(
                array(
                    'byteLength' => 1,
                    'uri' => 'data:application/octet-stream;base64,AA=='
                )
            ),
            'bufferViews' => array(
                array(
                    'buffer' => 0,
                    'byteOffset' => 0,
                    'byteLength' => 1
                )
            ),
            'accessors' => array(
                array(
                    'bufferView' => 0,
                    'byteOffset' => 0,
                    'componentType' => 5123,
                    'count' => 1,
                    'type' => 'SCALAR'
                ),
                array(
                    'bufferView' => 0,
                    'byteOffset' => 0,
                    'componentType' => 5126,
                    'count' => 1,
                    'type' => 'VEC3'
                ),
                array(
                    'bufferView' => 0,
                    'byteOffset' => 0,
                    'componentType' => 5126,
                    'count' => 1,
                    'type' => 'VEC3'
                )
            ),
            'extras' => array(
                'td_parameters' => $parameters
            )
        );
        
        $json_data = json_encode($gltf_data, JSON_PRETTY_PRINT);
        return file_put_contents($file_path, $json_data) !== false;
    }
    
    /**
     * Create a ZIP file containing multiple GLTF models
     * 
     * @param array $items The items with 3D models
     * @param string $context The context (order or cart)
     * @return array Result with success status, message, and download URL if successful
     */
    private function create_models_zip($items, $context) {
        if (!class_exists('ZipArchive')) {
            return array(
                'success' => false,
                'message' => __('ZIP functionality not available on this server.', 'td-link')
            );
        }
        
        // Create temporary directory for the ZIP file
        $upload_dir = wp_upload_dir();
        $p3d_dir = trailingslashit($upload_dir['basedir']) . 'p3d';
        $temp_dir = trailingslashit($p3d_dir) . 'temp';
        
        // Create the parent and temp directories if they don't exist
        if (!file_exists($p3d_dir)) {
            wp_mkdir_p($p3d_dir);
        }
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Create a unique filename for the ZIP
        $unique_id = uniqid();
        $zip_file = trailingslashit($temp_dir) . 'models_' . $context . '_' . $unique_id . '.zip';
        
        // Create the ZIP file
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE) !== true) {
            return array(
                'success' => false,
                'message' => __('Failed to create ZIP file.', 'td-link')
            );
        }
        
        // Generate and add each model to the ZIP
        $success_count = 0;
        foreach ($items as $item) {
            $scene_name = $item['scene_name'];
            $parameters = $item['parameters'];
            $dist_path = $item['dist_path'];
            
            // Generate the GLTF file
            $result = $this->generate_gltf($scene_name, $parameters, $dist_path);
            
            if ($result['success']) {
                // Add the GLTF file to the ZIP
                $model_file = basename(parse_url($result['download_url'], PHP_URL_PATH));
                $model_path = trailingslashit($temp_dir) . $model_file;
                
                if (file_exists($model_path)) {
                    // Use product name and scene name for the file in the ZIP
                    $product_name = sanitize_file_name($item['product_name']);
                    $zip_model_name = $product_name . '_' . $scene_name . '.gltf';
                    
                    $zip->addFile($model_path, $zip_model_name);
                    $success_count++;
                    
                    // Check for associated files (like .bin files)
                    $bin_file = str_replace('.gltf', '.bin', $model_path);
                    if (file_exists($bin_file)) {
                        $zip_bin_name = str_replace('.gltf', '.bin', $zip_model_name);
                        $zip->addFile($bin_file, $zip_bin_name);
                    }
                }
            }
        }
        
        // Close the ZIP file
        $zip->close();
        
        if ($success_count === 0) {
            return array(
                'success' => false,
                'message' => __('Failed to generate any models for the ZIP file.', 'td-link')
            );
        }
        
        // Create download URL
        $zip_url = trailingslashit($upload_dir['baseurl']) . 'p3d/temp/' . basename($zip_file);
        
        // Add a cron job to clean up the temp file after a period
        wp_schedule_single_event(time() + 3600, 'td_cleanup_temp_model', array($zip_file));
        
        return array(
            'success' => true,
            'download_url' => $zip_url,
            'message' => sprintf(__('%d models successfully added to ZIP file.', 'td-link'), $success_count)
        );
    }
}

// Register cleanup function for temporary model files
if (!function_exists('td_cleanup_temp_model')) {
    /**
     * Clean up temporary model files
     * 
     * @param string $file_path The path to the temporary file
     */
    function td_cleanup_temp_model($file_path) {
        if (file_exists($file_path)) {
            @unlink($file_path);
            
            // Also remove associated files like .bin if they exist
            $bin_file = str_replace('.gltf', '.bin', $file_path);
            if (file_exists($bin_file)) {
                @unlink($bin_file);
            }
        }
    }
}

add_action('td_cleanup_temp_model', 'td_cleanup_temp_model');

/**
 * Create necessary directories upon plugin activation
 */
function td_create_upload_directories() {
    $upload_dir = wp_upload_dir();
    $p3d_dir = trailingslashit($upload_dir['basedir']) . 'p3d';
    $temp_dir = trailingslashit($p3d_dir) . 'temp';
    
    // Create directories if they don't exist
    if (!file_exists($p3d_dir)) {
        wp_mkdir_p($p3d_dir);
    }
    
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    // Create an index.php file to prevent directory listing
    $index_file = trailingslashit($p3d_dir) . 'index.php';
    if (!file_exists($index_file)) {
        file_put_contents($index_file, '<?php // Silence is golden');
    }
    
    // Create an .htaccess file to allow direct access to files
    $htaccess_file = trailingslashit($p3d_dir) . '.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "<IfModule mod_rewrite.c>\n";
        $htaccess_content .= "RewriteEngine On\n";
        $htaccess_content .= "RewriteRule .* - [L]\n";
        $htaccess_content .= "</IfModule>\n";
        file_put_contents($htaccess_file, $htaccess_content);
    }
}

// Create the directories immediately for existing installations
add_action('init', 'td_create_upload_directories');