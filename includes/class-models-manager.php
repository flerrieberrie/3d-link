<?php
/**
 * 3D Models Manager
 * 
 * Handles storage, retrieval, and management of 3D models
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TD_Models_Manager {
    /**
     * Table name for the models
     */
    private $table_name;

    /**
     * Models storage directory
     */
    private $models_dir;

    /**
     * Initialize the class
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . '3d_models';
        
        // Set up the models directory
        $upload_dir = wp_upload_dir();
        $this->models_dir = trailingslashit($upload_dir['basedir']) . 'wp-content/3d/custom-models';
        
        // Register activation hook for creating the table
        register_activation_hook(TD_LINK_FILE, array($this, 'create_table'));
        
        // Add actions for saving models when adding to cart
        add_action('woocommerce_add_to_cart', array($this, 'save_model_from_cart'), 10, 6);
        
        // Add cleanup for abandoned models
        add_action('td_cleanup_abandoned_models', array($this, 'cleanup_abandoned_models'));
        
        // Schedule cleanup task if not already scheduled
        if (!wp_next_scheduled('td_cleanup_abandoned_models')) {
            wp_schedule_event(time(), 'daily', 'td_cleanup_abandoned_models');
        }
        
        // Add order model download link in WooCommerce order admin
        add_action('woocommerce_admin_order_item_headers', array($this, 'add_order_item_download_header'));
        add_action('woocommerce_admin_order_item_values', array($this, 'add_order_item_download_button'), 10, 3);
        
        // Save model metadata when checkout is completed
        add_action('woocommerce_checkout_order_processed', array($this, 'update_models_order_id'), 10, 3);
    }

    /**
     * Create the custom database table on plugin activation
     */
    public function create_table() {
        global $wpdb;
        
        // Define database schema
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_path varchar(255) NOT NULL,
            file_url varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT 0 NOT NULL,
            product_id bigint(20) DEFAULT 0 NOT NULL,
            order_id bigint(20) DEFAULT 0,
            cart_item_key varchar(64) DEFAULT '',
            cart_session_key varchar(64) DEFAULT '',
            parameters longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY product_id (product_id),
            KEY order_id (order_id),
            KEY status (status),
            KEY cart_item_key (cart_item_key),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create models directory if it doesn't exist
        if (!file_exists($this->models_dir)) {
            wp_mkdir_p($this->models_dir);
            
            // Create an index.php file to prevent directory listing
            $index_file = trailingslashit($this->models_dir) . 'index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden');
            }
            
            // Create an .htaccess file to allow direct access to files
            $htaccess_file = trailingslashit($this->models_dir) . '.htaccess';
            if (!file_exists($htaccess_file)) {
                $htaccess_content = "<IfModule mod_rewrite.c>\n";
                $htaccess_content .= "RewriteEngine On\n";
                $htaccess_content .= "RewriteRule .* - [L]\n";
                $htaccess_content .= "</IfModule>\n";
                file_put_contents($htaccess_file, $htaccess_content);
            }
        }
    }

    /**
     * Save a model when it's added to the cart
     * 
     * @param string $cart_item_key Cart item key
     * @param int $product_id Product ID
     * @param int $quantity Quantity
     * @param int $variation_id Variation ID
     * @param array $variation Variation data
     * @param array $cart_item_data Cart item data
     */
    public function save_model_from_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        // Check if the cart item has TD parameters
        if (empty($cart_item_data['td_parameters'])) {
            return;
        }
        
        // Get the scene name and dist path from product
        $scene_name = get_post_meta($product_id, '_td_scene_name', true);
        $product = wc_get_product($product_id);
        
        if (!$scene_name && $product) {
            $scene_name = $product->get_slug();
        }
        
        $dist_path = get_post_meta($product_id, '_td_dist_path', true) ?: '3d/dist';
        
        // Create a unique filename for tracking (no actual file generation)
        $user_id = get_current_user_id();
        $unique_id = uniqid();
        $sanitized_scene = sanitize_file_name($scene_name);
        $filename = "{$sanitized_scene}_{$user_id}_{$unique_id}.glb";
        $file_path = trailingslashit($this->models_dir) . $filename;
        
        // Store parameters for later use
        $parameters = $cart_item_data['td_parameters'];
        
        // Get file URL (virtual path for tracking purposes)
        $upload_dir = wp_upload_dir();
        $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
        
        // Get WooCommerce session data
        $session_key = '';
        if (function_exists('WC') && isset(WC()->session)) {
            $session_key = WC()->session->get_customer_id();
        }
        
        // Set expiration date (2 days from now)
        $expires_at = date('Y-m-d H:i:s', strtotime('+2 days'));
        
        // Save to database
        global $wpdb;
        $wpdb->insert(
            $this->table_name,
            array(
                'file_path' => $file_path,
                'file_url' => $file_url,
                'user_id' => $user_id,
                'product_id' => $product_id,
                'cart_item_key' => $cart_item_key,
                'cart_session_key' => $session_key,
                'parameters' => serialize($parameters),
                'status' => 'in_cart',
                'expires_at' => $expires_at
            ),
            array('%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        $model_id = $wpdb->insert_id;
        
        // Add the model URL to the cart item metadata so we can retrieve it later
        WC()->cart->cart_contents[$cart_item_key]['td_model_file'] = $file_url;
        WC()->cart->cart_contents[$cart_item_key]['td_model_id'] = $model_id;
        WC()->cart->set_session();
    }
    
    /**
     * Update models when an order is processed
     * 
     * @param int $order_id Order ID
     * @param array $posted_data Posted data
     * @param WC_Order $order Order object
     */
    public function update_models_order_id($order_id, $posted_data, $order) {
        global $wpdb;
        
        // Get all items from the cart
        $cart = WC()->cart->get_cart();
        
        foreach ($cart as $cart_item_key => $cart_item) {
            // Skip if no model file URL
            if (empty($cart_item['td_model_file'])) {
                continue;
            }
            
            // Update the model in the database
            $wpdb->update(
                $this->table_name,
                array(
                    'order_id' => $order_id,
                    'status' => 'ordered',
                    'expires_at' => null // Remove expiration once ordered
                ),
                array('cart_item_key' => $cart_item_key),
                array('%d', '%s', null),
                array('%s')
            );
            
            // Also add the model file URL as order item meta
            foreach ($order->get_items() as $item_id => $item) {
                // Check if this order item matches our cart item
                $product_id = $item->get_product_id();
                if ($product_id == $cart_item['product_id']) {
                    // Store the model file URL with the order item
                    wc_add_order_item_meta($item_id, '_td_model_file', $cart_item['td_model_file']);
                    
                    // Store the model ID as well
                    if (!empty($cart_item['td_model_id'])) {
                        wc_add_order_item_meta($item_id, '_td_model_id', $cart_item['td_model_id']);
                    }
                }
            }
        }
    }
    
    /**
     * Clean up abandoned models (run via cron)
     */
    public function cleanup_abandoned_models() {
        global $wpdb;
        
        // Find models that have expired
        $expired_models = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE status = 'in_cart' 
                AND expires_at < %s",
                date('Y-m-d H:i:s')
            )
        );
        
        $deleted_count = 0;
        
        foreach ($expired_models as $model) {
            // Delete the file
            if (file_exists($model->file_path)) {
                @unlink($model->file_path);
                $deleted_count++;
            }
            
            // Update the status in the database
            $wpdb->update(
                $this->table_name,
                array('status' => 'deleted'),
                array('id' => $model->id),
                array('%s'),
                array('%d')
            );
        }
        
        if ($deleted_count > 0) {
            error_log("Cleaned up {$deleted_count} abandoned 3D models.");
        }
    }
    
    /**
     * Add a header for the model download column in order items table
     */
    public function add_order_item_download_header() {
        echo '<th class="td-model-download">' . __('3D Model', 'td-link') . '</th>';
    }
    
    /**
     * Add a view model button for the model in order items table
     * 
     * @param WC_Order_Item $item Order item
     * @param WC_Order $order Order object
     * @param int $item_id Order item ID
     */
    public function add_order_item_download_button($item, $order, $item_id) {
        global $wpdb;
        
        // Get the model ID from meta
        $model_id = wc_get_order_item_meta($item_id, '_td_model_id', true);
        
        echo '<td class="td-model-download">';
        if (!empty($model_id)) {
            // The real order ID
            $real_order_id = $order->get_id();
            
            // First, find ALL models associated with this order in our database
            $order_models = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE order_id = %d ORDER BY id ASC",
                $real_order_id
            ));
            
            // If no models found with this order_id, check if our model exists at all
            if (empty($order_models)) {
                $model_exists = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, order_id FROM {$this->table_name} WHERE id = %d",
                    $model_id
                ));
                
                if ($model_exists) {
                    // The model exists but has a different order_id - this might be an issue with the database
                    // Let's get all models in that order instead
                    $order_models = $wpdb->get_results($wpdb->prepare(
                        "SELECT id FROM {$this->table_name} WHERE order_id = %d ORDER BY id ASC",
                        $model_exists->order_id
                    ));
                    
                    // Use the model's actual order_id to ensure valid navigation
                    $viewer_url = admin_url('admin.php?page=td-model-viewer&model_id=' . $model_id . '&order_id=' . $model_exists->order_id);
                } else {
                    // Model not found at all - maybe just use the provided model ID
                    $viewer_url = admin_url('admin.php?page=td-model-viewer&model_id=' . $model_id . '&order_id=' . $real_order_id);
                }
            } else {
                // Models found for this order - use first model and the real order ID
                $first_model_id = $order_models[0]->id;
                $viewer_url = admin_url('admin.php?page=td-model-viewer&model_id=' . $first_model_id . '&order_id=' . $real_order_id);
            }
            
            // Determine button text based on model count
            $model_count = count($order_models);
            if ($model_count > 1) {
                $button_text = sprintf(__('View 3D Models (%d)', 'td-link'), $model_count);
            } else {
                $button_text = __('View 3D Model', 'td-link');
            }
            
            echo '<a href="' . esc_url($viewer_url) . '" class="button" target="_blank">';
            echo '<span class="dashicons dashicons-visibility"></span> ' . $button_text;
            echo '</a>';
        } else {
            echo '—';
        }
        echo '</td>';
    }
    
    /**
     * Get models from the database with pagination
     * 
     * @param array $args Query arguments
     * @return array Models and pagination info
     */
    public function get_models($args = array()) {
        global $wpdb;
        
        // Default arguments
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'user_id' => 0,
            'product_id' => 0,
            'order_id' => 0,
            'status' => '',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $where = "WHERE 1=1";
        $values = array();
        
        // Filter by user ID
        if ($args['user_id'] > 0) {
            $where .= " AND user_id = %d";
            $values[] = $args['user_id'];
        }
        
        // Filter by product ID
        if ($args['product_id'] > 0) {
            $where .= " AND product_id = %d";
            $values[] = $args['product_id'];
        }
        
        // Filter by order ID
        if ($args['order_id'] > 0) {
            $where .= " AND order_id = %d";
            $values[] = $args['order_id'];
        }
        
        // Filter by status
        if (!empty($args['status'])) {
            $where .= " AND status = %s";
            $values[] = $args['status'];
        }
        
        // Add search filter
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (file_path LIKE %s OR parameters LIKE %s)";
            $values[] = $search;
            $values[] = $search;
        }
        
        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Sanitize order and orderby
        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} $where";
        $total = $wpdb->get_var($wpdb->prepare($count_query, $values));
        
        // Get models with pagination
        $query = "SELECT * FROM {$this->table_name} $where ORDER BY $orderby LIMIT %d, %d";
        $final_values = array_merge($values, array($offset, $args['per_page']));
        $models = $wpdb->get_results($wpdb->prepare($query, $final_values));
        
        // Calculate pagination data
        $total_pages = ceil($total / $args['per_page']);
        
        return array(
            'models' => $models,
            'total' => $total,
            'total_pages' => $total_pages,
            'page' => $args['page'],
            'per_page' => $args['per_page']
        );
    }
    
    /**
     * Get model data by ID
     * 
     * @param int $model_id Model ID
     * @return object|null Model data or null if not found
     */
    public function get_model($model_id) {
        global $wpdb;
        
        // Get by model_id
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $model_id
        ));
    }
    
    /**
     * Delete a model
     * 
     * @param int $model_id Model ID
     * @return bool Whether the model was deleted
     */
    public function delete_model($model_id) {
        global $wpdb;
        
        // Get the model
        $model = $this->get_model($model_id);
        
        if (!$model) {
            return false;
        }
        
        // Delete the file
        if (file_exists($model->file_path)) {
            @unlink($model->file_path);
        }
        
        // Update the status in the database
        return $wpdb->update(
            $this->table_name,
            array('status' => 'deleted'),
            array('id' => $model->id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Get models statistics for dashboard widget
     * 
     * @return array Statistics about models
     */
    public function get_models_stats() {
        global $wpdb;
        
        // Get total models
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Get total models by status
        $by_status = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
            FROM {$this->table_name} 
            GROUP BY status"
        );
        
        // Get models created today
        $today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE created_at >= %s",
            date('Y-m-d 00:00:00')
        ));
        
        // Get top users
        $top_users = $wpdb->get_results(
            "SELECT user_id, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE user_id > 0
            GROUP BY user_id 
            ORDER BY count DESC 
            LIMIT 5"
        );
        
        // Get top products
        $top_products = $wpdb->get_results(
            "SELECT product_id, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE product_id > 0
            GROUP BY product_id 
            ORDER BY count DESC 
            LIMIT 5"
        );
        
        // Format status counts as associative array
        $status_counts = array();
        foreach ($by_status as $status) {
            $status_counts[$status->status] = (int) $status->count;
        }
        
        // Get storage usage
        $storage_usage = 0;
        $storage_limit = 1073741824; // 1 GB in bytes
        
        if (function_exists('exec')) {
            $output = array();
            exec('du -sb ' . escapeshellarg($this->models_dir), $output);
            
            if (!empty($output[0])) {
                $parts = explode("\t", $output[0]);
                $storage_usage = (int) $parts[0];
            }
        }
        
        return array(
            'total' => (int) $total,
            'by_status' => $status_counts,
            'today' => (int) $today,
            'top_users' => $top_users,
            'top_products' => $top_products,
            'storage_usage' => $storage_usage,
            'storage_limit' => $storage_limit,
            'storage_percent' => $storage_usage > 0 ? round(($storage_usage / $storage_limit) * 100, 2) : 0
        );
    }
    
    /**
     * Get the URL for the models directory
     * 
     * @return string URL for the models directory
     */
    public function get_models_dir_url() {
        $upload_dir = wp_upload_dir();
        return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $this->models_dir);
    }
}