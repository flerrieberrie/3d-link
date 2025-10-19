<?php
/**
 * Admin Settings Page for 3D Link
 * 
 * Organized settings page with categorized tabs for better user experience
 */

defined('ABSPATH') || exit;

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Get the sections manager
global $td_link;
$sections_manager = $td_link->sections_manager;

// Determine which tab is active
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

// Handle form submissions based on active tab
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check nonce
    if (!isset($_POST['td_link_settings_nonce']) || !wp_verify_nonce($_POST['td_link_settings_nonce'], 'td_link_settings_nonce')) {
        wp_die(__('Security check failed.', 'td-link'));
    }
    
    $success_message = '';
    
    switch ($active_tab) {
        case 'general':
            // Process general settings
            if (isset($_POST['td_general_submit'])) {
                // Measurement unit
                $measurement_unit = isset($_POST['td_measurement_unit']) ? sanitize_text_field($_POST['td_measurement_unit']) : 'cm';
                update_option('td_measurement_unit', $measurement_unit);
                
                // Enable debug mode
                $enable_debug = isset($_POST['td_enable_debug']) ? 'yes' : 'no';
                update_option('td_link_enable_debug', $enable_debug);
                
                // Default aspect ratio
                $aspect_ratio = isset($_POST['td_default_aspect_ratio']) ? sanitize_text_field($_POST['td_default_aspect_ratio']) : '16:9';
                update_option('td_link_default_aspect_ratio', $aspect_ratio);
                
                $success_message = __('General settings saved successfully.', 'td-link');
            }
            break;
            
        case 'polygonjs':
            // Process PolygonJS settings
            if (isset($_POST['td_polygonjs_submit'])) {
                // Default dist path
                $polygonjs_dist_path = isset($_POST['td_default_dist_path']) ? sanitize_text_field($_POST['td_default_dist_path']) : '3d/dist';
                update_option('td_link_default_dist_path', $polygonjs_dist_path);
                
                // Default source directory
                $source_directory_path = isset($_POST['td_default_source_path']) ? sanitize_text_field($_POST['td_default_source_path']) : '';
                update_option('td_link_default_source_path', $source_directory_path);
                
                // Default exporter node path
                $exporter_node_path = isset($_POST['td_default_exporter_node_path']) ? sanitize_text_field($_POST['td_default_exporter_node_path']) : '';
                update_option('td_default_exporter_node_path', $exporter_node_path);
                
                $success_message = __('PolygonJS settings saved successfully.', 'td-link');
            }
            break;
            
        case 'display':
            // Process display settings
            if (isset($_POST['td_display_submit'])) {
                // Hidden parameters
                $hidden_parameters = isset($_POST['td_hidden_parameters']) ? (array) $_POST['td_hidden_parameters'] : array();
                update_option('td_hidden_parameters', $hidden_parameters);
                
                $success_message = __('Display settings saved successfully.', 'td-link');
            }
            break;
            
        case 'sections':
            // Process sections
            if (isset($_POST['td_sections_submit'])) {
                // Handle new section addition
                if (!empty($_POST['new_section_name'])) {
                    $new_section_name = sanitize_text_field($_POST['new_section_name']);
                    $section_type = isset($_POST['new_section_type']) ? sanitize_text_field($_POST['new_section_type']) : 'regular';
                    $is_helper = $section_type === 'helper';
                    
                    $sections_manager->add_section($new_section_name, ['is_helper' => $is_helper]);
                    $section_type_text = $is_helper ? __('helper', 'td-link') : __('regular', 'td-link');
                    $success_message = sprintf(__('New %s section "%s" added successfully.', 'td-link'), $section_type_text, $new_section_name);
                }
                
                // Handle section updates
                if (isset($_POST['section']) && is_array($_POST['section'])) {
                    foreach ($_POST['section'] as $section_id => $data) {
                        $name = sanitize_text_field($data['name']);
                        $order = isset($data['order']) ? intval($data['order']) : 10;
                        $sections_manager->update_section($section_id, $name, $order);
                    }
                    $success_message = __('Sections updated successfully.', 'td-link');
                }
                
                // Handle section deletion
                if (isset($_POST['delete_section']) && !empty($_POST['delete_section'])) {
                    $section_id = sanitize_text_field($_POST['delete_section']);
                    if ($sections_manager->delete_section($section_id)) {
                        $success_message = __('Section deleted successfully.', 'td-link');
                    } else {
                        $success_message = __('Default sections cannot be deleted.', 'td-link');
                    }
                }
            }
            break;
    }
    
    // Show success message if any
    if (!empty($success_message)) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_message) . '</p></div>';
    }
}

