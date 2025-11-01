<?php
/**
 * Admin page for viewing customer 3D model customizations
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'td-link'));
}

// Instantiate the models manager
$models_manager = new TD_Models_Manager();

// Enqueue the CSS file
wp_enqueue_style(
    'td-admin-customer-models-style',
    TD_LINK_URL . 'assets/css/admin-customer-models.css',
    [],
    filemtime(TD_LINK_PATH . 'assets/css/admin-customer-models.css')
);

// Add the JavaScript for the model list page
wp_enqueue_script(
    'td-admin-customer-models',
    TD_LINK_URL . 'assets/js/admin-customer-models.js',
    ['jquery'],
    filemtime(TD_LINK_PATH . 'assets/js/admin-customer-models.js'),
    true
);

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'models';

// Get action if any
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

// Set up tabs
$tabs = array(
    'models' => __('All 3D Models', 'td-link'),
    'pending' => __('Pending Models', 'td-link'),
    'orders' => __('Models in Orders', 'td-link'),
    'stats' => __('Statistics', 'td-link')
);

// Process action
if ($action === 'view' && isset($_GET['model_id'])) {
    $current_tab = 'view_model';
} elseif ($action === 'delete' && isset($_GET['model_id']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_model_' . $_GET['model_id'])) {
        $model_id = intval($_GET['model_id']);
        $deleted = $models_manager->delete_model($model_id);
        
        if ($deleted) {
            $redirect_url = admin_url('admin.php?page=td-models&tab=' . $current_tab . '&deleted=1');
            wp_redirect($redirect_url);
            exit;
        }
    }
}

// Get filter parameters
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Set status based on tab
$status = '';
if ($current_tab === 'pending') {
    $status = 'in_cart';
} elseif ($current_tab === 'orders') {
    $status = 'ordered';
}

// Get models
$models_data = $models_manager->get_models([
    'per_page' => $per_page,
    'page' => $page,
    'user_id' => $user_id,
    'product_id' => $product_id,
    'order_id' => $order_id,
    'status' => $status,
    'search' => $search
]);

$models = $models_data['models'];
$total = $models_data['total'];
$total_pages = $models_data['total_pages'];

// Get statistics if on stats tab
$stats = array();
if ($current_tab === 'stats') {
    $stats = $models_manager->get_models_stats();
}

// Handle notification messages
$notices = array();
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $notices[] = array(
        'type' => 'success',
        'message' => __('Model deleted successfully.', 'td-link')
    );
}
?>

<div class="wrap">
    <h1><?php _e('3D Model Customer Browser', 'td-link'); ?></h1>
    
    <?php foreach ($notices as $notice) : ?>
        <div class="notice notice-<?php echo $notice['type']; ?> is-dismissible">
            <p><?php echo $notice['message']; ?></p>
        </div>
    <?php endforeach; ?>
    
    <div class="td-admin-tabs">
        <?php foreach ($tabs as $tab_id => $tab_name) : ?>
            <a href="?page=td-models&tab=<?php echo $tab_id; ?>" class="<?php echo $current_tab === $tab_id ? 'active' : ''; ?>">
                <?php echo $tab_name; ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="td-tabs-content">
        <?php if ($current_tab === 'models' || $current_tab === 'pending' || $current_tab === 'orders') : ?>
            <!-- Models Tab -->
            <div class="active">
                <h2>
                    <?php 
                    if ($current_tab === 'pending') {
                        _e('Pending 3D Models', 'td-link');
                    } elseif ($current_tab === 'orders') {
                        _e('3D Models in Orders', 'td-link');
                    } else {
                        _e('All 3D Models', 'td-link');
                    }
                    ?>
                </h2>
                
                <!-- Filter Form -->
                <div class="td-models-filter">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="td-models">
                        <input type="hidden" name="tab" value="<?php echo $current_tab; ?>">
                        
                        <div class="td-filter-row">
                            <!-- User filter -->
                            <div class="td-filter-field">
                                <label for="user_id"><?php _e('User:', 'td-link'); ?></label>
                                <select name="user_id" id="user_id">
                                    <option value="0"><?php _e('All Users', 'td-link'); ?></option>
                                    <?php
                                    $users = get_users(['role__in' => ['customer', 'administrator', 'editor']]);
                                    foreach ($users as $user) {
                                        $selected = $user_id == $user->ID ? 'selected' : '';
                                        echo '<option value="' . $user->ID . '" ' . $selected . '>' . esc_html($user->display_name) . ' (' . $user->user_email . ')</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Product filter -->
                            <div class="td-filter-field">
                                <label for="product_id"><?php _e('Product:', 'td-link'); ?></label>
                                <select name="product_id" id="product_id">
                                    <option value="0"><?php _e('All Products', 'td-link'); ?></option>
                                    <?php
                                    $products = wc_get_products([
                                        'limit' => -1,
                                        'status' => 'publish',
                                        'orderby' => 'title',
                                        'order' => 'ASC'
                                    ]);
                                    
                                    foreach ($products as $product) {
                                        $selected = $product_id == $product->get_id() ? 'selected' : '';
                                        echo '<option value="' . $product->get_id() . '" ' . $selected . '>' . esc_html($product->get_name()) . ' (ID: ' . $product->get_id() . ')</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Order filter -->
                            <?php if ($current_tab === 'orders' || $current_tab === 'models') : ?>
                                <div class="td-filter-field">
                                    <label for="order_id"><?php _e('Order:', 'td-link'); ?></label>
                                    <select name="order_id" id="order_id">
                                        <option value="0"><?php _e('All Orders', 'td-link'); ?></option>
                                        <?php
                                        $orders = wc_get_orders([
                                            'limit' => 100,
                                            'orderby' => 'date',
                                            'order' => 'DESC'
                                        ]);
                                        
                                        foreach ($orders as $order) {
                                            $selected = $order_id == $order->get_id() ? 'selected' : '';
                                            echo '<option value="' . $order->get_id() . '" ' . $selected . '>' . esc_html('#' . $order->get_order_number() . ' - ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Search -->
                            <div class="td-filter-field">
                                <label for="s"><?php _e('Search:', 'td-link'); ?></label>
                                <input type="text" name="s" id="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search models...', 'td-link'); ?>">
                            </div>
                            
                            <!-- Per page -->
                            <div class="td-filter-field">
                                <label for="per_page"><?php _e('Per page:', 'td-link'); ?></label>
                                <select name="per_page" id="per_page">
                                    <?php
                                    $per_page_options = [10, 20, 50, 100];
                                    foreach ($per_page_options as $option) {
                                        $selected = $per_page == $option ? 'selected' : '';
                                        echo '<option value="' . $option . '" ' . $selected . '>' . $option . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="td-filter-field">
                                <button type="submit" class="button"><?php _e('Filter', 'td-link'); ?></button>
                                <a href="?page=td-models&tab=<?php echo $current_tab; ?>" class="button"><?php _e('Reset', 'td-link'); ?></a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <?php if (empty($models)) : ?>
                    <div class="notice notice-info">
                        <p>
                            <?php 
                            if ($current_tab === 'pending') {
                                _e('No pending 3D models found.', 'td-link');
                            } elseif ($current_tab === 'orders') {
                                _e('No 3D models in orders found.', 'td-link');
                            } else {
                                _e('No 3D models found.', 'td-link');
                            }
                            ?>
                        </p>
                    </div>
                <?php else : ?>
                    <div class="td-models-count">
                        <p>
                            <?php printf(__('Showing %d-%d of %d models', 'td-link'), 
                                ($page - 1) * $per_page + 1, 
                                min($page * $per_page, $total), 
                                $total); 
                            ?>
                        </p>
                    </div>
                    
                    <table class="td-models-table wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'td-link'); ?></th>
                                <th><?php _e('User', 'td-link'); ?></th>
                                <th><?php _e('Product', 'td-link'); ?></th>
                                <th><?php _e('Status', 'td-link'); ?></th>
                                <th><?php _e('Created', 'td-link'); ?></th>
                                <th><?php _e('Actions', 'td-link'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Group models by order for better display
                            $grouped_models = [];
                            $order_counts = [];
                            
                            // Group models by order_id and count them
                            foreach ($models as $model) {
                                $order_key = $model->order_id ? $model->order_id : 'no_order_' . $model->id;
                                if (!isset($grouped_models[$order_key])) {
                                    $grouped_models[$order_key] = [];
                                    $order_counts[$order_key] = 0;
                                }
                                $grouped_models[$order_key][] = $model;
                                $order_counts[$order_key]++;
                            }
                            
                            // Render models by group
                            $current_order_id = 0;
                            $row_number = 0;
                            
                            foreach ($models as $model) : 
                                $row_number++;
                                $user = get_user_by('id', $model->user_id);
                                $product = wc_get_product($model->product_id);
                                $status_class = '';
                                
                                switch ($model->status) {
                                    case 'in_cart':
                                        $status_text = __('In Cart', 'td-link');
                                        $status_class = 'td-status-in-cart';
                                        break;
                                    case 'ordered':
                                        $status_text = __('Ordered', 'td-link');
                                        $status_class = 'td-status-ordered';
                                        break;
                                    case 'deleted':
                                        $status_text = __('Deleted', 'td-link');
                                        $status_class = 'td-status-deleted';
                                        break;
                                    default:
                                        $status_text = $model->status;
                                }
                                
                                // Check if file exists
                                $file_exists = file_exists($model->file_path);
                                
                                // Determine if this is the first model in an order group
                                $is_first_in_order = false;
                                $is_new_order = false;
                                
                                if ($model->order_id && $model->order_id != $current_order_id) {
                                    $current_order_id = $model->order_id;
                                    $is_first_in_order = true;
                                    $is_new_order = true;
                                } elseif (!$model->order_id && $current_order_id != 0) {
                                    $current_order_id = 0;
                                    $is_new_order = true;
                                }
                                
                                // Order count for this model's order
                                $order_model_count = 0;
                                $order_key = $model->order_id ? $model->order_id : 'no_order_' . $model->id;
                                if (isset($order_counts[$order_key])) {
                                    $order_model_count = $order_counts[$order_key];
                                }
                                
                                // Add order header if this is first model in a multi-model order
                                if ($is_new_order && $model->order_id && $order_model_count > 1) :
                                    $order = wc_get_order($model->order_id);
                                    if ($order) :
                                        ?>
                                        <tr class="td-order-group-header">
                                            <td colspan="6">
                                                <div class="td-order-group-info">
                                                    <span class="td-order-icon"><span class="dashicons dashicons-cart"></span></span>
                                                    <strong>
                                                        <a href="<?php echo admin_url('post.php?post=' . $model->order_id . '&action=edit'); ?>" target="_blank">
                                                            <?php printf(__('Order #%s', 'td-link'), $order->get_order_number()); ?>
                                                        </a>
                                                    </strong>
                                                    <span class="td-order-customer">
                                                        <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>
                                                    </span>
                                                    <span class="td-order-date">
                                                        <?php echo date_i18n(get_option('date_format'), strtotime($order->get_date_created())); ?>
                                                    </span>
                                                    <span class="td-order-model-count">
                                                        <?php printf(_n('%s model', '%s models', $order_model_count, 'td-link'), number_format_i18n($order_model_count)); ?>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php
                                // Apply different styling based on order grouping
                                $row_class = '';
                                if ($model->order_id && $order_model_count > 1) {
                                    $row_class = ' class="td-order-group-item"';
                                    
                                    // Additional class for the first item
                                    if ($is_first_in_order) {
                                        $row_class = ' class="td-order-group-item td-order-group-first-item"';
                                    }
                                }
                                ?>
                                
                                <tr<?php echo $row_class; ?>>
                                    <td>
                                        <?php echo $model->id; ?>
                                        <?php if ($model->order_id && $order_model_count > 1): ?>
                                            <div class="td-model-index"><?php echo sprintf(__('Model %d of %d', 'td-link'), 
                                                array_search($model, $grouped_models[$model->order_id]) + 1, 
                                                count($grouped_models[$model->order_id])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user) : ?>
                                            <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" target="_blank">
                                                <?php echo esc_html($user->display_name); ?>
                                            </a>
                                            <br>
                                            <small><?php echo esc_html($user->user_email); ?></small>
                                        <?php else : ?>
                                            <?php _e('Guest', 'td-link'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product) : ?>
                                            <a href="<?php echo admin_url('post.php?post=' . $model->product_id . '&action=edit'); ?>" target="_blank">
                                                <?php echo esc_html($product->get_name()); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php _e('Product not found', 'td-link'); ?>
                                            (ID: <?php echo $model->product_id; ?>)
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="td-status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                        <?php if ($model->status === 'in_cart' && $model->expires_at) : ?>
                                            <br>
                                            <small>
                                                <?php 
                                                $expires = strtotime($model->expires_at);
                                                $now = time();
                                                
                                                if ($expires > $now) {
                                                    printf(__('Expires in %s', 'td-link'), human_time_diff($now, $expires));
                                                } else {
                                                    _e('Expired', 'td-link');
                                                }
                                                ?>
                                            </small>
                                        <?php endif; ?>
                                        
                                        <?php if ($model->order_id && $order_model_count == 1) : ?>
                                            <br>
                                            <small>
                                                <a href="<?php echo admin_url('post.php?post=' . $model->order_id . '&action=edit'); ?>" target="_blank">
                                                    <?php printf(__('Order #%s', 'td-link'), $model->order_id); ?>
                                                </a>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($model->created_at)); ?>
                                    </td>
                                    <td>
                                        <div class="td-actions">
                                            <?php if ($file_exists) : ?>
                                                <a href="<?php echo esc_url($model->file_url); ?>" class="button button-secondary" target="_blank">
                                                    <span class="dashicons dashicons-download"></span> <?php _e('Download', 'td-link'); ?>
                                                </a>
                                            <?php else : ?>
                                                <span class="td-file-missing"><?php _e('File missing', 'td-link'); ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($model->parameters) : ?>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=td-model-viewer&model_id=' . $model->id . '&order_id=' . $model->order_id)); ?>" class="button button-primary" target="_blank">
                                                    <span class="dashicons dashicons-visibility"></span> <?php _e('View 3D Model', 'td-link'); ?>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($model->status !== 'deleted') : ?>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=td-models&tab=' . $current_tab . '&action=delete&model_id=' . $model->id), 'delete_model_' . $model->id); ?>" class="button button-link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this model?', 'td-link'); ?>');">
                                                    <span class="dashicons dashicons-trash"></span> <?php _e('Delete', 'td-link'); ?>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($model->parameters) : ?>
                                                <button type="button" class="button button-secondary td-view-parameters" data-model-id="<?php echo $model->id; ?>">
                                                    <span class="dashicons dashicons-visibility"></span> <?php _e('View Parameters', 'td-link'); ?>
                                                </button>
                                                
                                                <div id="td-parameters-<?php echo $model->id; ?>" class="td-parameters-container" style="display:none;">
                                                    <h3><?php _e('Model Parameters', 'td-link'); ?></h3>
                                                    <table class="td-parameters-table">
                                                        <thead>
                                                            <tr>
                                                                <th><?php _e('Parameter', 'td-link'); ?></th>
                                                                <th><?php _e('Value', 'td-link'); ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $parameters = unserialize($model->parameters);
                                                            if (is_array($parameters)) :
                                                                foreach ($parameters as $param_id => $param) :
                                                                    // Format the value for display
                                                                    $display_value = $param['value'];
                                                                    
                                                                    // Format color values with improved swatch
                                                                    if ($param['control_type'] === 'color') {
                                                                        // Try to get color information
                                                                        $color_hex = '';
                                                                        $color_name = $display_value;
                                                                        
                                                                        // Method 1: Check if the value itself is already a hex color
                                                                        if (preg_match('/#([a-f0-9]{3}){1,2}\b/i', $display_value)) {
                                                                            $color_hex = $display_value;
                                                                        }
                                                                        // Method 2: Try to find this color by name in the global colors
                                                                        else if (class_exists('TD_Colors_Manager')) {
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
                                                                    <th><?php echo esc_html($param['display_name']); ?></th>
                                                                    <td><?php echo $display_value; ?></td>
                                                                </tr>
                                                            <?php
                                                                endforeach;
                                                            else :
                                                            ?>
                                                                <tr>
                                                                    <td colspan="2"><?php _e('No parameters found or invalid format', 'td-link'); ?></td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1) : ?>
                        <div class="td-pagination">
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(_n('%s item', '%s items', $total, 'td-link'), number_format_i18n($total)); ?>
                                </span>
                                
                                <span class="pagination-links">
                                    <?php
                                    // First page
                                    if ($page > 1) {
                                        echo '<a class="first-page button" href="' . esc_url(add_query_arg('paged', 1)) . '"><span class="screen-reader-text">' . __('First page', 'td-link') . '</span><span aria-hidden="true">&laquo;</span></a>';
                                    } else {
                                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
                                    }
                                    
                                    // Previous page
                                    if ($page > 1) {
                                        echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', max(1, $page - 1))) . '"><span class="screen-reader-text">' . __('Previous page', 'td-link') . '</span><span aria-hidden="true">&lsaquo;</span></a>';
                                    } else {
                                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
                                    }
                                    
                                    // Current page
                                    echo '<span class="paging-input">';
                                    echo '<label for="current-page-selector" class="screen-reader-text">' . __('Current Page', 'td-link') . '</label>';
                                    echo '<input class="current-page" id="current-page-selector" type="text" name="paged" value="' . $page . '" size="1" aria-describedby="table-paging">';
                                    echo ' ' . __('of', 'td-link') . ' <span class="total-pages">' . $total_pages . '</span>';
                                    echo '</span>';
                                    
                                    // Next page
                                    if ($page < $total_pages) {
                                        echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', min($total_pages, $page + 1))) . '"><span class="screen-reader-text">' . __('Next page', 'td-link') . '</span><span aria-hidden="true">&rsaquo;</span></a>';
                                    } else {
                                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
                                    }
                                    
                                    // Last page
                                    if ($page < $total_pages) {
                                        echo '<a class="last-page button" href="' . esc_url(add_query_arg('paged', $total_pages)) . '"><span class="screen-reader-text">' . __('Last page', 'td-link') . '</span><span aria-hidden="true">&raquo;</span></a>';
                                    } else {
                                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
        <?php elseif ($current_tab === 'stats') : ?>
            <!-- Statistics Tab -->
            <div class="active">
                <h2><?php _e('3D Models Statistics', 'td-link'); ?></h2>
                
                <div class="td-stats-grid">
                    <div class="td-stats-card">
                        <h3><?php _e('Total Models', 'td-link'); ?></h3>
                        <div class="td-stats-number"><?php echo number_format_i18n($stats['total']); ?></div>
                        <div class="td-stats-meta"><?php _e('All 3D models', 'td-link'); ?></div>
                    </div>
                    
                    <div class="td-stats-card">
                        <h3><?php _e('Models Today', 'td-link'); ?></h3>
                        <div class="td-stats-number"><?php echo number_format_i18n($stats['today']); ?></div>
                        <div class="td-stats-meta"><?php _e('Created in the last 24 hours', 'td-link'); ?></div>
                    </div>
                    
                    <div class="td-stats-card">
                        <h3><?php _e('Storage Usage', 'td-link'); ?></h3>
                        <div class="td-stats-number">
                            <?php 
                            if ($stats['storage_usage'] < 1048576) {
                                echo round($stats['storage_usage'] / 1024, 2) . ' KB';
                            } else {
                                echo round($stats['storage_usage'] / 1048576, 2) . ' MB';
                            }
                            ?>
                        </div>
                        <div class="td-stats-meta">
                            <?php printf(__('%s%% of 1 GB limit', 'td-link'), $stats['storage_percent']); ?>
                        </div>
                        <div class="td-progress-bar">
                            <div class="td-progress-fill" style="width: <?php echo min(100, $stats['storage_percent']); ?>%;"></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($stats['by_status'])) : ?>
                        <div class="td-stats-card">
                            <h3><?php _e('Models by Status', 'td-link'); ?></h3>
                            <ul class="td-stats-list">
                                <?php foreach ($stats['by_status'] as $status => $count) : 
                                    switch ($status) {
                                        case 'in_cart':
                                            $status_text = __('In Cart', 'td-link');
                                            break;
                                        case 'ordered':
                                            $status_text = __('Ordered', 'td-link');
                                            break;
                                        case 'deleted':
                                            $status_text = __('Deleted', 'td-link');
                                            break;
                                        default:
                                            $status_text = $status;
                                    }
                                ?>
                                    <li>
                                        <span class="td-stats-label"><?php echo $status_text; ?></span>
                                        <span class="td-stats-value"><?php echo number_format_i18n($count); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($stats['top_users'])) : ?>
                        <div class="td-stats-card">
                            <h3><?php _e('Top Users', 'td-link'); ?></h3>
                            <ul class="td-stats-list">
                                <?php foreach ($stats['top_users'] as $user_data) : 
                                    $user = get_user_by('id', $user_data->user_id);
                                    $user_name = $user ? $user->display_name : __('User ID', 'td-link') . ' ' . $user_data->user_id;
                                ?>
                                    <li>
                                        <span class="td-stats-label"><?php echo esc_html($user_name); ?></span>
                                        <span class="td-stats-value"><?php echo number_format_i18n($user_data->count); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($stats['top_products'])) : ?>
                        <div class="td-stats-card">
                            <h3><?php _e('Top Products', 'td-link'); ?></h3>
                            <ul class="td-stats-list">
                                <?php foreach ($stats['top_products'] as $product_data) : 
                                    $product = wc_get_product($product_data->product_id);
                                    $product_name = $product ? $product->get_name() : __('Product ID', 'td-link') . ' ' . $product_data->product_id;
                                ?>
                                    <li>
                                        <span class="td-stats-label"><?php echo esc_html($product_name); ?></span>
                                        <span class="td-stats-value"><?php echo number_format_i18n($product_data->count); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Parameters Dialog -->
<div id="td-parameters-dialog" class="td-dialog" style="display:none;">
    <div class="td-dialog-content">
        <span class="td-dialog-close">&times;</span>
        <div id="td-dialog-content"></div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        // View parameters button
        $('.td-view-parameters').on('click', function() {
            var modelId = $(this).data('model-id');
            var parametersHtml = $('#td-parameters-' + modelId).html();
            
            $('#td-dialog-content').html(parametersHtml);
            $('#td-parameters-dialog').show();
        });
        
        // Close dialog
        $('.td-dialog-close').on('click', function() {
            $('#td-parameters-dialog').hide();
        });
        
        // Close dialog when clicking outside
        $(window).on('click', function(event) {
            if ($(event.target).is('#td-parameters-dialog')) {
                $('#td-parameters-dialog').hide();
            }
        });
    });
</script>