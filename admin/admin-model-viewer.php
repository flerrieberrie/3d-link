<?php
/**
 * Admin page for viewing customer 3D model customizations in a standalone viewer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'td-link'));
}

// Ensure we have model data
if (empty($model_data)) {
    wp_die(__('Model data not available.', 'td-link'));
}

// Check if we have an order ID to potentially load related models
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$url_order_id = $order_id; // Store original from URL for debugging

// If no order_id provided in URL, use the model's order_id
if ($order_id <= 0) {
    $order_id = $model_data->order_id;
}

// Add debug information for admins
if (current_user_can('manage_options') && isset($_GET['debug'])) {
    global $wpdb;
    
    // Get all models for this order
    $order_models = $wpdb->get_results($wpdb->prepare(
        "SELECT id, product_id, status, user_id, created_at FROM {$wpdb->prefix}3d_models WHERE order_id = %d ORDER BY id ASC",
        $order_id
    ));
    
    // Get all models in general matching this model ID
    $model_matches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, order_id, product_id, status, user_id, created_at FROM {$wpdb->prefix}3d_models WHERE id = %d",
        $model_data->id
    ));
    
    echo '<div style="background:#f8f8f8; padding:10px; margin:10px 0; border:1px solid #ddd;">';
    echo '<h3>Debug Information</h3>';
    echo '<p>Model ID: ' . $model_data->id . '</p>';
    echo '<p>Model Order ID (from database): ' . $model_data->order_id . '</p>';
    echo '<p>URL Order ID: ' . $url_order_id . '</p>';
    echo '<p>Final Order ID used: ' . $order_id . '</p>';
    echo '<p>Product ID: ' . $model_data->product_id . '</p>';
    
    echo '<h4>Models in Order #' . $order_id . ':</h4>';
    echo '<ul>';
    foreach ($order_models as $model) {
        echo '<li>Model #' . $model->id . ' - Product #' . $model->product_id . ' - Created: ' . $model->created_at . '</li>';
    }
    echo '</ul>';
    
    if (count($model_matches) > 1) {
        echo '<h4>Duplicate Models with ID #' . $model_data->id . ':</h4>';
        echo '<ul>';
        foreach ($model_matches as $model) {
            echo '<li>Model #' . $model->id . ' - Order #' . $model->order_id . ' - Product #' . $model->product_id . ' - Created: ' . $model->created_at . '</li>';
        }
        echo '</ul>';
    }
    
    echo '</div>';
}

$related_models = [];

// Get current model ID
$current_model_id = $model_data->id;

// If we have an order ID, get all models for this order
if ($order_id) {
    // Get the models manager if not already available
    if (!isset($models_manager)) {
        $models_manager = new TD_Models_Manager();
    }
    
    // Load all models from this order
    $order_models_data = $models_manager->get_models([
        'order_id' => $order_id,
        'per_page' => 50, // Get a reasonable number of models
        'status' => 'ordered'
    ]);
    
    $related_models = $order_models_data['models'];
    
    // Sort models by ID (assuming this is creation order)
    usort($related_models, function($a, $b) {
        return $a->id - $b->id;
    });
    
    // Find index of current model in the list
    $current_model_index = 0;
    foreach ($related_models as $index => $model) {
        if ($model->id === $current_model_id) {
            $current_model_index = $index;
            break;
        }
    }
}

// Get product information
$product = wc_get_product($model_data->product_id);
$product_name = $product ? $product->get_name() : __('Unknown Product', 'td-link');

// Get order information if available
$order = null;
$order_display = __('N/A', 'td-link');
if ($model_data->order_id) {
    $order = wc_get_order($model_data->order_id);
    if ($order) {
        $order_display = sprintf('#%s - %s %s', 
            $order->get_order_number(),
            $order->get_billing_first_name(),
            $order->get_billing_last_name()
        );
    }
}

// Get user information
$user_name = __('Guest', 'td-link');
if ($model_data->user_id) {
    $user = get_user_by('id', $model_data->user_id);
    if ($user) {
        $user_name = $user->display_name;
    }
}

// Get parameters
$parameters = [];
if ($model_data->parameters) {
    $parameters = @unserialize($model_data->parameters);
}

// Get scene and dist path from product
$scene_name = get_post_meta($model_data->product_id, '_td_scene_name', true);
if (!$scene_name && $product) {
    $scene_name = $product->get_slug();
}

$dist_path = get_post_meta($model_data->product_id, '_td_dist_path', true) ?: '3d/dist';

// Get stored color values for this product
$synced_colors = [];
if (class_exists('TD_Color_Sync') && $model_data->product_id) {
    $synced_colors = TD_Color_Sync::get_product_colors($model_data->product_id);
}

// Get unified sync key from order metadata for exact frontend state
$unified_sync_key = null;
$unified_frontend_state = null;
if ($model_data->order_id && class_exists('TD_Unified_Parameter_Sync')) {
    // Try to get sync key from order item metadata
    $order_items = wc_get_order($model_data->order_id)->get_items();
    foreach ($order_items as $item) {
        if ($item->get_product_id() == $model_data->product_id) {
            $sync_key = $item->get_meta('_td_sync_key');
            if ($sync_key) {
                $unified_sync_key = $sync_key;
                $unified_frontend_state = TD_Unified_Parameter_Sync::get_frontend_state($sync_key);
                break;
            }
        }
    }
}

// Create iframe URL
$iframe_url = home_url($dist_path . '/?scene=' . $scene_name);

// Add admin preview params
$iframe_url = add_query_arg([
    'admin_preview' => '1',
    // Removed the script parameter to ensure our injected script is used instead
], $iframe_url);

// Get site path for displaying download destination
$upload_dir = wp_upload_dir();
$default_download_path = trailingslashit($upload_dir['basedir']) . 'p3d/downloads';

// Get or create download settings
$download_settings = get_option('td_download_settings');
if (!$download_settings) {
    $download_settings = [
        'download_path' => $default_download_path,
        'default_format' => 'glb'
    ];
    update_option('td_download_settings', $download_settings);
}

// Get previous and next model in order if applicable
$prev_model = null;
$next_model = null;
if (!empty($related_models) && count($related_models) > 1) {
    if ($current_model_index > 0) {
        $prev_model = $related_models[$current_model_index - 1];
    }
    
    if ($current_model_index < count($related_models) - 1) {
        $next_model = $related_models[$current_model_index + 1];
    }
}

?>

<div class="wrap td-model-viewer-page">
    <h1><?php _e('3D Model Viewer', 'td-link'); ?></h1>
    
    <?php if ($order): ?>
    <div class="td-order-header">
        <div class="td-order-info">
            <div class="td-order-info-row">
                <h2>
                    <span class="dashicons dashicons-cart"></span>
                    <?php printf(__('Order %s', 'td-link'), $order->get_order_number()); ?>
                </h2>
                <h2 class="td-product-title">
                    <?php echo esc_html($product_name); ?>
                </h2>
            </div>
            <div class="td-customer-info">
                <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?> - 
                <?php echo date_i18n(get_option('date_format'), strtotime($order->get_date_created())); ?>
                <span class="td-models-count">
                    <?php printf(_n('%d model', '%d models', count($related_models), 'td-link'), count($related_models)); ?>
                </span>
            </div>
        </div>
        
        <div class="td-navigation-actions">
            <?php if (wp_get_referer() && strpos(wp_get_referer(), 'post.php?post=') !== false): ?>
            <a href="<?php echo esc_url(wp_get_referer()); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt"></span> <?php _e('Back to Order', 'td-link'); ?>
            </a>
            <?php else: ?>
            <a href="<?php echo admin_url('admin.php?page=td-models'); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt"></span> <?php _e('Back to Models List', 'td-link'); ?>
            </a>
            <?php endif; ?>
            
            <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" class="button">
                <span class="dashicons dashicons-visibility"></span> <?php _e('View Order', 'td-link'); ?>
            </a>
            
            <?php if ($order_id > 0): ?>
            <a href="<?php echo admin_url('admin.php?page=td-models&order_id=' . $order_id); ?>" class="button">
                <span class="dashicons dashicons-visibility"></span> <?php _e('View All Models', 'td-link'); ?>
            </a>
            <?php endif; ?>
            
            <button id="td-download-current-view" class="button button-primary">
                <span class="dashicons dashicons-download"></span> <?php _e('Download', 'td-link'); ?>
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="td-model-viewer-header td-simplified-header">
        <div class="td-model-info">
            <h2><?php echo esc_html($product_name); ?></h2>
        </div>
        
        <div class="td-model-actions">
            <a href="<?php echo admin_url('admin.php?page=td-models'); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt"></span> <?php _e('Back to Models', 'td-link'); ?>
            </a>
            
            <button id="td-download-current-view" class="button button-primary">
                <span class="dashicons dashicons-download"></span> <?php _e('Download', 'td-link'); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (count($related_models) > 1 || $order_id): ?>
    <div class="td-models-card-grid">
        <?php 
        $card_counter = 0;
        foreach ($related_models as $related_model): 
            $card_counter++;
            $is_current = ($related_model->id === $current_model_id);
            $card_class = $is_current ? 'td-model-card td-current-model' : 'td-model-card';
            
            // Extract primary customization data
            $main_params = [];
            $model_parameters = @unserialize($related_model->parameters);
            if (is_array($model_parameters)) {
                // Get only a few key parameters (max 3) for the card
                $param_counter = 0;
                foreach ($model_parameters as $param_id => $param) {
                    if ($param_counter >= 3) break;
                    
                    if (isset($param['control_type']) && isset($param['display_name']) && isset($param['value'])) {
                        $main_params[] = $param;
                        $param_counter++;
                    }
                }
            }
            
            // Create a brief model label
            $model_number = $card_counter;
        ?>
            <div class="<?php echo $card_class; ?>">
                <div class="td-model-card-header">
                    <div class="td-model-number"><?php echo sprintf(__('Model %d', 'td-link'), $model_number); ?></div>
                    
                    <div class="td-model-id">ID: <?php echo $related_model->id; ?></div>
                    
                    <div class="td-model-date">
                        <?php echo date_i18n(get_option('date_format'), strtotime($related_model->created_at)); ?>
                    </div>
                </div>
                
                <?php if (!empty($scene_name)): ?>
                <div class="td-model-scene">
                    <?php _e('Scene:', 'td-link'); ?> <?php echo esc_html($scene_name); ?>
                </div>
                <?php endif; ?>
                
                <div class="td-model-card-params">
                    <?php if (!empty($main_params)): ?>
                        <ul>
                        <?php foreach ($main_params as $param): 
                            $param_value = $param['value'];
                            
                            // Format color values with swatch
                            if ($param['control_type'] === 'color') {
                                // Generate a color based on the name
                                $color_hex = '#' . substr(md5($param_value), 0, 6);
                                
                                // Try to find this color in TD_Color_Sync
                                if (class_exists('TD_Color_Sync') && $related_model->product_id) {
                                    $color_key = strtolower(str_replace(' ', '-', $param_value));
                                    $rgb_values = TD_Color_Sync::get_color_values($related_model->product_id, $color_key);
                                    
                                    if ($rgb_values) {
                                        // Convert RGB 0-1 values to hex
                                        $r = min(255, max(0, round($rgb_values[0] * 255)));
                                        $g = min(255, max(0, round($rgb_values[1] * 255)));
                                        $b = min(255, max(0, round($rgb_values[2] * 255)));
                                        $color_hex = sprintf('#%02x%02x%02x', $r, $g, $b);
                                    }
                                }
                                
                                $swatch = '<span class="td-mini-color-swatch" style="background-color:' . esc_attr($color_hex) . '"></span>';
                                $param_value = $swatch . ' ' . $param_value;
                            }
                        ?>
                            <li>
                                <span class="td-param-name"><?php echo esc_html($param['display_name']); ?>:</span>
                                <span class="td-param-value"><?php echo $param_value; ?></span>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="td-no-params"><?php _e('No parameters available', 'td-link'); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="td-model-card-actions">
                    <?php if (!$is_current): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=td-model-viewer&model_id=' . $related_model->id . '&order_id=' . $order_id)); ?>" class="button button-primary">
                            <span class="dashicons dashicons-visibility"></span> <?php _e('View Model', 'td-link'); ?>
                        </a>
                    <?php else: ?>
                        <span class="button button-primary button-disabled">
                            <span class="dashicons dashicons-yes"></span> <?php _e('Current Model', 'td-link'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    
    <div class="td-model-viewer-content">
        <div class="td-model-viewer-container">
            <div class="td-iframe-container">
                <iframe id="td-model-iframe" src="<?php echo esc_url($iframe_url); ?>" frameborder="0" allowfullscreen allow="clipboard-write"></iframe>
                <div id="td-iframe-loader" class="td-iframe-loader">
                    <div class="td-loader-spinner"></div>
                    <div class="td-loader-text"><?php _e('Loading 3D Model...', 'td-link'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="td-model-parameters">
            <h3><?php _e('Model Parameters', 'td-link'); ?></h3>
            
            <?php if (empty($parameters) || !is_array($parameters)) : ?>
                <div class="notice notice-warning">
                    <p><?php _e('No parameters found or invalid format', 'td-link'); ?></p>
                </div>
            <?php else : ?>
                <table class="td-parameters-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Parameter', 'td-link'); ?></th>
                            <th><?php _e('Value', 'td-link'); ?></th>
                            <th><?php _e('Type', 'td-link'); ?></th>
                            <th><?php _e('Node ID', 'td-link'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parameters as $param_id => $param) : 
                            // Format the value for display
                            $display_value = isset($param['value']) ? $param['value'] : '';
                            $control_type = isset($param['control_type']) ? $param['control_type'] : '';
                            $node_id = isset($param['node_id']) ? $param['node_id'] : $param_id;
                            
                            // Format color values with improved swatch
                            if ($control_type === 'color' && $display_value) {
                                // Try to get color information
                                $color_hex = '';
                                $color_name = $display_value;
                                
                                // Method 1: Check if the value itself is already a hex color
                                if (preg_match('/#([a-f0-9]{3}){1,2}\b/i', $display_value)) {
                                    $color_hex = $display_value;
                                }
                                // Method 2: Try to get color values from Color Sync if available
                                else if (class_exists('TD_Color_Sync') && $model_data->product_id) {
                                    // Try to get the color key from the display value
                                    $color_key = strtolower(str_replace(' ', '-', $display_value));
                                    $rgb_values = TD_Color_Sync::get_color_values($model_data->product_id, $color_key);
                                    
                                    if ($rgb_values) {
                                        // Convert RGB 0-1 values to hex
                                        $r = min(255, max(0, round($rgb_values[0] * 255)));
                                        $g = min(255, max(0, round($rgb_values[1] * 255)));
                                        $b = min(255, max(0, round($rgb_values[2] * 255)));
                                        $color_hex = sprintf('#%02x%02x%02x', $r, $g, $b);
                                    }
                                }
                                
                                // Method 3: Try to find this color by name in the global colors as fallback
                                if (empty($color_hex) && class_exists('TD_Colors_Manager')) {
                                    $colors_manager = new TD_Colors_Manager();
                                    $global_colors = $colors_manager->get_global_colors();
                                    
                                    foreach ($global_colors as $color_id => $color) {
                                        if (strtolower($color['name']) === strtolower($display_value)) {
                                            $color_hex = $color['hex'];
                                            break;
                                        }
                                    }
                                }
                                
                                // Fallback: Generate a repeatable color from the name
                                if (empty($color_hex)) {
                                    // Create a color based on the color name hash
                                    $hash = substr(md5($display_value), 0, 6);
                                    $color_hex = '#' . $hash;
                                }
                                
                                // Generate color preview
                                $swatch_style = 'display: inline-block; width: 24px; height: 24px; vertical-align: middle; margin-right: 8px; border: 1px solid #ddd; box-shadow: inset 0 0 0 1px rgba(0,0,0,.1); background-color: ' . esc_attr($color_hex) . ';';
                                $color_preview = '<span class="td-color-preview" style="' . $swatch_style . '"></span>';
                                $display_value = $color_preview . $display_value;
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html($param['display_name'] ?? $param_id); ?></td>
                                <td><?php echo $display_value; ?></td>
                                <td><?php echo esc_html($control_type); ?></td>
                                <td><?php echo esc_html($node_id); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Model Viewer CSS */
