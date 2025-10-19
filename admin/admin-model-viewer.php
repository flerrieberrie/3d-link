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

// Enqueue CSS and JS files
wp_enqueue_style('td-model-viewer-css', plugin_dir_url(__FILE__) . 'assets/css/admin-model-viewer.css', [], '1.0.0');
wp_enqueue_script('td-model-viewer-js', plugin_dir_url(__FILE__) . 'assets/js/admin-model-viewer.js', ['jquery'], '1.0.0', true);

// Prepare data for JavaScript
$js_data = [
    'parameters' => $parameters,
    'scene_name' => $scene_name,
    'model_id' => $model_data->id,
    'product_id' => $model_data->product_id,
    'unified_sync_key' => $unified_sync_key,
    'unified_frontend_state' => $unified_frontend_state,
    'synced_colors' => [],
    'synced_parameters' => [],
    'universal_mapping' => [],
    'color_options' => [],
    'raw_parameters' => [],
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('td_model_viewer_nonce'),
    'unified_sync_script' => '',
    'strings' => [
        'loading' => __('Loading 3D Model...', 'td-link'),
        'downloading' => __('Downloading...', 'td-link'),
        'download' => __('Download', 'td-link'),
        'download_failed' => __('Could not trigger download. Please check the model configuration.', 'td-link'),
        'scene_access_failed' => __('Could not access the 3D scene. Please wait for the model to load completely.', 'td-link'),
    ]
];

// Prepare color data in the format needed by JavaScript
foreach ($synced_colors as $color_key => $color_data) {
    if (isset($color_data['rgb']) && is_array($color_data['rgb'])) {
        $js_data['synced_colors'][$color_key] = $color_data['rgb'];
    }
}

// Get synced parameter mappings
if (class_exists('TD_Parameter_Sync')) {
    $js_data['synced_parameters'] = TD_Parameter_Sync::get_model_parameter_mappings($model_data->id);
} else {
    $js_data['synced_parameters'] = [];
}

// Get universal mapping and color options
if (class_exists('TD_Parameters_Manager')) {
    $parameters_manager = new TD_Parameters_Manager();
    $js_data['universal_mapping'] = $parameters_manager->get_universal_mapping($model_data->product_id);
    $js_data['raw_parameters'] = $parameters_manager->get_parameters($model_data->product_id);
} else {
    $js_data['universal_mapping'] = [];
    $js_data['raw_parameters'] = [];
}

if (class_exists('TD_Colors_Manager')) {
    $colors_manager = new TD_Colors_Manager();
    $global_colors = $colors_manager->get_global_colors();
    foreach ($global_colors as $color_id => $color) {
        $color_key = strtolower(str_replace(' ', '-', $color['name']));
        $js_data['color_options'][$color_key] = [
            'name' => $color['name'],
            'hex' => $color['hex'],
            'rgb' => $color['rgb'],
            'in_stock' => $color['in_stock'] ?? true
        ];
    }
}

// Generate unified sync script if available
if ($unified_sync_key && class_exists('TD_Unified_Parameter_Sync')) {
    $js_data['unified_sync_script'] = TD_Unified_Parameter_Sync::generate_backend_viewer_script($unified_sync_key);
}

// Localize script with data
wp_localize_script('td-model-viewer-js', 'tdModelViewerData', $js_data);

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