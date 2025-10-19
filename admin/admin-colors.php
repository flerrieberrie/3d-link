<?php
// Exit if accessed directly
defined('ABSPATH') || exit;

// Get existing colors
$colors_manager = new TD_Colors_Manager();
$colors = $colors_manager->get_global_colors();

// Create nonce
$nonce = wp_create_nonce('td_colors_nonce');

?>
<div class="wrap td-colors-manager">
    <h1><?php echo esc_html__('3D Link Global Colors', 'td-link'); ?></h1>
    
    <div class="td-admin-notice notice notice-success" style="display: none;">
        <p></p>
    </div>
    
    <div class="td-admin-notice notice notice-error" style="display: none;">
        <p></p>
    </div>
    
    <div class="td-colors-container">
        <div class="td-colors-main">
            <div class="td-colors-list-container">
                <h2><?php echo esc_html__('Available Colors', 'td-link'); ?></h2>
                
                <?php if (empty($colors)) : ?>
                    <div class="td-colors-empty">
                        <p><?php echo esc_html__('No colors defined yet. Add your first color using the form.', 'td-link'); ?></p>
                    </div>
                <?php else : ?>
                    <div class="td-colors-list">
                        <?php foreach ($colors as $id => $color) : ?>
                            <div class="td-color-item" data-id="<?php echo esc_attr($id); ?>">
                                <div class="color-preview" style="background-color: <?php echo esc_attr($color['hex']); ?>"></div>
                                <div class="color-details">
                                    <h3><?php echo esc_html($color['name']); ?></h3>
                                    <div class="color-meta">
                                        <span class="color-hex"><?php echo esc_html($color['hex']); ?></span>
                                        <span class="color-rgb">
                                            RGB: <?php 
                                                echo esc_html(sprintf(
                                                    "%.2f, %.2f, %.2f", 
                                                    $color['rgb'][0], 
                                                    $color['rgb'][1], 
                                                    $color['rgb'][2]
                                                )); 
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="color-actions">
                                    <button type="button" class="button edit-color" title="<?php esc_attr_e('Edit', 'td-link'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="button delete-color" title="<?php esc_attr_e('Delete', 'td-link'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                    <label class="color-stock-toggle">
                                        <input type="checkbox" 
                                            <?php checked(!empty($color['in_stock']) && $color['in_stock']); ?> 
                                            class="toggle-stock">
                                        <span><?php esc_html_e('In Stock', 'td-link'); ?></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="td-color-form-container">
            <h2 id="color-form-title"><?php echo esc_html__('Add New Color', 'td-link'); ?></h2>
            
            <form id="td-color-form" method="post">
                <input type="hidden" id="color_id" name="color_id" value="">
                <input type="hidden" name="action" value="td_save_global_colors">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                
                <div class="form-field">
                    <label for="color_name"><?php esc_html_e('Color Name', 'td-link'); ?></label>
                    <input type="text" id="color_name" name="name" required placeholder="<?php esc_attr_e('e.g., Cherry Red', 'td-link'); ?>">
                </div>
                
                <div class="form-field color-picker-field">
                    <label for="color_hex"><?php esc_html_e('Hex Color', 'td-link'); ?></label>
                    <input type="text" id="color_hex" name="hex" required placeholder="#FF0000" class="color-field">
                </div>
                
                <div class="form-fields-group">
                    <h3><?php esc_html_e('RGB Values (0-1 for PolygonJS)', 'td-link'); ?></h3>
                    
                    <div class="rgb-fields">
                        <div class="form-field rgb-field">
                            <label for="color_red"><?php esc_html_e('Red', 'td-link'); ?></label>
                            <input type="number" id="color_red" name="red" step="0.01" min="0" max="1" required value="1">
                        </div>
                        
                        <div class="form-field rgb-field">
                            <label for="color_green"><?php esc_html_e('Green', 'td-link'); ?></label>
                            <input type="number" id="color_green" name="green" step="0.01" min="0" max="1" required value="0">
                        </div>
                        
                        <div class="form-field rgb-field">
                            <label for="color_blue"><?php esc_html_e('Blue', 'td-link'); ?></label>
                            <input type="number" id="color_blue" name="blue" step="0.01" min="0" max="1" required value="0">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary save-color"><?php esc_html_e('Save Color', 'td-link'); ?></button>
                    <button type="button" class="button cancel-edit" style="display: none;"><?php esc_html_e('Cancel', 'td-link'); ?></button>
                </div>
            </form>
            
            <div class="usage-instructions">
                <h3><?php esc_html_e('How to Use Colors', 'td-link'); ?></h3>
                <p><?php esc_html_e('Colors defined here will be available in the product editor under the "PolygonJS Parameters" section when you add a color parameter.', 'td-link'); ?></p>
                <p><?php esc_html_e('You can quickly add RGB color parameters to your products by clicking the "Add RGB Color" button in the product editor.', 'td-link'); ?></p>
                <p><?php esc_html_e('Toggle the "In Stock" switch to control which colors are available for selection in products.', 'td-link'); ?></p>
            </div>
        </div>
    </div>
</div>