.td-model-viewer-page {
    margin: 20px;
}

/* Page heading */
.td-model-viewer-page h1 {
    margin-bottom: 20px;
}

/* Order header */
.td-order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 3px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.td-order-info h2 {
    margin: 0 0 10px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #2271b1;
}

.td-order-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 5px;
}

.td-product-title {
    display: flex;
    align-items: center;
    gap: 15px;
    color: #333 !important;
    margin: 0 !important;
}

.td-product-title .button {
    margin-left: auto;
}

.td-customer-info {
    color: #555;
    margin-bottom: 5px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
}

.td-models-count {
    display: inline-block;
    background-color: #2271b1;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: bold;
    letter-spacing: 0.5px;
}

.td-scene-info {
    color: #666;
    font-style: italic;
}

.td-model-scene {
    color: #666;
    font-style: italic;
    font-size: 13px;
    padding: 0 10px;
    margin-top: -5px;
    margin-bottom: 5px;
    border-top: 1px dashed #eee;
    padding-top: 8px;
}

.td-navigation-actions {
    display: flex;
    gap: 10px;
}

/* Models card grid */
.td-models-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.td-model-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.td-model-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
}

.td-current-model {
    border: 2px solid #2271b1;
    background-color: #f0f7ff;
}

.td-model-card-header {
    background: #f5f5f5;
    padding: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
}

.td-model-number {
    font-weight: bold;
    color: #2271b1;
}

.td-model-id {
    color: #666;
    font-size: 12px;
}

.td-model-date {
    color: #666;
    font-size: 12px;
    font-style: italic;
}

.td-model-card-params {
    padding: 10px;
}

.td-model-card-params ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.td-model-card-params li {
    margin-bottom: 5px;
    padding-bottom: 5px;
    border-bottom: 1px dashed #eee;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    font-size: 13px;
}

.td-model-card-params li:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.td-param-name {
    font-weight: bold;
    color: #666;
    flex: 1;
}

.td-param-value {
    text-align: right;
}

.td-mini-color-swatch {
    display: inline-block;
    width: 16px;
    height: 16px;
    vertical-align: middle;
    margin-right: 5px;
    border-radius: 3px;
    border: 1px solid rgba(0,0,0,0.1);
}

.td-no-params {
    color: #999;
    font-style: italic;
    text-align: center;
    padding: 10px 0;
}

.td-model-card-actions {
    padding: 10px;
    background: #f9f9f9;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    gap: 5px;
}


/* Main viewer elements */
.td-model-viewer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* More compact header when we have order cards */
.td-models-card-grid + .td-model-viewer-header {
    padding: 10px 15px;
    background: #f8f9fa;
    box-shadow: none;
    border-top: none;
    border-radius: 0 0 3px 3px;
    margin-top: -10px;
}

.td-model-info h2 {
    margin-top: 0;
    margin-bottom: 10px;
}

.td-model-meta {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
}

.td-meta-item {
    margin-bottom: 5px;
}

.td-model-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.td-model-viewer-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.td-model-viewer-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.td-viewer-controls {
    display: flex;
    justify-content: center;
    padding: 10px;
    gap: 5px;
    flex-wrap: wrap;
    background: #f0f0f0;
    border-bottom: 1px solid #ddd;
}

.td-iframe-container {
    position: relative;
    padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
    height: 0;
    overflow: hidden;
}

.td-iframe-container iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    min-height: 500px;
}

.td-iframe-loader {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.8);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 10;
}

.td-loader-spinner {
    border: 5px solid #f3f3f3;
    border-top: 5px solid #3498db;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: td-spin 2s linear infinite;
    margin-bottom: 15px;
}

.td-loader-text {
    font-size: 16px;
    font-weight: bold;
}

.td-model-parameters {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    max-height: 700px;
    overflow-y: auto;
}

.td-parameters-table {
    width: 100%;
    border-collapse: collapse;
}

.td-parameters-table th {
    background: #f0f0f0;
    padding: 8px;
    text-align: left;
}

.td-parameters-table td {
    padding: 8px;
    border-bottom: 1px solid #f0f0f0;
}

@keyframes td-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .td-model-viewer-content {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 782px) {
    .td-models-card-grid {
        grid-template-columns: 1fr;
    }
    
    .td-order-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .td-navigation-actions {
        width: 100%;
    }
    
    .td-model-viewer-header {
        flex-direction: column;
    }
    
    .td-model-actions {
        margin-top: 10px;
    }
}
</style>

