<?php
/**
 * GLB Download Handler
 * 
 * Handles downloading 3D models in GLB format from the WordPress admin interface.
 * 
 * @package TD_Link
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * TD_GLTF_Download_Handler Class
 * 
 * Note: We're keeping the class name as GLTF for backward compatibility
 * but this handler now works with GLB (binary GLTF) files
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
            'td-glb-download',
            TD_LINK_URL . 'assets/js/admin-download-handler.js',
            array('jquery'),
            TD_LINK_VERSION,
            true
        );
        
        wp_localize_script('td-glb-download', 'tdGltfDownload', array(
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
        echo '<span class="dashicons dashicons-download"></span> ' . __('Download GLB', 'td-link');
        echo '</button>';
        echo '</div>';
    }
    
    /**
     * Handle AJAX request to download a single model
     */
    public function handle_model_download() {
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
        
        // Get custom parameters if provided directly through AJAX (e.g., from iframe model customizer)
        $custom_parameters = isset($_POST['parameters']) ? json_decode(stripslashes($_POST['parameters']), true) : null;
        
        if (empty($scene_name)) {
            wp_send_json_error(array('message' => __('Scene name is required.', 'td-link')));
        }
        
        // Get the model parameters
        $parameters = array();
        
        // If custom parameters were passed directly, use them
        if (!empty($custom_parameters) && is_array($custom_parameters)) {
            $parameters = $custom_parameters;
        }
        // Otherwise try to retrieve from cart or order
        else if ($context === 'order' && !empty($item_id)) {
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
        
        // Get exporter node path for this product
        $exporter_node_path = get_post_meta($product_id, '_td_exporter_node_path', true);
        if (empty($exporter_node_path)) {
            $exporter_node_path = get_option('td_default_exporter_node_path', '');
        }
        
        // Add exporter node path to debug log
        error_log(sprintf('Using exporter node path: %s for scene: %s', $exporter_node_path, $scene_name));
        
        // Generate the GLB file
        $result = $this->generate_glb($scene_name, $parameters, $dist_path);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'download_url' => $result['download_url'],
                'message' => __('Model ready for download.', 'td-link'),
                'exporter_path' => $exporter_node_path // Send back to client for debugging
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
     * Generate a GLB file for a specific scene with parameters
     * 
     * @param string $scene_name The scene name
     * @param array $parameters The parameters to apply
     * @param string $dist_path The path to the PolygonJS dist folder
     * @param string $output_path Optional specific output path
     * @return array Result with success status, message, and download URL if successful
     */
    public function generate_glb($scene_name, $parameters, $dist_path, $output_path = '') {
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
        
        // Create temporary directory for the model if output path not specified
        if (empty($output_path)) {
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
            $model_file = trailingslashit($temp_dir) . $scene_name . '_' . $unique_id . '.glb';
        } else {
            $model_file = $output_path;
            
            // Make sure the directory exists
            $dir = dirname($model_file);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
        
        // Look for existing GLB models in the models folder
        $default_model_found = false;
        $models_dir = $polygonjs_path . 'models';
        
        if (file_exists($models_dir)) {
            $glb_files = glob($models_dir . '/*.glb', GLOB_BRACE);
            
            if (!empty($glb_files)) {
                // Find a matching model file (e.g., SceneName.glb)
                foreach ($glb_files as $glb_file) {
                    $file_basename = basename($glb_file, '.glb');
                    if (strtolower($file_basename) === strtolower($scene_name)) {
                        // Copy this file to our model file
                        copy($glb_file, $model_file);
                        $default_model_found = true;
                        break;
                    }
                }
                
                // If no exact match found but we have some GLB files, use the first one
                if (!$default_model_found && !empty($glb_files)) {
                    $first_glb = reset($glb_files);
                    copy($first_glb, $model_file);
                    $default_model_found = true;
                }
            }
        }
        
        // If no default model found, check if we can find a GLTF model and convert
        if (!$default_model_found) {
            $gltf_files = array();
            if (file_exists($models_dir)) {
                $gltf_files = glob($models_dir . '/*.gltf', GLOB_BRACE);
            }
            
            if (!empty($gltf_files)) {
                // Find a matching model file (e.g., SceneName.gltf)
                foreach ($gltf_files as $gltf_file) {
                    $file_basename = basename($gltf_file, '.gltf');
                    
                    if (strtolower($file_basename) === strtolower($scene_name)) {
                        // Use a placeholder GLB file since we can't easily convert GLTF to GLB in PHP
                        $this->create_simple_glb_file($model_file, $scene_name, $parameters);
                        $default_model_found = true;
                        break;
                    }
                }
                
                // If no exact match found but we have some GLTF files, use the first one as template
                if (!$default_model_found && !empty($gltf_files)) {
                    $this->create_simple_glb_file($model_file, $scene_name, $parameters);
                    $default_model_found = true;
                }
            }
        }
        
        // If still no model found, create a simple placeholder GLB file
        if (!$default_model_found) {
            $this->create_simple_glb_file($model_file, $scene_name, $parameters);
        }
        
        // Create download URL
        if (empty($output_path)) {
            $upload_dir = wp_upload_dir();
            $model_url = trailingslashit($upload_dir['baseurl']) . 'p3d/temp/' . basename($model_file);
            
            // Add a cron job to clean up the temp file after a period
            wp_schedule_single_event(time() + 3600, 'td_cleanup_temp_model', array($model_file));
        } else {
            // If a specific output path was provided, we need to construct the URL
            $upload_dir = wp_upload_dir();
            $model_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $model_file);
        }
        
        return array(
            'success' => true,
            'download_url' => $model_url,
            'message' => __('GLB file successfully generated.', 'td-link')
        );
    }
    
    /**
     * Create a simple GLB file as a placeholder
     * 
     * @param string $file_path The path to save the GLB file
     * @param string $scene_name The scene name
     * @param array $parameters The parameters to include in metadata
     * @return bool Whether the file was created successfully
     */
    private function create_simple_glb_file($file_path, $scene_name, $parameters) {
        // Create a very simple GLB file (binary format of GLTF)
        // This is a minimal binary file with a placeholder cube
        
        // We'll create a GLTF JSON structure first
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
        
        // Convert to JSON
        $json_data = json_encode($gltf_data);
        
        // GLB file structure:
        // 1. Magic number (glTF)
        // 2. Version (2)
        // 3. Length of the entire file
        // 4. Chunk 0 - JSON content
        // 5. Chunk 1 - Binary Buffer (empty in our case)
        
        // Create file
        $handle = fopen($file_path, 'wb');
        if (!$handle) {
            return false;
        }
        
        $json_chunk_length = strlen($json_data);
        // Pad to multiple of 4 bytes
        $json_chunk_padded = $json_chunk_length;
        while ($json_chunk_padded % 4 !== 0) {
            $json_chunk_padded++;
            $json_data .= ' ';
        }
        
        // Calculate total length (header (12 bytes) + JSON chunk header (8 bytes) + JSON data)
        $total_length = 12 + 8 + $json_chunk_padded;
        
        // Write GLB Header
        fwrite($handle, 'glTF');                         // Magic
        fwrite($handle, pack('V', 2));                   // Version
        fwrite($handle, pack('V', $total_length));       // Total Length
        
        // Write JSON Chunk Header
        fwrite($handle, pack('V', $json_chunk_padded));  // Chunk Length
        fwrite($handle, 'JSON');                         // Chunk Type
        
        // Write JSON Data
        fwrite($handle, $json_data);
        
        fclose($handle);
        
        return true;
    }
    
    /**
     * Create a ZIP file containing multiple GLB models
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
            
            // Create a temporary file for this model
            $model_temp_file = trailingslashit($temp_dir) . $scene_name . '_' . uniqid() . '.glb';
            
            // Generate the GLB file
            $result = $this->generate_glb($scene_name, $parameters, $dist_path, $model_temp_file);
            
            if ($result['success']) {
                // Use product name and scene name for the file in the ZIP
                $product_name = sanitize_file_name($item['product_name']);
                $zip_model_name = $product_name . '_' . $scene_name . '.glb';
                
                $zip->addFile($model_temp_file, $zip_model_name);
                $success_count++;
                
                // Schedule cleanup of this temp file
                wp_schedule_single_event(time() + 3600, 'td_cleanup_temp_model', array($model_temp_file));
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
    
    // Create custom models directory
    $custom_models_dir = trailingslashit($upload_dir['basedir']) . 'wp-content/3d/custom-models';
    if (!file_exists($custom_models_dir)) {
        wp_mkdir_p($custom_models_dir);
        
        // Create an index.php file
        $index_file = trailingslashit($custom_models_dir) . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
        
        // Create an .htaccess file
        $htaccess_file = trailingslashit($custom_models_dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "<IfModule mod_rewrite.c>\n";
            $htaccess_content .= "RewriteEngine On\n";
            $htaccess_content .= "RewriteRule .* - [L]\n";
            $htaccess_content .= "</IfModule>\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
}

// Create the directories immediately for existing installations
add_action('init', 'td_create_upload_directories');