// Get current settings
$measurement_unit = get_option('td_measurement_unit', 'cm');
$enable_debug = get_option('td_link_enable_debug', 'no');
$aspect_ratio = get_option('td_link_default_aspect_ratio', '16:9');
$polygonjs_dist_path = get_option('td_link_default_dist_path', '3d/dist');
$source_directory_path = get_option('td_link_default_source_path', '');
$exporter_node_path = get_option('td_default_exporter_node_path', '');
$hidden_parameters = get_option('td_hidden_parameters', array());
$sections = $sections_manager->get_sections();

// Initialize parameters list for display settings
$parameters_manager = new TD_Parameters_Manager();
$all_parameters = array();
$parameter_sections = array();

// Initialize section containers
foreach ($sections as $section_id => $section) {
    $parameter_sections[$section_id] = [
        'title' => $section['name'],
        'parameters' => []
    ];
}

// Get parameters from all products
$products = wc_get_products(array(
    'limit' => -1,
    'status' => 'publish',
    'type' => array('simple', 'variable'),
));

foreach ($products as $product) {
    $product_parameters = $parameters_manager->get_parameters($product->get_id());
    
    foreach ($product_parameters as $param) {
        $param_key = sanitize_title($param['display_name']);
        
        // Skip if already processed this parameter name
        if (isset($all_parameters[$param_key])) {
            continue;
        }
        
        // Add to all parameters
        $all_parameters[$param_key] = $param['display_name'];
        
        // Get parameter section (auto-detect if not set)
        $section_id = isset($param['section']) && !empty($param['section']) 
            ? $param['section'] 
            : $sections_manager->auto_categorize_parameter($param);
            
        // Make sure section exists (fallback to other if not)
        if (!isset($parameter_sections[$section_id])) {
            $section_id = 'other';
        }
            
        // Add parameter to its section
        $parameter_sections[$section_id]['parameters'][$param_key] = $param['display_name'];
    }
}