<script>
// Initialize synced colors from PHP
window.tdSyncedColors = <?php
    // Prepare color data in the format needed by JavaScript
    $js_color_data = [];
    foreach ($synced_colors as $color_key => $color_data) {
        if (isset($color_data['rgb']) && is_array($color_data['rgb'])) {
            $js_color_data[$color_key] = $color_data['rgb'];
        }
    }
    echo json_encode($js_color_data);
?>;

// Initialize synced parameter mappings (actual PolygonJS paths from frontend)
window.tdSyncedParameters = <?php
    // Get the actual PolygonJS node paths and parameter names that were used on the frontend
    $synced_parameters = TD_Parameter_Sync::get_model_parameter_mappings($model_data->id);
    echo json_encode($synced_parameters);
?>;

// Synced parameter system - uses actual PolygonJS paths stored when orders are placed

jQuery(document).ready(function($) {
    // Variables to store the state
    let iframeLoaded = false;
    let polygonJsReady = false;
    let pendingParameters = <?php echo json_encode($parameters); ?>;
    let retryCount = 0;
    let maxRetries = 30; // Increased number of retries for more reliability
    
    // Set a maximum time to wait for the loading screen
    const maxLoadingTime = 15000; // 15 seconds
    
    console.log('Model viewer initialized with parameters:', pendingParameters);
    
    // Set a timeout to hide the loader in case the ready event never comes
    setTimeout(() => {
        if ($('#td-iframe-loader').is(':visible')) {
            console.log('Forcing loader to hide after timeout');
            $('#td-iframe-loader').fadeOut();
        }
    }, maxLoadingTime);
    
    // Hide loader when iframe is loaded
    $('#td-model-iframe').on('load', function() {
        iframeLoaded = true;
        console.log('Iframe loaded, ready to interact with PolygonJS');
        
        // Inject our script directly into the iframe
        injectParameterHandler();
        
        // Send parameters immediately
        setTimeout(applyParameters, 1000);
        
        // And apply again after a delay to ensure they're sent after the scene is fully ready
        setTimeout(applyParameters, 3000);
        
        // We'll keep the loader visible until the scene is actually ready
    });
    
    /**
     * Inject parameter handler script directly into the iframe
     */
    function injectParameterHandler() {
        try {
            const iframe = document.getElementById('td-model-iframe');
            if (!iframe || !iframe.contentWindow || !iframe.contentDocument) {
                console.error('Cannot access iframe');
                return;
            }
            
            // Create script element
            const script = iframe.contentDocument.createElement('script');
            script.type = 'text/javascript';
            script.innerHTML = `
                // TD Model Viewer Parameter Handler - Directly Injected
                (function() {
                    console.log('[TD Model Viewer] Injected parameter handler ready');
                    
                    // Debug mode for logging
                    const DEBUG = true;
                    
                    // Store parameters for application
                    let pendingParameters = null;
                    
                    // Synced parameter resolution with smart fallback discovery
                    
                    /**
                     * Smart node discovery - finds the correct node path by searching the scene
                     */
                    function discoverCorrectNodePath(scene, approximatePath, paramName) {
                        if (!scene || !scene.nodesController || !scene.nodesController.nodes) {
                            return null;
                        }
                        
                        const nodeMap = scene.nodesController.nodes;
                        const targetSegments = approximatePath.replace(/^\//, '').split('/');
                        
                        // Strategy 1: Find nodes that contain the main path segments
                        const candidates = [];
                        for (const [nodePath, nodeObj] of nodeMap) {
                            const pathSegments = nodePath.replace(/^\//, '').split('/');
                            
                            // Check if this path contains key segments from our target
                            let matches = 0;
                            for (const targetSeg of targetSegments) {
                                for (const pathSeg of pathSegments) {
                                    if (pathSeg.toLowerCase().includes(targetSeg.toLowerCase()) || 
                                        targetSeg.toLowerCase().includes(pathSeg.toLowerCase())) {
                                        matches++;
                                        break;
                                    }
                                }
                            }
                            
                            // If we have good matches and the node has the parameter we need
                            if (matches >= targetSegments.length - 1) {
                                try {
                                    const node = scene.node(nodePath);
                                    if (node && node.p && (node.p[paramName] || 
                                        node.p[paramName.toLowerCase()] || 
                                        node.p[paramName.toUpperCase()])) {
                                        candidates.push({
                                            path: nodePath,
                                            matches: matches,
                                            hasParameter: true
                                        });
                                    }
                                } catch (e) {
                                    // Node not accessible, skip
                                }
                            }
                        }
                        
                        // Sort by best matches
                        candidates.sort((a, b) => b.matches - a.matches);
                        
                        if (candidates.length > 0) {
                            log('Smart discovery found candidate: ' + candidates[0].path + ' for ' + approximatePath);
                            return candidates[0].path;
                        }
                        
                        // Strategy 2: If no good matches, try to find nodes with the parameter
                        for (const [nodePath, nodeObj] of nodeMap) {
                            try {
                                const node = scene.node(nodePath);
                                if (node && node.p && (node.p[paramName] || 
                                    node.p[paramName.toLowerCase()] || 
                                    node.p[paramName.toUpperCase()])) {
                                    
                                    // Check if this node path makes sense for our target
                                    const pathLower = nodePath.toLowerCase();
                                    const approxLower = approximatePath.toLowerCase();
                                    
                                    if ((pathLower.includes('ctrl') && approxLower.includes('ctrl')) ||
                                        (pathLower.includes('mat') && approxLower.includes('mat')) ||
                                        (pathLower.includes('control') && approxLower.includes('ctrl')) ||
                                        (pathLower.includes('material') && approxLower.includes('mat'))) {
                                        log('Smart discovery found parameter match: ' + nodePath + ' for ' + approximatePath);
                                        return nodePath;
                                    }
                                }
                            } catch (e) {
                                // Node not accessible, skip
                            }
                        }
                        
                        return null;
                    }
                    
                    /**
                     * Resolve parameter node path and name from synced data (actual PolygonJS paths)
                     */
                    function resolveParameterFromSyncedData(originalNodeId, originalParamName) {
                        // Check if we have synced parameter data from the parent window
                        log('Checking for synced parameters...');
                        log('window.parent exists: ' + !!window.parent);
                        log('window.parent.tdSyncedParameters exists: ' + !!(window.parent && window.parent.tdSyncedParameters));
                        
                        if (!window.parent || !window.parent.tdSyncedParameters) {
                            log('No synced parameters available, using fallback');
                            return null;
                        }
                        
                        const syncedParams = window.parent.tdSyncedParameters;
                        
                        // Strategy 1: Direct parameter ID match
                        if (syncedParams[originalNodeId]) {
                            const mapping = syncedParams[originalNodeId];
                            log('Found direct synced mapping for: ' + originalNodeId);
                            return {
                                nodePath: mapping.actual_node_path,
                                paramName: mapping.actual_param_name
                            };
                        }
                        
                        // Strategy 2: Try building parameter ID from node path and param name
                        if (originalNodeId && originalParamName) {
                            // Convert path format to ID format
                            // e.g., "/sleutelhoes/ctrl" + "width" -> "sleutelhoes-ctrl-width"
                            let potentialId = originalNodeId;
                            if (potentialId.startsWith('/')) {
                                potentialId = potentialId.substring(1);
                            }
                            potentialId = potentialId.replace(/\//g, '-') + '-' + originalParamName;
                            
                            if (syncedParams[potentialId]) {
                                const mapping = syncedParams[potentialId];
                                log('Found synced mapping via constructed ID: ' + potentialId);
                                return {
                                    nodePath: mapping.actual_node_path,
                                    paramName: mapping.actual_param_name
                                };
                            }
                        }
                        
                        // Strategy 3: Search all synced parameters for similar ones
                        for (const [paramId, mapping] of Object.entries(syncedParams)) {
                            if (originalNodeId && originalParamName) {
                                const nodeIdClean = originalNodeId.replace(/^\//, '').replace(/\//g, '-');
                                if (paramId.includes(nodeIdClean) && paramId.includes(originalParamName)) {
                                    log('Found synced mapping via search: ' + paramId);
                                    return {
                                        nodePath: mapping.actual_node_path,
                                        paramName: mapping.actual_param_name
                                    };
                                }
                            }
                        }
                        
                        return null;
                    }
                    
                    // Listen for messages from parent
                    window.addEventListener('message', function(event) {
                        if (!event.data || typeof event.data !== 'object' || !event.data.type) return;
                        
                        log('Received message from parent: ' + event.data.type);
                        
                        // Handle standard parameter update message
                        if (event.data.type === 'updateParameter') {
                            // This is the standard format that PolygonJS expects
                            log('Received updateParameter: ' + 
                                event.data.nodeId + ' / ' + 
                                event.data.paramName + ' = ' + 
                                event.data.value);
                                
                            // Don't rely on PolygonJS handling these messages - apply them ourselves
                            try {
                                const scene = findPolygonScene();
                                if (scene) {
                                    // Universal parameter resolution using HTML snippet extraction
                                    let nodePath = event.data.nodeId;
                                    let paramName = event.data.paramName;
                                    let value = event.data.value;
                                    
                                    // Try to get correct node path and parameter from synced data (actual PolygonJS paths)
                                    const resolvedInfo = resolveParameterFromSyncedData(nodePath, paramName);
                                    if (resolvedInfo) {
                                        nodePath = resolvedInfo.nodePath;
                                        paramName = resolvedInfo.paramName;
                                        log('Resolved from synced data: ' + event.data.nodeId + ' -> ' + nodePath + '.' + paramName);
                                    } else {
                                        // Smart fallback: Try to discover the correct node path automatically
                                        let originalNodePath = nodePath;
                                        
                                        // Clean path of any trailing parameter segments first
                                        if (nodePath && typeof nodePath === 'string') {
                                            // Check for duplicate path/param pattern (most common issue)
                                            if (paramName && typeof paramName === 'string' && nodePath.endsWith('/' + paramName)) {
                                                // Remove the param from the path
                                                nodePath = nodePath.substring(0, nodePath.length - paramName.length - 1);
                                                log('Cleaned duplicate parameter from path: ' + originalNodePath + ' -> ' + nodePath);
                                            }
                                            
                                            // Check for color component paths
                                            if (nodePath.includes('/colorr') || nodePath.includes('/colorg') || nodePath.includes('/colorb')) {
                                                // Remove color component from path
                                                const components = nodePath.split('/');
                                                if (components.length > 0 && 
                                                    (components[components.length-1] === 'colorr' || 
                                                    components[components.length-1] === 'colorg' || 
                                                    components[components.length-1] === 'colorb')) {
                                                    // Remove the last component
                                                    components.pop();
                                                    nodePath = components.join('/');
                                                    log('Cleaned color component from path: ' + nodePath);
                                                }
                                            }
                                        }
                                        
                                        // Try smart discovery to find the correct node
                                        log('Attempting smart discovery for: ' + nodePath + ' param: ' + paramName);
                                        const discoveredPath = discoverCorrectNodePath(scene, nodePath, paramName);
                                        if (discoveredPath) {
                                            log('🎯 Smart discovery corrected path: ' + nodePath + ' -> ' + discoveredPath);
                                            nodePath = discoveredPath;
                                        } else {
                                            log('❌ Smart discovery failed for: ' + nodePath);
                                        }
                                    }
                                    
                                    // Universal scene mappings based on main.js structure
                                    if (nodePath) {
                                        // Define universal path mappings for all scenes
                                        const pathMappings = {
                                            // Doosje scene mappings
                                            '/doos/mat/colorbox': '/doos/MAT/colorBox',
                                            '/doos/mat/colorlid': '/doos/MAT/colorLid',
                                            '/doos/mat/colortekst': '/doos/MAT/colorTekst',
                                            '/doos/ctrl_doos/breedte': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/diepte': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/hoogte': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/dikte_wanden': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/dikte_bodem': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/deksel_dikte': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/tekst': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/tekst_schaal': '/doos/ctrl_doos',
                                            
                                            // Sleutelhoes scene mappings (from main.js lines 146-166)
                                            '/sleutelhoes/ctrl/width': '/sleutelhoes/CTRL',
                                            '/sleutelhoes/ctrl/height': '/sleutelhoes/CTRL',
                                            '/sleutelhoes/ctrl/length': '/sleutelhoes/CTRL',
                                            '/sleutelhoes/mat/meshstandard1': '/sleutelhoes/MAT/meshStandard1'
                                        };
                                        
                                        // Apply mapping if found
                                        if (pathMappings[nodePath]) {
                                            nodePath = pathMappings[nodePath];
                                            log('Applied path mapping: ' + event.data.nodeId + ' -> ' + nodePath);
                                        }
                                    }
                                    
                                    // Handle color values specially
                                    if (paramName === 'colorr' && typeof value === 'string') {
                                        // Try to find this color in tdPolygonjs.colorOptions
                                        if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
                                            const colorKey = typeof value === 'string' ? value.toLowerCase().replace(/\s+/g, '-') : String(value).toLowerCase().replace(/\s+/g, '-');
                                            if (window.tdPolygonjs.colorOptions[colorKey] && 
                                                window.tdPolygonjs.colorOptions[colorKey].rgb) {
                                                // Get RGB values
                                                const rgb = window.tdPolygonjs.colorOptions[colorKey].rgb;
                                                if (rgb && rgb.length === 3) {
                                                    // Update all RGB components
                                                    log('Found RGB values for ' + value + ': ' + rgb.join(', '));
                                                    value = rgb[0]; // Only use red component for colorr
                                                    
                                                    // Also apply green and blue if available
                                                    try {
                                                        if (node.p.colorg) node.p.colorg.set(rgb[1]);
                                                        if (node.p.colorb) node.p.colorb.set(rgb[2]);
                                                    } catch (e) {
                                                        log('Error setting color components: ' + e.message);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Try to find the node
                                    let node = null;
                                    try {
                                        node = scene.node(nodePath);
                                    } catch (e) {
                                        log('Error finding node at ' + nodePath + ': ' + e.message);
                                        
                                        // DEBUG: List all available nodes in the scene to help debug
                                        if (scene && scene.nodesController && scene.nodesController.nodes) {
                                            log('Available nodes in scene:');
                                            const nodeMap = scene.nodesController.nodes;
                                            const availableNodes = [];
                                            for (const [path, nodeObj] of nodeMap) {
                                                if (path.includes('ctrl') || path.includes('mat') || path.includes('control') || path.includes('material')) {
                                                    availableNodes.push(path);
                                                }
                                            }
                                            log('Control/Material nodes: ' + availableNodes.join(', '));
                                        }
                                    }
                                    
                                    // If node found, apply parameter
                                    if (node && node.p) {
                                        if (node.p[paramName]) {
                                            log('Manually applying parameter: ' + nodePath + '.' + paramName);
                                            node.p[paramName].set(value);
                                        } else {
                                            log('Parameter not found: ' + paramName + ' on node ' + nodePath);
                                            log('Available parameters: ' + Object.keys(node.p).join(', '));
                                            
                                            // Try case variations
                                            const variations = [
                                                typeof paramName === 'string' ? paramName.toLowerCase() : String(paramName).toLowerCase(),
                                                typeof paramName === 'string' ? paramName.toUpperCase() : String(paramName).toUpperCase(),
                                                typeof paramName === 'string' ? (paramName.charAt(0).toUpperCase() + paramName.slice(1)) : String(paramName).charAt(0).toUpperCase() + String(paramName).slice(1)
                                            ];
                                            
                                            for (const variant of variations) {
                                                if (node.p[variant]) {
                                                    log('Found parameter with case variation: ' + variant);
                                                    node.p[variant].set(value);
                                                    break;
                                                }
                                            }
                                        }
                                    } else {
                                        log('Node not found at path: ' + nodePath);
                                    }
                                }
                            } catch (e) {
                                log('Error manually applying parameter: ' + e.message);
                            }
                        }
                        
                        // Handle legacy parameter application request
                        else if (event.data.type === 'td_apply_parameters' && event.data.parameters) {
                            log('Received legacy parameters from parent window');
                            pendingParameters = event.data.parameters;
                            applyParameters();
                        }
                        
                        // Handle download request
                        else if (event.data.type === 'download_model') {
                            handleDownload(event.data.format, event.data.filename);
                        }
                    });
                    
                    // Setup a safer way to monitor native messages
                    // In case postMessage interception would cause issues
                    let modelIsReady = false;
                    
                    // Set interval to monitor global variables for scene readiness
                    setInterval(() => {
                        // If we haven't yet detected that model is ready
                        if (!modelIsReady) {
                            // Check if any global indicators suggest model is ready
                            if (window.modelReady || 
                                (window.polyScene && window.polyScene.isReady) ||
                                (window.polygonjs && window.polygonjs.isReady)) {
                                
                                modelIsReady = true;
                                log('Model readiness detected from global state');
                                
                                // Notify parent
                                window.parent.postMessage({ 
                                    type: 'modelReady',
                                    source: 'td_parameter_handler'
                                }, '*');
                                
                                // Apply parameters if needed
                                if (pendingParameters) {
                                    const scene = findPolygonScene();
                                    if (scene) {
                                        log('Auto-applying parameters after model ready detected');
                                        try {
                                            applyParameters();
                                        } catch(e) {
                                            log('Error during parameter application: ' + e.message);
                                        }
                                    }
                                }
                            }
                        }
                    }, 500);
                    
                    // Apply parameters to the scene using the native PolygonJS approach
                    function applyParameters() {
                        if (!pendingParameters) return;
                        
                        // Get scene with fallbacks
                        const scene = findPolygonScene();
                        if (!scene) {
                            log('Scene not found, will attempt again shortly');
                            setTimeout(applyParameters, 500);
                            return;
                        }
                        
                        log('Applying ' + Object.keys(pendingParameters).length + ' parameters to scene using native API');
                        
                        try {
                            // Process parameters
                            Object.keys(pendingParameters).forEach(paramId => {
                                const param = pendingParameters[paramId];
                                if (!param.value) return;
                                
                                // Get node path
                                const nodePath = extractNodePath(paramId, param);
                                if (!nodePath) return;
                                
                                log('Processing parameter: ' + paramId);
                                
                                try {
                                    // Try direct node method first
                                    applyParameterDirectly(scene, nodePath, param);
                                } catch (e) {
                                    log('Error with direct application, falling back to standard methods: ' + e.message);
                                    
                                    // Format value based on type
                                    const formattedValue = formatValueForType(param.value, param.control_type);
                                    
                                    // Try using the native updateParameter method
                                    // This is what PolygonJS expects from its regular UI
                                    if (window.updateParameter && typeof window.updateParameter === 'function') {
                                        log('Using window.updateParameter native method for: ' + nodePath);
                                        window.updateParameter(nodePath, formattedValue);
                                    }
                                    // Alternative: emit a standard updateParameter event
                                    else {
                                        log('Dispatching updateParameter event for: ' + nodePath);
                                        
                                        // Create a standard updateParameter message/event that PolygonJS will recognize
                                        const customEvent = new CustomEvent('updateParameter', {
                                            detail: {
                                                nodePath: nodePath,
                                                paramName: param.param_name || getParameterName(paramId),
                                                value: formattedValue
                                            }
                                        });
                                        
                                        // Dispatch event on both document and window
                                        document.dispatchEvent(customEvent);
                                        window.dispatchEvent(customEvent);
                                    }
                                }
                            });
                            
                            // Force scene update
                            if (typeof scene.processUpdateNodeConnections === 'function') {
                                scene.processUpdateNodeConnections();
                                log('Called scene.processUpdateNodeConnections()');
                            }
                            if (scene.root && typeof scene.root().compute === 'function') {
                                scene.root().compute();
                                log('Scene computed after parameter application');
                            }
                            
                            // Notify parent that parameters were applied
                            window.parent.postMessage({ type: 'parameters_applied' }, '*');
                        } catch (e) {
                            log('Error applying parameters: ' + e.message);
                        }
                        
                        // Clear parameters after application
                        pendingParameters = null;
                    }
                    
                    // Format value based on parameter type
                    function formatValueForType(value, type) {
                        if (type === 'color') {
                            // For colors, convert to RGB array in 0-1 range
                            if (typeof value === 'string') {
                                if (value.startsWith('#')) {
                                    return hexToRgb(value);
                                } else {
                                    // Try common colors
                                    const commonColors = {
                                        'red': [1, 0, 0],
                                        'green': [0, 1, 0],
                                        'blue': [0, 0, 1],
                                        'yellow': [1, 1, 0],
                                        'black': [0, 0, 0],
                                        'white': [1, 1, 1]
                                    };
                                    
                                    const lowerColor = typeof value === 'string' ? value.toLowerCase() : String(value).toLowerCase();
                                    if (commonColors[lowerColor]) {
                                        return commonColors[lowerColor];
                                    }
                                    
                                    // Generate from hash as last resort
                                    return generateColorFromString(value);
                                }
                            } else if (Array.isArray(value)) {
                                // Assume it's already RGB
                                return value.map(v => v > 1 ? v / 255 : v);
                            }
                        }
                        else if (type === 'number' || type === 'slider') {
                            // Make sure it's a number
                            return parseFloat(value);
                        }
                        else if (type === 'checkbox') {
                            // Convert to boolean
                            return value === true || 
                                value === 1 || 
                                value === '1' || 
                                value === 'yes' || 
                                value === 'true';
                        }
                        
                        // Default: return as is
                        return value;
                    }
                    
                    // Apply parameter directly to node
                    function applyParameterDirectly(scene, nodePath, param) {
                        // First try to get the node
                        const node = scene.node(nodePath);
                        if (!node || !node.p) {
                            throw new Error('Node not found or has no parameters: ' + nodePath);
                        }
                        
                        // Determine parameter name and apply based on type
                        const controlType = param.control_type || 'generic';
                        const paramName = param.param_name || getParameterName(param.id || nodePath);
                        const value = formatValueForType(param.value, controlType);
                        
                        // Apply specifically based on control type
                        if (controlType === 'color') {
                            // Handle RGB components
                            if (node.p.colorr && node.p.colorg && node.p.colorb) {
                                node.p.colorr.set(value[0]);
                                node.p.colorg.set(value[1]);
                                node.p.colorb.set(value[2]);
                                log('Set RGB components on ' + nodePath);
                                return true;
                            }
                            else if (node.p.color) {
                                node.p.color.set(value);
                                log('Set color vector on ' + nodePath);
                                return true;
                            }
                        }
                        else {
                            // For other types, try to set directly
                            if (node.p[paramName]) {
                                node.p[paramName].set(value);
                                log('Set ' + paramName + ' on ' + nodePath);
                                return true;
                            }
                            // Try 'value' as fallback
                            else if (node.p.value) {
                                node.p.value.set(value);
                                log('Set value parameter on ' + nodePath);
                                return true;
                            }
                        }
                        
                        throw new Error('No suitable parameter found on node: ' + nodePath);
                    }
                    
                    // Extract node path from parameter
                    function extractNodePath(paramId, param) {
                        // Use node_id from parameter if available
                        if (param.node_id) {
                            return '/' + param.node_id.replace(/-/g, '/');
                        }
                        
                        // Extract from parameter ID
                        if (paramId.includes('-')) {
                            const parts = paramId.split('-');
                            const lastPart = typeof parts[parts.length - 1] === 'string' ? parts[parts.length - 1].toLowerCase() : String(parts[parts.length - 1]).toLowerCase();
                            
                            // Check if last part is a common parameter name
                            const commonParams = ['value', 'color', 'colorr', 'colorg', 'colorb', 
                                                'r', 'g', 'b', 'scale', 'size', 'text'];
                            
                            if (commonParams.includes(lastPart)) {
                                parts.pop();
                            }
                            
                            return '/' + parts.join('/');
                        }
                        
                        return null;
                    }
                    
                    // Apply parameter to node based on type
                    function applyParameterToNode(node, paramId, param) {
                        const controlType = param.control_type || 'generic';
                        const value = param.value;
                        
                        log('Applying ' + controlType + ' parameter ' + paramId + ' with value: ' + value);
                        
                        switch (controlType) {
                            case 'color':
                                applyColorParameter(node, value);
                                break;
                            case 'slider':
                            case 'number':
                                applyNumericParameter(node, value, paramId);
                                break;
                            case 'text':
                                applyTextParameter(node, value, paramId);
                                break;
                            case 'checkbox':
                                applyBooleanParameter(node, value, paramId);
                                break;
                            default:
                                applyGenericParameter(node, value, paramId);
                        }
                    }
                    
                    // Apply color parameter
                    function applyColorParameter(node, value) {
                        // Extract RGB values
                        const rgb = extractColorValue(value);
                        log('Extracted RGB values: [' + rgb.join(', ') + ']');
                        
                        // Try different color parameters
                        if (node.p.colorr && node.p.colorg && node.p.colorb) {
                            node.p.colorr.set(rgb[0]);
                            node.p.colorg.set(rgb[1]);
                            node.p.colorb.set(rgb[2]);
                            log('Set RGB components');
                            return true;
                        } else if (node.p.color) {
                            node.p.color.set(rgb);
                            log('Set color vector');
                            return true;
                        } else if (node.p.r && node.p.g && node.p.b) {
                            node.p.r.set(rgb[0]);
                            node.p.g.set(rgb[1]);
                            node.p.b.set(rgb[2]);
                            log('Set r,g,b components');
                            return true;
                        } else if (node.p.diffuse) {
                            node.p.diffuse.set(rgb);
                            log('Set diffuse color');
                            return true;
                        } else {
                            // Try to find any color-related parameter
                            for (const paramName in node.p) {
                                if ((typeof paramName === 'string' && paramName.toLowerCase().includes('color')) ||
                                    (typeof paramName === 'string' && paramName.toLowerCase() === 'diffuse')) {
                                    try {
                                        node.p[paramName].set(rgb);
                                        log('Set color via ' + paramName);
                                        return true;
                                    } catch (e) {
                                        // Try next parameter
                                    }
                                }
                            }
                        }
                        return false;
                    }
                    
                    // Extract color value from string, hex, etc.
                    function extractColorValue(value) {
                        // If already an array, use it
                        if (Array.isArray(value)) {
                            return value.map(v => v > 1 ? v / 255 : v);
                        }
                        
                        // If hex color
                        if (typeof value === 'string' && value.startsWith('#')) {
                            return hexToRgb(value);
                        }
                        
                        // If color name, use common colors
                        if (typeof value === 'string') {
                            const commonColors = {
                                'red': [1, 0, 0],
                                'green': [0, 1, 0],
                                'blue': [0, 0, 1],
                                'yellow': [1, 1, 0],
                                'black': [0, 0, 0],
                                'white': [1, 1, 1],
                                'gray': [0.5, 0.5, 0.5],
                                'purple': [0.5, 0, 0.5],
                                'orange': [1, 0.65, 0],
                                'pink': [1, 0.75, 0.8],
                                'brown': [0.65, 0.16, 0.16],
                                'cyan': [0, 1, 1],
                                'magenta': [1, 0, 1]
                            };
                            
                            const lowerColor = typeof value === 'string' ? value.toLowerCase() : String(value).toLowerCase();
                            if (commonColors[lowerColor]) {
                                return commonColors[lowerColor];
                            }
                            
                            // Try to find in window.tdPolygonjs.colorOptions
                            if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
                                const colorKey = lowerColor.replace(/\\s+/g, '-');
                                if (window.tdPolygonjs.colorOptions[colorKey]) {
                                    const colorData = window.tdPolygonjs.colorOptions[colorKey];
                                    if (colorData.rgb) {
                                        return colorData.rgb;
                                    } else if (colorData.hex) {
                                        return hexToRgb(colorData.hex);
                                    }
                                }
                            }
                            
                            // Generate from hash as last resort
                            return generateColorFromString(value);
                        }
                        
                        // Default red
                        return [1, 0, 0];
                    }
                    
                    // Apply numeric parameter
                    function applyNumericParameter(node, value, paramId) {
                        const numValue = parseFloat(value);
                        if (isNaN(numValue)) return false;
                        
                        // Extract parameter name from paramId
                        const paramName = extractParameterName(paramId);
                        
                        // Try direct parameter
                        if (node.p[paramName] !== undefined) {
                            try {
                                node.p[paramName].set(numValue);
                                log('Set numeric parameter ' + paramName + ' to ' + numValue);
                                return true;
                            } catch (e) {
                                log('Error setting ' + paramName + ': ' + e.message);
                            }
                        }
                        
                        // Try common numeric parameters
                        const numericParams = ['value', 'val', 'size', 'scale', 'width', 'height', 'depth'];
                        for (const name of numericParams) {
                            if (node.p[name] !== undefined) {
                                try {
                                    node.p[name].set(numValue);
                                    log('Set ' + name + ' parameter to ' + numValue);
                                    return true;
                                } catch (e) {
                                    // Try next parameter
                                }
                            }
                        }
                        
                        // Try all parameters
                        for (const name in node.p) {
                            try {
                                node.p[name].set(numValue);
                                log('Set ' + name + ' parameter to ' + numValue);
                                return true;
                            } catch (e) {
                                // Try next parameter
                            }
                        }
                        
                        return false;
                    }
                    
                    // Apply text parameter
                    function applyTextParameter(node, value, paramId) {
                        // Extract parameter name from paramId
                        const paramName = extractParameterName(paramId);
                        
                        // Try direct parameter
                        if (node.p[paramName] !== undefined) {
                            try {
                                node.p[paramName].set(value);
                                log('Set text parameter ' + paramName);
                                return true;
                            } catch (e) {
                                log('Error setting ' + paramName + ': ' + e.message);
                            }
                        }
                        
                        // Try common text parameters
                        const textParams = ['text', 'string', 'value', 'input', 'label'];
                        for (const name of textParams) {
                            if (node.p[name] !== undefined) {
                                try {
                                    node.p[name].set(value);
                                    log('Set ' + name + ' parameter');
                                    return true;
                                } catch (e) {
                                    // Try next parameter
                                }
                            }
                        }
                        
                        // Try all parameters
                        for (const name in node.p) {
                            try {
                                node.p[name].set(value);
                                log('Set ' + name + ' parameter');
                                return true;
                            } catch (e) {
                                // Try next parameter
                            }
                        }
                        
                        return false;
                    }
                    
                    // Apply boolean parameter
                    function applyBooleanParameter(node, value, paramId) {
                        const boolValue = value === true || value === 1 || value === '1' || 
                                         value === 'yes' || value === 'true';
                        
                        // Extract parameter name from paramId
                        const paramName = extractParameterName(paramId);
                        
                        // Try direct parameter
                        if (node.p[paramName] !== undefined) {
                            try {
                                node.p[paramName].set(boolValue);
                                log('Set boolean parameter ' + paramName);
                                return true;
                            } catch (e) {
                                log('Error setting ' + paramName + ': ' + e.message);
                            }
                        }
                        
                        // Try common boolean parameters
                        const boolParams = ['value', 'enabled', 'visible', 'toggle', 'active'];
                        for (const name of boolParams) {
                            if (node.p[name] !== undefined) {
                                try {
                                    node.p[name].set(boolValue);
                                    log('Set ' + name + ' parameter');
                                    return true;
                                } catch (e) {
                                    // Try next parameter
                                }
                            }
                        }
                        
                        // Try all parameters
                        for (const name in node.p) {
                            try {
                                node.p[name].set(boolValue);
                                log('Set ' + name + ' parameter');
                                return true;
                            } catch (e) {
                                // Try next parameter
                            }
                        }
                        
                        return false;
                    }
                    
                    // Apply generic parameter
                    function applyGenericParameter(node, value, paramId) {
                        // Extract parameter name from paramId
                        const paramName = extractParameterName(paramId);
                        
                        // Try direct parameter
                        if (node.p[paramName] !== undefined) {
                            try {
                                node.p[paramName].set(value);
                                log('Set ' + paramName + ' parameter');
                                return true;
                            } catch (e) {
                                log('Error setting ' + paramName + ': ' + e.message);
                            }
                        }
                        
                        // Try 'value' parameter
                        if (node.p.value !== undefined) {
                            try {
                                node.p.value.set(value);
                                log('Set value parameter');
                                return true;
                            } catch (e) {
                                // Try other parameters
                            }
                        }
                        
                        // Try all parameters
                        for (const name in node.p) {
                            try {
                                node.p[name].set(value);
                                log('Set ' + name + ' parameter');
                                return true;
                            } catch (e) {
                                // Try next parameter
                            }
                        }
                        
                        return false;
                    }
                    
                    // Extract parameter name from paramId
                    function extractParameterName(paramId) {
                        if (!paramId) return 'value';
                        
                        // If contains dashes, get last part
                        if (paramId.includes('-')) {
                            const parts = paramId.split('-');
                            return parts[parts.length - 1];
                        }
                        
                        return 'value';
                    }
                    
                    // Convert hex to RGB
                    function hexToRgb(hex) {
                        hex = hex.replace(/^#/, '');
                        
                        let r, g, b;
                        if (hex.length === 3) {
                            r = parseInt(hex.charAt(0) + hex.charAt(0), 16) / 255;
                            g = parseInt(hex.charAt(1) + hex.charAt(1), 16) / 255;
                            b = parseInt(hex.charAt(2) + hex.charAt(2), 16) / 255;
                        } else {
                            r = parseInt(hex.substr(0, 2), 16) / 255;
                            g = parseInt(hex.substr(2, 2), 16) / 255;
                            b = parseInt(hex.substr(4, 2), 16) / 255;
                        }
                        
                        return [r, g, b];
                    }
                    
                    // Generate color from string
                    function generateColorFromString(str) {
                        let hash = 0;
                        for (let i = 0; i < str.length; i++) {
                            hash = str.charCodeAt(i) + ((hash << 5) - hash);
                        }
                        hash = Math.abs(hash);
                        
                        const r = ((hash & 0xFF0000) >> 16) / 255;
                        const g = ((hash & 0x00FF00) >> 8) / 255;
                        const b = (hash & 0x0000FF) / 255;
                        
                        return [r, g, b];
                    }
                    
                    // Find PolygonJS scene with multiple fallbacks
                    function findPolygonScene() {
                        // Method 1: Check window.polygonjs.scene
                        if (window.polygonjs && window.polygonjs.scene) {
                            log('Found scene at window.polygonjs.scene');
                            return window.polygonjs.scene;
                        }
                        
                        // Method 2: Check window.polyScene
                        if (window.polyScene) {
                            log('Found scene at window.polyScene');
                            return window.polyScene;
                        }
                        
                        // Method 3: Check for any properly named variable
                        for (const key in window) {
                            if (typeof key === 'string' && key.toLowerCase().includes('scene') && 
                                window[key] && typeof window[key] === 'object' &&
                                typeof window[key].node === 'function') {
                                log('Found scene in global window.' + key);
                                return window[key];
                            }
                        }
                        
                        // Method 4: If any of the above "PolygonJS" variables are objects with a root function
                        if (window.polygonjs && typeof window.polygonjs.root === 'function') {
                            log('Found scene at window.polygonjs');
                            return window.polygonjs;
                        }
                        if (window.polyScene && typeof window.polyScene.root === 'function') {
                            log('Found scene at window.polyScene');
                            return window.polyScene;
                        }
                        
                        // Method 5: Check if TDPolygonjs bridge is initialized
                        if (window.TDPolygonjs && typeof window.TDPolygonjs.getScene === 'function') {
                            const scene = window.TDPolygonjs.getScene();
                            if (scene) {
                                log('Found scene via TDPolygonjs.getScene()');
                                return scene;
                            }
                        }
                        
                        log('Scene not found');
                        return null;
                    }
                    
                    // Get all nodes in scene
                    function getAllSceneNodes(scene) {
                        const nodes = [];
                        
                        function collectNodes(node) {
                            if (!node) return;
                            
                            nodes.push(node);
                            
                            // Check for children
                            if (node.children) {
                                for (const child of node.children) {
                                    collectNodes(child);
                                }
                            }
                        }
                        
                        try {
                            if (scene.root) {
                                const root = scene.root();
                                collectNodes(root);
                            }
                        } catch (e) {
                            log('Error collecting nodes: ' + e.message);
                        }
                        
                        return nodes;
                    }
                    
                    // Handle model download
                    function handleDownload(format, filename) {
                        const scene = findPolygonScene();
                        if (!scene) {
                            log('Cannot download, scene not found');
                            window.parent.postMessage({
                                type: 'download_failed',
                                error: 'Scene not found'
                            }, '*');
                            return;
                        }
                        
                        try {
                            // Try to find exporter node
                            let exporterNode = null;
                            
                            // Common paths
                            const paths = [
                                '/doos/exporterGLTF1',
                                '/geo1/exporterGLTF1', 
                                '/case/exporterGLTF1',
                                '/root/exporterGLTF1',
                                '/geo2/exporterGLTF1',
                                '/box/exporterGLTF1'
                            ];
                            
                            // Try each path
                            for (const path of paths) {
                                try {
                                    const node = scene.node(path);
                                    if (node) {
                                        exporterNode = node;
                                        log('Found exporter node at: ' + path);
                                        break;
                                    }
                                } catch (e) {}
                            }
                            
                            // If not found, search for exporter in all nodes
                            if (!exporterNode) {
                                log('Searching for exporter node in all scene nodes');
                                const allNodes = getAllSceneNodes(scene);
                                for (const node of allNodes) {
                                    if (node.name && typeof node.name === 'string' && node.name.toLowerCase().includes('exporter')) {
                                        exporterNode = node;
                                        log('Found exporter node by name search: ' + node.path());
                                        break;
                                    }
                                }
                            }
                            
                            if (!exporterNode) {
                                log('No exporter node found');
                                window.parent.postMessage({
                                    type: 'download_failed',
                                    error: 'No exporter node found'
                                }, '*');
                                return;
                            }
                            
                            // Set filename if it exists
                            if (filename && exporterNode.p.fileName) {
                                exporterNode.p.fileName.set(filename);
                                log('Set export filename to: ' + filename);
                            }
                            
                            // Trigger download
                            if (exporterNode.p.trigger) {
                                exporterNode.p.trigger.pressButton();
                                log('Download triggered for ' + filename);
                                
                                window.parent.postMessage({
                                    type: 'download_started',
                                    format: format
                                }, '*');
                            } else if (exporterNode.p.download) {
                                exporterNode.p.download.pressButton();
                                log('Download triggered using download button for ' + filename);
                                
                                window.parent.postMessage({
                                    type: 'download_started',
                                    format: format
                                }, '*');
                            } else {
                                log('Exporter has no trigger or download parameter');
                                window.parent.postMessage({
                                    type: 'download_failed',
                                    error: 'Exporter has no trigger parameter'
                                }, '*');
                            }
                        } catch (e) {
                            log('Error triggering download: ' + e.message);
                            window.parent.postMessage({
                                type: 'download_failed',
                                error: e.message
                            }, '*');
                        }
                    }
                    
                    // Debug logging
                    function log(message) {
                        if (DEBUG) {
                            console.log('[TD Model Viewer]', message);
                        }
                    }
                    
                    // Notify parent we're ready
                    window.parent.postMessage({ 
                        type: 'td_injected_script_ready',
                        source: 'td_parameter_handler'
                    }, '*');
                    
                    // Listen for native scene ready events directly on window
                    // These might be triggered before our message handler is set up
                    document.addEventListener('sceneReady', function() {
                        log('Native document sceneReady event fired');
                        window.parent.postMessage({ type: 'sceneReady' }, '*');
                    });
                    
                    document.addEventListener('modelReady', function() {
                        log('Native document modelReady event fired');
                        window.parent.postMessage({ type: 'modelReady' }, '*');
                    });
                    
                    // Check for the scene and attempt to apply parameters automatically
                    function checkSceneAndApplyParameters() {
                        const scene = findPolygonScene();
                        if (scene) {
                            window.parent.postMessage({ 
                                type: 'td_polygonjs_ready', 
                                scene: true,
                                source: 'td_parameter_handler'
                            }, '*');
                            
                            // If we have pending parameters, apply them
                            if (pendingParameters) {
                                log('Automatically applying pending parameters');
                                applyParameters();
                            }
                        } else {
                            // Try again in 500ms
                            setTimeout(checkSceneAndApplyParameters, 500);
                        }
                    }
                    
                    // Repeatedly check for scene loading
                    let sceneCheckCount = 0;
                    const maxSceneChecks = 20;
                    const sceneCheckInterval = setInterval(() => {
                        sceneCheckCount++;
                        const scene = findPolygonScene();
                        
                        if (scene) {
                            clearInterval(sceneCheckInterval);
                            log('Scene found after ' + sceneCheckCount + ' attempts');
                            
                            window.parent.postMessage({
                                type: 'td_polygonjs_ready',
                                scene: true,
                                source: 'td_parameter_handler'
                            }, '*');
                            
                            if (pendingParameters) {
                                log('Applying pending parameters');
                                applyParameters();
                            }
                        } else if (sceneCheckCount >= maxSceneChecks) {
                            clearInterval(sceneCheckInterval);
                            log('Failed to find scene after ' + maxSceneChecks + ' attempts');
                            
                            // Notify parent even when we can't find the scene, so the loader disappears
                            window.parent.postMessage({
                                type: 'td_polygonjs_ready',
                                scene: false,
                                error: 'Scene not found',
                                source: 'td_parameter_handler'
                            }, '*');
                        } else {
                            log('Scene check attempt ' + sceneCheckCount + '/' + maxSceneChecks);
                        }
                    }, 500);
                    
                    // Also check for global load events
                    window.addEventListener('load', () => {
                        log('Window load event fired');
                        // Wait a moment after load to allow scene initialization
                        setTimeout(() => {
                            const scene = findPolygonScene();
                            if (scene) {
                                log('Scene found after window load');
                                window.parent.postMessage({
                                    type: 'td_polygonjs_ready',
                                    scene: true,
                                    source: 'td_parameter_handler'
                                }, '*');
                            }
                        }, 1000);
                    });
                })();
            `;
            
            // Add to the document
            iframe.contentDocument.head.appendChild(script);
            console.log('Parameter handler script injected successfully');
            
            // Inject unified sync script if we have frontend state
            <?php if ($unified_sync_key && $unified_frontend_state): ?>
            try {
                const unifiedScript = iframe.contentDocument.createElement('script');
                unifiedScript.type = 'text/javascript';
                unifiedScript.innerHTML = `<?php echo TD_Unified_Parameter_Sync::generate_backend_viewer_script($unified_sync_key); ?>`;
                iframe.contentDocument.head.appendChild(unifiedScript);
                console.log('✅ Unified sync script injected with frontend state');
            } catch (unifiedError) {
                console.error('❌ Error injecting unified sync script:', unifiedError);
            }
            <?php else: ?>
            console.log('ℹ️ No unified sync state available for this model');
            <?php endif; ?>
            
        } catch (e) {
            console.error('Error injecting script:', e);
        }
    }
    
    // Listen for messages from the iframe
    window.addEventListener('message', function(event) {
        // Verify the message structure
        if (!event.data || typeof event.data !== 'object') {
            return;
        }
        
        console.log('Received message from iframe:', event.data.type);
        
        // Check message source
        const isFromInjectedScript = event.data.source === 'td_parameter_handler';
        
        // Handle script ready message
        if (event.data.type === 'td_injected_script_ready') {
            console.log('Injected parameter handler is ready');
        }
        
        // Handle PolygonJS ready message
        if (event.data.type === 'td_polygonjs_ready') {
            polygonJsReady = true;
            console.log('\u2705 Received ready message from PolygonJS');
            
            // Hide loader when PolygonJS is actually ready
            $('#td-iframe-loader').fadeOut();
            
            // Apply parameters if we haven't already
            if (pendingParameters) {
                applyParameters();
            }
        }
        
        // Also listen for the native PolygonJS 'sceneReady' and 'modelReady' messages
        if (event.data.type === 'sceneReady' || event.data.type === 'modelReady') {
            console.log('\u2705 Received ' + event.data.type + ' message from PolygonJS');
            
            // Hide loader when model is ready
            $('#td-iframe-loader').fadeOut();
            
            // Only apply parameters once
            if (event.data.type === 'modelReady' && pendingParameters) {
                console.log('Applying parameters after model ready');
                
                // Force iframeLoaded to true since we know the model is ready
                iframeLoaded = true;
                
                // Apply parameters with a short delay to ensure model is fully ready
                setTimeout(applyParameters, 500);
            }
        }
        
        // Handle parameter application confirmation
        if (event.data.type === 'parameters_applied') {
            console.log('\u2705 Parameters applied successfully');
        }
        
        // Handle download events
        if (event.data.type === 'download_started') {
            console.log('\ud83d\udc4d Download started:', event.data.format);
        }
        else if (event.data.type === 'download_failed') {
            console.error('\u274c Download failed:', event.data.error);
            alert('Download failed: ' + event.data.error);
            
            // Re-enable download button
            $('#td-download-current-view').prop('disabled', false)
                .html('<span class="dashicons dashicons-download"></span> <?php _e('Download Current View', 'td-link'); ?>');
        }
    });
    
    // Apply parameters with retry logic
    function applyParameters() {
        // Don't reapply if already done
        if (!pendingParameters) {
            return;
        }
        
        // Get the iframe element
        const iframeElement = document.getElementById('td-model-iframe');
        
        // Check if we can access the iframe
        if (!iframeElement || !iframeElement.contentWindow) {
            console.log('Cannot access iframe, delaying parameter application');
            
            // Retry with exponential backoff
            retryCount++;
            
            if (retryCount <= maxRetries) {
                setTimeout(applyParameters, Math.min(500 * Math.pow(1.2, retryCount), 5000));
            } else {
                console.error('Failed to apply parameters - cannot access iframe');
            }
            
            return;
        }
        
        // At this point we have access to the iframe, so we can try to apply parameters
        // even if the load event hasn't fired
        
        console.log('Applying parameters to PolygonJS scene:', Object.keys(pendingParameters).length, 'parameters');
        
        // Send parameters to the iframe using universal parameter system
        if (iframeElement && iframeElement.contentWindow) {
            try {
                console.log('Applying parameters using universal parameter system');
                
                // Get the universal mapping for this product
                <?php
                $product_id = $model_data->product_id;
                $parameters_manager = new TD_Parameters_Manager();
                $raw_parameters = $parameters_manager->get_parameters($product_id);
                $universal_mapping = $parameters_manager->get_universal_mapping($product_id);
                ?>
                const universalMapping = <?php echo json_encode($universal_mapping); ?>;
                
                // Debug logging for parameter analysis
                console.log('Raw parameters for product <?php echo $product_id; ?>:', <?php echo json_encode($raw_parameters); ?>);
                console.log('Generated universal mapping:', universalMapping);
                console.log('Universal mapping node_mappings keys:', Object.keys(universalMapping.node_mappings || {}));
                
                // Send the universal mapping data to the iframe first
                iframeElement.contentWindow.postMessage({
                    type: 'td_set_universal_mapping',
                    mapping: universalMapping
                }, '*');
                
                // Send color options to the iframe
                <?php
                $colors_manager = new TD_Colors_Manager();
                $global_colors = $colors_manager->get_global_colors();
                $color_options_for_js = [];
                foreach ($global_colors as $color_id => $color) {
                    $color_key = strtolower(str_replace(' ', '-', $color['name']));
                    $color_options_for_js[$color_key] = [
                        'name' => $color['name'],
                        'hex' => $color['hex'],
                        'rgb' => $color['rgb'],
                        'in_stock' => $color['in_stock'] ?? true
                    ];
                }
                ?>
                
                // Make color options available in the iframe
                iframeElement.contentWindow.postMessage({
                    type: 'td_set_color_options',
                    colorOptions: <?php echo json_encode($color_options_for_js); ?>
                }, '*');
                
                // Wait a bit to ensure color options are processed before sending parameters
                setTimeout(() => {
                    // Send the parameters using the universal system
                    iframeElement.contentWindow.postMessage({
                        type: 'td_apply_parameters',
                        parameters: pendingParameters,
                        universalMapping: universalMapping
                    }, '*');
                }, 100); // 100ms delay to ensure color options are received first
                
                console.log('Sent parameters and universal mapping to iframe:', {
                    parameters: Object.keys(pendingParameters).length,
                    mappings: Object.keys(universalMapping.node_mappings || {}).length,
                    colorMappings: Object.keys(universalMapping.color_mappings || {}).length
                });
                
                // Legacy fallback - send each parameter individually as before for backward compatibility (also with delay)
                setTimeout(() => {
                    let paramsSent = 0;
                
                // Process each parameter using universal HTML snippet extraction (keeping for backward compatibility)
                Object.keys(pendingParameters).forEach(paramId => {
                    const param = pendingParameters[paramId];
                    if (!param || !param.value) return;
                    
                    // First try to extract node info from synced parameter data (actual PolygonJS paths)
                    let nodePath = '';
                    let paramName = '';
                    
                    // Try to get correct path from synced parameter data
                    if (window.tdSyncedParameters && window.tdSyncedParameters[paramId]) {
                        const syncedMapping = window.tdSyncedParameters[paramId];
                        nodePath = syncedMapping.actual_node_path;
                        paramName = syncedMapping.actual_param_name;
                        console.log('✅ Universal: Using synced parameter mapping for', paramId, ':', nodePath, paramName);
                    }
                    
                    // Fallback: Extract node path (remove parameter name if present)
                    if (!nodePath) {
                        if (param.node_id) {
                            nodePath = '/' + param.node_id.replace(/-/g, '/');
                        } else if (paramId.includes('-')) {
                            const parts = paramId.split('-');
                            const lastPart = typeof parts[parts.length - 1] === 'string' ? parts[parts.length - 1].toLowerCase() : String(parts[parts.length - 1]).toLowerCase();
                            
                            // Common parameter names to remove from path
                            const commonParams = ['value', 'color', 'colorr', 'colorg', 'colorb', 
                                                'r', 'g', 'b', 'scale', 'size', 'text'];
                            
                            if (commonParams.includes(lastPart)) {
                                parts.pop();
                            }
                            
                            nodePath = '/' + parts.join('/');
                        }
                    }
                    
                    if (!nodePath) return;
                    
                    // Format the value based on type
                    let value = param.value;
                    
                    // For colors, handle RGB values
                    if (param.control_type === 'color') {
                        if (typeof value === 'string' && !value.startsWith('#')) {
                            // Try to get the color from global colors
                            const colorKey = typeof value === 'string' ? value.toLowerCase().replace(/\s+/g, '-') : String(value).toLowerCase().replace(/\s+/g, '-');
                            if (window.tdPolygonjs && window.tdPolygonjs.colorOptions && 
                                window.tdPolygonjs.colorOptions[colorKey]) {
                                value = window.tdPolygonjs.colorOptions[colorKey].rgb || 
                                       [1, 0, 0]; // Default to red if not found
                            } else {
                                // Send color name as is - bridge will handle conversion
                                // This works better with named colors like 'Geel', 'Blauw', etc.
                                console.log('Using color name directly:', value);
                            }
                        }
                    }
                    
                    // For numeric values, ensure they are actually numbers (but not for color parameters)
                    if ((param.control_type === 'number' || param.control_type === 'slider') && param.control_type !== 'color') {
                        value = parseFloat(value);
                    }
                    
                    // Extract parameter name (use already extracted paramName if available)
                    if (!paramName) {
                        if (param.param_name) {
                            paramName = param.param_name;
                        } else if (paramId.includes('-')) {
                            const parts = paramId.split('-');
                            paramName = parts[parts.length - 1];
                        } else {
                            paramName = 'value'; // Default parameter name
                        }
                    }
                    
                    // Fix for parameters duplicated in path and name
                    const pathParts = nodePath.split('/');
                    const lastSegment = pathParts[pathParts.length - 1];
                    if (lastSegment && typeof lastSegment === 'string' && typeof paramName === 'string' && lastSegment.toLowerCase() === paramName.toLowerCase()) {
                        console.log('Found duplicated parameter in path:', lastSegment);
                        // Remove the duplicated parameter from the path
                        pathParts.pop();
                        nodePath = pathParts.join('/');
                        console.log('Corrected path to:', nodePath);
                    }
                    
                    // Universal scene mappings - apply for all scenes
                    let mappedNodePath = nodePath;
                    if (nodePath) {
                        // Cleanup path representation first
                        if (nodePath.includes('/colorr') || nodePath.includes('/colorg') || nodePath.includes('/colorb')) {
                            // Remove color component from path
                            const components = nodePath.split('/');
                            if (components.length > 0 && 
                                (components[components.length-1] === 'colorr' || 
                                 components[components.length-1] === 'colorg' || 
                                 components[components.length-1] === 'colorb')) {
                                // Remove the last component
                                components.pop();
                                nodePath = components.join('/');
                                console.log('Removed color component from path:', nodePath);
                            }
                        }
                    
                        // Define universal path mappings for all scenes
                        const pathMappings = {
                            // Doosje scene mappings
                            '/doos/mat/colorbox': '/doos/MAT/colorBox',
                            '/doos/mat/colorlid': '/doos/MAT/colorLid',
                            '/doos/mat/colortekst': '/doos/MAT/colorTekst',
                            '/doos/ctrl_doos/breedte': '/doos/ctrl_doos',
                            '/doos/ctrl_doos/diepte': '/doos/ctrl_doos',
                            '/doos/ctrl_doos/hoogte': '/doos/ctrl_doos',
                            '/doos/ctrl_doos/dikte_wanden': '/doos/ctrl_doos',
                            '/doos/ctrl_doos/dikte_bodem': '/doos/ctrl_doos',
                            '/doos/ctrl_doos/deksel_dikte': '/doos/ctrl_doos',
                            '/doos/ctrl_doos/tekst': '/doos/ctrl_doos',
                            '/doos/ctrl_doos/tekst_schaal': '/doos/ctrl_doos',
                            
                            // Sleutelhoes scene mappings (from main.js lines 146-166)
                            '/sleutelhoes/ctrl/width': '/sleutelhoes/CTRL',
                            '/sleutelhoes/ctrl/height': '/sleutelhoes/CTRL',
                            '/sleutelhoes/ctrl/length': '/sleutelhoes/CTRL',
                            '/sleutelhoes/mat/meshstandard1': '/sleutelhoes/MAT/meshStandard1',
                            
                            // Bloempot (flowerpot) scene mappings
                            '/geo1/mat/meshstandard1': '/geo1/MAT/meshStandard1',
                            '/geo1/cadcone1': '/geo1/CADCone1'
                        };
                        
                        // Apply mapping if found
                        if (pathMappings[nodePath]) {
                            mappedNodePath = pathMappings[nodePath];
                            console.log('Applied path mapping:', nodePath, '->', mappedNodePath);
                        }
                    }
                    
                    // Make sure we're sending the right value types
                    let processedValue = value;
                    
                    // Convert numeric strings to numbers (but not colors)
                    if ((param.control_type === 'number' || param.control_type === 'slider' || 
                        param.control_type === 'range') && param.control_type !== 'color') {
                        processedValue = parseFloat(value);
                    }
                    
                    // Convert boolean strings
                    if (param.control_type === 'checkbox') {
                        processedValue = value === true || value === 1 || value === '1' || 
                                     value === 'yes' || value === 'true';
                    }
                    
                    // Special handling for colors - check if we have stored RGB values for this color
                    if (param.control_type === 'color' && typeof value === 'string') {
                        // Check if we have PHP-provided RGB values for this color
                        const colorKey = typeof value === 'string' ? value.toLowerCase().replace(/\s+/g, '-') : String(value).toLowerCase().replace(/\s+/g, '-');
                        
                        // Check if RGB values were made available in the page
                        if (window.tdSyncedColors && window.tdSyncedColors[colorKey]) {
                            const rgbValues = window.tdSyncedColors[colorKey];
                            console.log('Using synced RGB values for color', value, rgbValues);
                            processedValue = rgbValues;
                        }
                    }
                    
                    console.log('Sending parameter to PolygonJS:', {
                        path: mappedNodePath,
                        param: paramName,
                        value: processedValue,
                        type: typeof processedValue
                    });
                    
                    // Send the parameter using native format with corrected path
                    iframeElement.contentWindow.postMessage({
                        type: 'updateParameter',
                        nodeId: mappedNodePath,
                        paramName: paramName,
                        value: processedValue
                    }, '*');
                    
                    paramsSent++;
                });
                
                    console.log(`Sent ${paramsSent} parameters to iframe using native format`);
                    
                    // Successfully sent - clear pending parameters to prevent duplicate sends
                    pendingParameters = null;
                }, 200); // Additional 200ms delay for legacy fallback to ensure color options are fully processed
            } catch (e) {
                console.error('Error sending parameters:', e);
                
                // Retry if possible
                retryCount++;
                if (retryCount <= maxRetries) {
                    console.log(`Will retry sending parameters (attempt ${retryCount}/${maxRetries})`);
                    setTimeout(applyParameters, 1000);
                }
            }
        } else {
            console.warn('\u26a0\ufe0f Could not access iframe contentWindow');
            
            // Retry if possible
            retryCount++;
            if (retryCount <= maxRetries) {
                setTimeout(applyParameters, 1000);
            }
        }
    }
    
    // Handle download button
    $('#td-download-current-view').on('click', function() {
        const iframeEl = document.getElementById('td-model-iframe');
        if (!iframeEl || !iframeEl.contentWindow) return;
        
        // Show a loading indicator
        $(this).prop('disabled', true).html('<span class="dashicons dashicons-update fa-spin"></span> <?php _e('Downloading...', 'td-link'); ?>');
        
        try {
            // First, try to access the PolygonJS scene directly and trigger the exporter
            // This is the most reliable approach based on the logs
            if (iframeEl.contentWindow.polyScene || 
                (iframeEl.contentWindow.polygonjs && iframeEl.contentWindow.polygonjs.scene)) {
                
                const scene = iframeEl.contentWindow.polyScene || iframeEl.contentWindow.polygonjs.scene;
                console.log('Found PolygonJS scene, looking for exporter node');
                
                // The log shows the exporter is at /sleutelhoes/exporterGLTF1
                // Let's try the specific path first, then common paths
                const exporterPaths = [
                    '/<?php echo esc_js($scene_name); ?>/exporterGLTF1',  // Scene-specific path
                    '/doos/exporterGLTF1',
                    '/geo1/exporterGLTF1',
                    '/geo2/exporterGLTF1',
                    '/case/exporterGLTF1',
                    '/root/exporterGLTF1',
                    '/box/exporterGLTF1'
                ];
                
                let exporterFound = false;
                for (const path of exporterPaths) {
                    try {
                        const exporter = scene.node(path);
                        if (exporter) {
                            console.log('Found exporter at:', path);
                            
                            // Set filename if possible
                            if (exporter.p && exporter.p.fileName) {
                                const filename = '<?php echo esc_js($scene_name); ?>_<?php echo esc_js($model_data->id); ?>';
                                exporter.p.fileName.set(filename);
                                console.log('Set filename to:', filename);
                            }
                            
                            // Trigger download - try both trigger and download parameters
                            if (exporter.p && exporter.p.trigger) {
                                console.log('Triggering download via trigger parameter');
                                exporter.p.trigger.pressButton();
                                exporterFound = true;
                            } else if (exporter.p && exporter.p.download) {
                                console.log('Triggering download via download parameter');
                                exporter.p.download.pressButton();
                                exporterFound = true;
                            } else {
                                console.log('Exporter node has no trigger or download parameter, checking all parameters');
                                // Try to find any parameter that might trigger the download
                                if (exporter.p) {
                                    for (const paramName in exporter.p) {
                                        if (paramName.toLowerCase().includes('trigger') || 
                                            paramName.toLowerCase().includes('download') || 
                                            paramName.toLowerCase().includes('export')) {
                                            console.log('Trying parameter:', paramName);
                                            if (typeof exporter.p[paramName].pressButton === 'function') {
                                                exporter.p[paramName].pressButton();
                                                exporterFound = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            if (exporterFound) break;
                        }
                    } catch (e) {
                        console.log('Error accessing exporter at', path, ':', e);
                    }
                }
                
                if (!exporterFound) {
                    // Fallback: Try to find and click the hidden download button in the iframe
                    console.log('Exporter node approach failed, trying to find hidden download button');
                    
                    const iframeDoc = iframeEl.contentDocument || iframeEl.contentWindow.document;
                    
                    // Look for any hidden button
                    const hiddenButtons = iframeDoc.querySelectorAll('button');
                    for (const button of hiddenButtons) {
                        const style = window.getComputedStyle(button);
                        if (style.display === 'none' || button.style.display === 'none') {
                            console.log('Found hidden button, clicking it');
                            button.click();
                            exporterFound = true;
                            break;
                        }
                    }
                    
                    if (!exporterFound) {
                        console.error('Could not find any way to trigger download');
                        alert('<?php _e('Could not trigger download. Please check the model configuration.', 'td-link'); ?>');
                    }
                }
                
                // Reset button
                setTimeout(() => {
                    $('#td-download-current-view').prop('disabled', false)
                        .html('<span class="dashicons dashicons-download"></span> <?php _e('Download Current View', 'td-link'); ?>');
                }, 2000);
            } else {
                console.error('Could not access PolygonJS scene in iframe');
                alert('<?php _e('Could not access the 3D scene. Please wait for the model to load completely.', 'td-link'); ?>');
                
                // Reset button
                $('#td-download-current-view').prop('disabled', false)
                    .html('<span class="dashicons dashicons-download"></span> <?php _e('Download Current View', 'td-link'); ?>');
            }
        } catch (error) {
            console.error('Error triggering download:', error);
            
            // Reset button
            $('#td-download-current-view').prop('disabled', false)
                .html('<span class="dashicons dashicons-download"></span> <?php _e('Download Current View', 'td-link'); ?>');
        }
    });
});
</script>