<?php
// Exit if accessed directly
defined('ABSPATH') || exit;
?>
<div class="wrap td-dashboard">
    <h1><?php echo esc_html__('3D Link Dashboard', 'td-link'); ?></h1>
    
    <div class="td-dashboard-container">
        <div class="td-dashboard-main">
            <div class="td-welcome-panel">
                <h2><?php echo esc_html__('Welcome to 3D Link', 'td-link'); ?></h2>
                <p class="td-welcome-description">
                    <?php echo esc_html__('3D Link allows you to integrate PolygonJS 3D models with your WooCommerce products, creating customizable 3D product displays.', 'td-link'); ?>
                </p>
                
                <div class="td-welcome-panel-content">
                    <div class="td-welcome-panel-column">
                        <h3><?php echo esc_html__('Getting Started', 'td-link'); ?></h3>
                        <ul>
                            <li>
                                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="button button-primary">
                                    <?php echo esc_html__('Create New 3D Product', 'td-link'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=td-colors')); ?>">
                                    <?php echo esc_html__('Manage 3D Model Colors', 'td-link'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=td-link-settings')); ?>">
                                    <?php echo esc_html__('Configure Plugin Settings', 'td-link'); ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="td-welcome-panel-column">
                        <h3><?php echo esc_html__('3D Model Integration', 'td-link'); ?></h3>
                        <ul>
                            <li><?php echo esc_html__('Upload your PolygonJS models to:', 'td-link'); ?> <code>C:\laragon\www\alles3d\3d\dist</code></li>
                            <li><?php echo esc_html__('Use Bricks elements to embed your 3D models', 'td-link'); ?></li>
                            <li><?php echo esc_html__('Add custom parameters to make models interactive', 'td-link'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="td-welcome-panel-column td-welcome-panel-last">
                        <h3><?php echo esc_html__('Recent 3D Products', 'td-link'); ?></h3>
                        
                        <?php
                        // Query recent products with PolygonJS enabled
                        $products_query = new WP_Query([
                            'post_type' => 'product',
                            'posts_per_page' => 5,
                            'meta_query' => [
                                [
                                    'key' => '_enable_polygonjs',
                                    'value' => 'yes',
                                    'compare' => '='
                                ]
                            ]
                        ]);
                        
                        if ($products_query->have_posts()) :
                            echo '<ul class="td-recent-products">';
                            while ($products_query->have_posts()) :
                                $products_query->the_post();
                                echo '<li><a href="' . esc_url(get_edit_post_link()) . '">' . get_the_title() . '</a></li>';
                            endwhile;
                            echo '</ul>';
                            wp_reset_postdata();
                        else :
                            echo '<p>' . esc_html__('No 3D products found. Create your first 3D product to get started.', 'td-link') . '</p>';
                        endif;
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="td-dashboard-widgets">
                <div class="td-dashboard-widget">
                    <h3><?php echo esc_html__('3D Link Stats', 'td-link'); ?></h3>
                    <div class="td-stats-container">
                        <?php
                        // Total 3D products - FIXED VERSION
                        $products_query = get_posts([
                            'post_type' => 'product',
                            'meta_key' => '_enable_polygonjs',
                            'meta_value' => 'yes',
                            'numberposts' => -1,
                            'fields' => 'ids'
                        ]);
                        $total_products = count($products_query);
                        
                        // Total colors
                        $total_colors = 0;
                        if (class_exists('TD_Colors_Manager')) {
                            $colors_manager = new TD_Colors_Manager();
                            $colors = $colors_manager->get_global_colors();
                            $total_colors = count($colors);
                        }
                        ?>
                        
                        <div class="td-stat-item">
                            <span class="td-stat-value"><?php echo esc_html($total_products); ?></span>
                            <span class="td-stat-label"><?php echo esc_html__('3D Products', 'td-link'); ?></span>
                        </div>
                        
                        <div class="td-stat-item">
                            <span class="td-stat-value"><?php echo esc_html($total_colors); ?></span>
                            <span class="td-stat-label"><?php echo esc_html__('Global Colors', 'td-link'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="td-dashboard-sidebar">
            <div class="td-dashboard-widget">
                <h3><?php echo esc_html__('Quick Links', 'td-link'); ?></h3>
                <ul class="td-quick-links">
                    <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>"><?php echo esc_html__('All Products', 'td-link'); ?></a></li>
                    <li><a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>"><?php echo esc_html__('Add New Product', 'td-link'); ?></a></li>
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=td-colors')); ?>"><?php echo esc_html__('Manage Colors', 'td-link'); ?></a></li>
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=td-link-settings')); ?>"><?php echo esc_html__('Settings', 'td-link'); ?></a></li>
                </ul>
            </div>
            
            <div class="td-dashboard-widget">
                <h3><?php echo esc_html__('Help & Support', 'td-link'); ?></h3>
                <p><?php echo esc_html__('Need help with your 3D product setup?', 'td-link'); ?></p>
                <a href="#" class="button"><?php echo esc_html__('Documentation', 'td-link'); ?></a>
            </div>
        </div>
    </div>
</div>