?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="?page=td-link-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General Settings', 'td-link'); ?>
        </a>
        <a href="?page=td-link-settings&tab=polygonjs" class="nav-tab <?php echo $active_tab === 'polygonjs' ? 'nav-tab-active' : ''; ?>">
            <?php _e('PolygonJS Integration', 'td-link'); ?>
        </a>
        <a href="?page=td-link-settings&tab=display" class="nav-tab <?php echo $active_tab === 'display' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Display Options', 'td-link'); ?>
        </a>
        <a href="?page=td-link-settings&tab=sections" class="nav-tab <?php echo $active_tab === 'sections' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Parameter Sections', 'td-link'); ?>
        </a>
    </nav>
    
    <!-- Tab Content -->
    <div class="td-settings-content">
        <?php switch ($active_tab):
            case 'general': ?>
                <!-- General Settings Tab -->
                <form method="post" action="">
                    <?php wp_nonce_field('td_link_settings_nonce'); ?>
                    
                    <div class="td-settings-container">
                        <h2><?php _e('General Settings', 'td-link'); ?></h2>
                        <p class="description"><?php _e('Configure general plugin settings and preferences.', 'td-link'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="td_measurement_unit"><?php _e('Default Measurement Unit', 'td-link'); ?></label>
                                </th>
                                <td>
                                    <select name="td_measurement_unit" id="td_measurement_unit">
                                        <option value="mm" <?php selected($measurement_unit, 'mm'); ?>><?php _e('Millimeters (mm)', 'td-link'); ?></option>
                                        <option value="cm" <?php selected($measurement_unit, 'cm'); ?>><?php _e('Centimeters (cm)', 'td-link'); ?></option>
                                        <option value="m" <?php selected($measurement_unit, 'm'); ?>><?php _e('Meters (m)', 'td-link'); ?></option>
                                        <option value="in" <?php selected($measurement_unit, 'in'); ?>><?php _e('Inches (in)', 'td-link'); ?></option>
                                        <option value="ft" <?php selected($measurement_unit, 'ft'); ?>><?php _e('Feet (ft)', 'td-link'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('This unit will be displayed next to all dimensional parameters in the customizer and on order pages.', 'td-link'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="td_default_aspect_ratio"><?php _e('Default Viewer Aspect Ratio', 'td-link'); ?></label>
                                </th>
                                <td>
                                    <select name="td_default_aspect_ratio" id="td_default_aspect_ratio">
                                        <option value="16:9" <?php selected($aspect_ratio, '16:9'); ?>>16:9</option>
                                        <option value="4:3" <?php selected($aspect_ratio, '4:3'); ?>>4:3</option>
                                        <option value="1:1" <?php selected($aspect_ratio, '1:1'); ?>>1:1 (Square)</option>
                                        <option value="21:9" <?php selected($aspect_ratio, '21:9'); ?>>21:9 (Ultra-wide)</option>
                                    </select>
                                    <p class="description"><?php _e('Default aspect ratio for the 3D viewer. Can be overridden per product.', 'td-link'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Debug Mode', 'td-link'); ?></th>
                                <td>
                                    <label for="td_enable_debug">
                                        <input type="checkbox" name="td_enable_debug" id="td_enable_debug" value="yes" <?php checked($enable_debug, 'yes'); ?> />
                                        <?php _e('Enable debug mode', 'td-link'); ?>
                                    </label>
                                    <p class="description"><?php _e('When enabled, additional debugging information will be logged to the browser console.', 'td-link'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="td_general_submit" class="button button-primary" value="<?php esc_attr_e('Save General Settings', 'td-link'); ?>" />
                    </p>
                </form>
                <?php break;
                
            case 'polygonjs': ?>
                <!-- PolygonJS Settings Tab -->
                <form method="post" action="">
                    <?php wp_nonce_field('td_link_settings_nonce'); ?>
                    
                    <div class="td-settings-container">
                        <h2><?php _e('PolygonJS Integration Settings', 'td-link'); ?></h2>
                        <p class="description"><?php _e('Configure PolygonJS integration paths and technical settings.', 'td-link'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="td_default_dist_path"><?php _e('Default Distribution Path', 'td-link'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="td_default_dist_path" id="td_default_dist_path" 
                                           value="<?php echo esc_attr($polygonjs_dist_path); ?>" class="regular-text" 
                                           placeholder="3d/dist" />
                                    <p class="description">
                                        <?php _e('Path to PolygonJS distribution files relative to WordPress site root. Default: 3d/dist', 'td-link'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="td_default_source_path"><?php _e('Development Source Directory', 'td-link'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="td_default_source_path" id="td_default_source_path" 
                                           value="<?php echo esc_attr($source_directory_path); ?>" class="regular-text" 
                                           placeholder="C:/alles3d" />
                                    <p class="description">
                                        <?php _e('Full path to your development PolygonJS directory. Used for development purposes only.', 'td-link'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="td_default_exporter_node_path"><?php _e('Default Exporter Node Path', 'td-link'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="td_default_exporter_node_path" id="td_default_exporter_node_path" 
                                           value="<?php echo esc_attr($exporter_node_path); ?>" class="regular-text" 
                                           placeholder="/geo1/exporterGLTF1" />
                                    <p class="description">
                                        <?php _e('The default path to the exporter node in your PolygonJS scenes. Example: "/geo1/exporterGLTF1". This can be overridden on a per-product basis.', 'td-link'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="td_polygonjs_submit" class="button button-primary" value="<?php esc_attr_e('Save PolygonJS Settings', 'td-link'); ?>" />
                    </p>
                </form>
                <?php break;
                
            case 'display': ?>
                <!-- Display Settings Tab -->
                <form method="post" action="">
                    <?php wp_nonce_field('td_link_settings_nonce'); ?>
                    
                    <div class="td-settings-container">
                        <h2><?php _e('Display Options', 'td-link'); ?></h2>
                        <p class="description"><?php _e('Configure which parameter sections should be hidden in checkout and order details.', 'td-link'); ?></p>
                        
                        <div class="td-settings-section">
                            <h3><?php _e('Parameter Visibility in Checkout', 'td-link'); ?></h3>
                            <p><?php _e('Select which sections should be hidden in checkout and order details. Hidden sections will not appear in order emails or customer order pages.', 'td-link'); ?></p>
                            
                            <fieldset>
                                <?php foreach ($sections as $section_id => $section): ?>
                                    <?php
                                        $hidden_section_key = 'section_' . $section_id;
                                        $has_parameters = isset($parameter_sections[$section_id]) && !empty($parameter_sections[$section_id]['parameters']);
                                    ?>
                                    <label <?php echo !$has_parameters ? 'class="disabled-option" style="opacity: 0.6;"' : ''; ?>>
                                        <input type="checkbox"
                                              name="td_hidden_parameters[]"
                                              value="<?php echo esc_attr($hidden_section_key); ?>"
                                              <?php checked(in_array($hidden_section_key, $hidden_parameters)); ?>
                                              <?php echo !$has_parameters ? 'disabled' : ''; ?> />
                                        <?php printf(__('Hide "%s" section', 'td-link'), $section['name']); ?>
                                        <?php if (!$has_parameters): ?>
                                            <span class="section-info" style="color: #999; font-style: italic; margin-left: 5px;"><?php _e('(No parameters in this section)', 'td-link'); ?></span>
                                        <?php else: ?>
                                            <span class="section-info" style="color: #666; margin-left: 5px;">(<?php echo count($parameter_sections[$section_id]['parameters']); ?> <?php _e('parameters', 'td-link'); ?>)</span>
                                        <?php endif; ?>
                                    </label>
                                    <br>
                                <?php endforeach; ?>
                            </fieldset>
                        </div>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="td_display_submit" class="button button-primary" value="<?php esc_attr_e('Save Display Settings', 'td-link'); ?>" />
                    </p>
                </form>
                <?php break;
                
            case 'sections': ?>
                <!-- Section Management Tab -->
                <form method="post" action="">
                    <?php wp_nonce_field('td_link_settings_nonce'); ?>
                    
                    <div class="td-settings-container">
                        <h2><?php _e('Parameter Section Management', 'td-link'); ?></h2>
                        <p class="description"><?php _e('Manage sections that parameters are organized into. Sections help group related parameters together in the frontend.', 'td-link'); ?></p>
                        
                        <table class="widefat fixed td-sections-table" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="manage-column column-id" style="width: 10%;"><?php _e('ID', 'td-link'); ?></th>
                                    <th class="manage-column column-name" style="width: 25%;"><?php _e('Section Name', 'td-link'); ?></th>
                                    <th class="manage-column column-order" style="width: 10%;"><?php _e('Display Order', 'td-link'); ?></th>
                                    <th class="manage-column column-type" style="width: 20%;"><?php _e('Type', 'td-link'); ?></th>
                                    <th class="manage-column column-params" style="width: 25%;"><?php _e('Parameters', 'td-link'); ?></th>
                                    <th class="manage-column column-actions" style="width: 10%;"><?php _e('Actions', 'td-link'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sections as $section_id => $section): ?>
                                    <?php
                                        $is_helper = isset($section['is_helper']) && $section['is_helper'];
                                        $param_count = isset($parameter_sections[$section_id]['parameters']) ? count($parameter_sections[$section_id]['parameters']) : 0;
                                    ?>
                                    <tr class="<?php echo $is_helper ? 'helper-section' : ''; ?>">
                                        <td class="column-id"><?php echo esc_html($section_id); ?></td>
                                        <td class="column-name">
                                            <input type="text" name="section[<?php echo esc_attr($section_id); ?>][name]"
                                                   value="<?php echo esc_attr($section['name']); ?>" class="regular-text" />
                                        </td>
                                        <td class="column-order">
                                            <input type="number" name="section[<?php echo esc_attr($section_id); ?>][order]"
                                                   value="<?php echo esc_attr($section['order']); ?>" class="small-text" min="1" step="1" />
                                        </td>
                                        <td class="column-type">
                                            <?php if ($is_helper): ?>
                                                <span class="helper-badge"><?php _e('Helper Section', 'td-link'); ?></span>
                                                <p class="helper-description"><?php _e('Parameters in this section will be displayed beneath the 3D viewport and will not be included in orders.', 'td-link'); ?></p>
                                            <?php else: ?>
                                                <span class="section-badge"><?php _e('Regular Section', 'td-link'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="column-params">
                                            <span class="param-count"><?php echo $param_count; ?> <?php echo $param_count === 1 ? __('parameter', 'td-link') : __('parameters', 'td-link'); ?></span>
                                            <?php if ($param_count > 0): ?>
                                                <a href="#" class="toggle-parameters"><?php _e('View', 'td-link'); ?></a>
                                                <ul class="parameter-list" style="display: none;">
                                                    <?php foreach ($parameter_sections[$section_id]['parameters'] as $param_key => $param_name): ?>
                                                        <li><?php echo esc_html($param_name); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                        <td class="column-actions">
                                            <?php if (!array_key_exists($section_id, $sections_manager->default_sections)): ?>
                                                <button type="submit" name="delete_section" value="<?php echo esc_attr($section_id); ?>"
                                                        class="button delete-section"
                                                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this section? Any parameters assigned to it will be moved to the Other Parameters section.', 'td-link'); ?>')">
                                                    <?php _e('Delete', 'td-link'); ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="default-section-notice"><?php _e('Default', 'td-link'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6" class="add-new-section">
                                        <div class="new-section-container">
                                            <input type="text" name="new_section_name" placeholder="<?php esc_attr_e('New section name', 'td-link'); ?>" class="regular-text" />
                                            <select name="new_section_type" id="new_section_type">
                                                <option value="regular"><?php _e('Regular Section', 'td-link'); ?></option>
                                                <option value="helper"><?php _e('Helper Section', 'td-link'); ?></option>
                                            </select>
                                            <button type="submit" class="button button-secondary"><?php _e('Add New Section', 'td-link'); ?></button>
                                        </div>
                                        <div class="new-section-description">
                                            <p><strong><?php _e('Section Types:', 'td-link'); ?></strong></p>
                                            <ul>
                                                <li><strong><?php _e('Regular Section:', 'td-link'); ?></strong> <?php _e('Parameters appear in the main customizer and are included in orders.', 'td-link'); ?></li>
                                                <li><strong><?php _e('Helper Section:', 'td-link'); ?></strong> <?php _e('Parameters appear below the 3D viewport and are excluded from orders. Use for visualization controls.', 'td-link'); ?></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="td_sections_submit" class="button button-primary" value="<?php esc_attr_e('Save Section Settings', 'td-link'); ?>" />
                    </p>
                </form>
                <?php break;
        endswitch; ?>
    </div>
</div>

<style>
/* General styling */
.td-settings-content {
    margin-top: 20px;
}

.td-settings-container {
    max-width: 1200px;
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.td-settings-section {
    margin: 20px 0 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.td-settings-section:last-child {
    border-bottom: none;
}

.td-settings-section label {
    display: block;
    margin: 5px 0;
    padding: 5px 0;
}

.td-settings-section h3 {
    margin-bottom: 10px;
    font-size: 1.1em;
    font-weight: 600;
}

/* Form table styling */
.form-table th {
    width: 250px;
    font-weight: 600;
}

.form-table td {
    padding: 15px 10px;
}

.form-table .description {
    margin-top: 5px;
}

/* Section management table */
.td-sections-table {
    margin-top: 15px;
}

.td-sections-table th {
    font-weight: 600;
}

.default-section-notice {
    color: #999;
    font-style: italic;
}

.add-new-section {
    padding: 15px;
    background: #f9f9f9;
}

.new-section-container {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.new-section-description {
    background: #f0f0f0;
    padding: 10px 15px;
    border-radius: 4px;
    margin-top: 10px;
}

.new-section-description ul {
    list-style: disc;
    margin-left: 20px;
}

/* Helper section styling */
.helper-section {
    background-color: #f0f7ff;
}

.helper-badge {
    display: inline-block;
    background-color: #2271b1;
    color: #fff;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.section-badge {
    display: inline-block;
    background-color: #646970;
    color: #fff;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.helper-description {
    font-size: 12px;
    color: #646970;
    margin-top: 5px;
    font-style: italic;
}

/* Parameter list styling */
.parameter-list {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    margin-top: 8px;
    padding: 8px 8px 8px 25px;
    max-height: 150px;
    overflow-y: auto;
}

.parameter-list li {
    margin-bottom: 3px;
    font-size: 12px;
}

.toggle-parameters {
    margin-left: 8px;
    text-decoration: none;
}

/* Disabled option styling */
.disabled-option {
    opacity: 0.6;
}

/* Responsive adjustments */
@media screen and (max-width: 782px) {
    .nav-tab-wrapper {
        padding: 0;
    }
    
    .form-table th {
        width: auto;
        padding-bottom: 0;
    }
    
    .td-sections-table {
        font-size: 14px;
    }
    
    .new-section-container {
        flex-wrap: wrap;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle parameter list visibility
    $('.toggle-parameters').on('click', function(e) {
        e.preventDefault();
        
        // Toggle the parameter list
        var $paramList = $(this).siblings('.parameter-list');
        $paramList.slideToggle(200);
        
        // Update the toggle text
        if ($paramList.is(':visible')) {
            $(this).text('<?php _e('Hide', 'td-link'); ?>');
        } else {
            $(this).text('<?php _e('View', 'td-link'); ?>');
        }
    });
    
    // Helper toggle in add new section
    $('#new_section_type').on('change', function() {
        // Highlight the description based on selection
        if ($(this).val() === 'helper') {
            $('.new-section-description li:eq(1)').css({
                'background-color': '#f0f7ff',
                'padding': '3px 5px'
            });
            $('.new-section-description li:eq(0)').css({
                'background-color': 'transparent',
                'padding': '0'
            });
        } else {
            $('.new-section-description li:eq(0)').css({
                'background-color': '#f0f7ff',
                'padding': '3px 5px'
            });
            $('.new-section-description li:eq(1)').css({
                'background-color': 'transparent',
                'padding': '0'
            });
        }
    });
    
    // Initialize helper highlight
    $('#new_section_type').trigger('change');
});
</script>