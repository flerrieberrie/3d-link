<?php
/**
 * Dashboard Widget for 3D Models
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Register the dashboard widget
 */
function td_register_dashboard_widget() {
    wp_add_dashboard_widget(
        'td_dashboard_widget',
        __('3D Models Overview', 'td-link'),
        'td_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'td_register_dashboard_widget');

/**
 * Display the dashboard widget content
 */
function td_dashboard_widget_content() {
    // Check if models manager class exists
    if (!class_exists('TD_Models_Manager')) {
        echo '<p>' . __('Models Manager not initialized.', 'td-link') . '</p>';
        return;
    }
    
    // Get models statistics
    $models_manager = new TD_Models_Manager();
    $stats = $models_manager->get_models_stats();
    
    // Display statistics
    ?>
    <div class="td-dashboard-stats">
        <div class="td-dashboard-stat">
            <h3><?php _e('Total Models', 'td-link'); ?></h3>
            <div class="td-dashboard-number"><?php echo number_format_i18n($stats['total']); ?></div>
        </div>
        
        <div class="td-dashboard-stat">
            <h3><?php _e('Models Today', 'td-link'); ?></h3>
            <div class="td-dashboard-number"><?php echo number_format_i18n($stats['today']); ?></div>
        </div>
        
        <?php if (!empty($stats['by_status'])) : ?>
            <div class="td-dashboard-stat">
                <h3><?php _e('Status Breakdown', 'td-link'); ?></h3>
                <ul class="td-dashboard-list">
                    <?php 
                    foreach ($stats['by_status'] as $status => $count) : 
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
                            <span class="td-dashboard-label"><?php echo $status_text; ?></span>
                            <span class="td-dashboard-value"><?php echo number_format_i18n($count); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="td-dashboard-stat">
            <h3><?php _e('Storage Usage', 'td-link'); ?></h3>
            <div class="td-dashboard-number">
                <?php 
                if ($stats['storage_usage'] < 1048576) {
                    echo round($stats['storage_usage'] / 1024, 2) . ' KB';
                } else {
                    echo round($stats['storage_usage'] / 1048576, 2) . ' MB';
                }
                ?>
            </div>
            <div class="td-dashboard-meta">
                <?php printf(__('%s%% of 1 GB limit', 'td-link'), $stats['storage_percent']); ?>
            </div>
            <div class="td-dashboard-progress">
                <div class="td-dashboard-fill" style="width: <?php echo min(100, $stats['storage_percent']); ?>%;"></div>
            </div>
        </div>
    </div>
    
    <p class="td-dashboard-footer">
        <a href="<?php echo admin_url('admin.php?page=td-models&tab=stats'); ?>"><?php _e('View Detailed Statistics', 'td-link'); ?></a> |
        <a href="<?php echo admin_url('admin.php?page=td-models'); ?>"><?php _e('Manage 3D Models', 'td-link'); ?></a>
    </p>
    
    <style>
        /* Dashboard widget styles */
        .td-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .td-dashboard-stat {
            margin-bottom: 15px;
        }
        
        .td-dashboard-stat h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .td-dashboard-number {
            font-size: 22px;
            font-weight: 600;
            color: #2271b1;
            margin-bottom: 5px;
        }
        
        .td-dashboard-meta {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .td-dashboard-progress {
            height: 8px;
            background-color: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .td-dashboard-fill {
            height: 100%;
            background-color: #2271b1;
        }
        
        .td-dashboard-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .td-dashboard-list li {
            padding: 5px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        
        .td-dashboard-list li:last-child {
            border-bottom: none;
        }
        
        .td-dashboard-label {
            color: #666;
        }
        
        .td-dashboard-value {
            font-weight: 600;
            color: #444;
        }
        
        .td-dashboard-footer {
            margin: 0;
            padding-top: 10px;
            border-top: 1px solid #eee;
            text-align: center;
        }
    </style>
    <?php
}