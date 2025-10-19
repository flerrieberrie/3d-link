<?php
/**
 * Universal Frontend Handler
 * 
 * Handles frontend display and interaction for any PolygonJS parameter structure
 * without requiring hardcoded backend implementations
 */

defined('ABSPATH') || exit;

class TD_Universal_Frontend_Handler {
    
    /**
     * Generate universal parameter update JavaScript
     * 
     * @param int $product_id Product ID
     * @return string JavaScript code for parameter handling
     */
    public static function generate_parameter_update_js($product_id) {
        $parameters_manager = new TD_Parameters_Manager();
        $mapping = $parameters_manager->get_universal_mapping($product_id);
        
        if (empty($mapping['node_mappings'])) {
            return '';
        }

        ob_start();
        ?>
        <script>
        // Universal Parameter Handler - Auto-generated
        (function() {
            const productId = <?php echo json_encode((string)$product_id); ?>;
            const productMapping = <?php echo json_encode($mapping); ?>;
            
            // Store the mapping globally for debugging and frontend use
            window['tdUniversalMapping_' + productId] = productMapping;
            window.tdUniversalMapping = productMapping;
            
            // Make RGB groups available globally for frontend customizer
            if (productMapping.rgb_groups) {
                window.tdPolygonjs = window.tdPolygonjs || {};
                window.tdPolygonjs.rgbGroups = productMapping.rgb_groups;
            }
            
            /**
             * Initialize universal parameter controls
             */
            function initUniversalControls() {
                console.log('[Universal Controls] Initializing for product', productId);
                console.log('[Universal Controls] Mapping data:', productMapping);
                
                // Wait for PolygonJS scene to be ready
                const checkScene = () => {
                    if (window.polyScene) {
                        setupParameterControls(window.polyScene);
                    } else {
                        setTimeout(checkScene, 100);
                    }
                };
                checkScene();
            }
            
            /**
             * Set up parameter controls for the scene
             */
            function setupParameterControls(scene) {
                console.log('[Universal Controls] Setting up controls with mapping:', productMapping);
                
                // Set up individual parameter controls (non-color)
                Object.keys(productMapping.node_mappings).forEach(paramKey => {
                    const mapping = productMapping.node_mappings[paramKey];
                    const element = document.getElementById(paramKey);
                    
                    if (!element) {
                        // Try to find element in container with data-node-id
                        const container = document.querySelector(`[data-node-id="${paramKey}"]`);
                        if (!container) {
                            console.warn('[Universal Controls] Element and container not found:', paramKey);
                            return;
                        }
                        
                        // Find the actual control within the container
                        const control = container.querySelector('.polygonjs-control, input, select, textarea');
                        if (!control) {
                            console.warn('[Universal Controls] No control found in container:', paramKey);
                            return;
                        }
                        
                        setupParameterControl(scene, control, mapping, paramKey);
                    } else {
                        setupParameterControl(scene, element, mapping, paramKey);
                    }
                });
                
                // Set up enhanced color controls with universal mapping
                setupUniversalColorControls(scene);
                
                console.log('[Universal Controls] Setup complete');
            }
            
            /**
             * Set up a single parameter control
             */
            function setupParameterControl(scene, element, mapping, paramKey) {
                // Skip color controls - they're handled separately
                if (mapping.type === 'color') {
                    return;
                }
                
                // Determine the appropriate event type
                const eventType = (element.type === 'range' || element.classList.contains('visible-slider')) ? 'input' : 'change';
                
                element.addEventListener(eventType, function(event) {
                    let value = event.target.value;
                    
                    // Type conversion based on mapping
                    if (mapping.type === 'number') {
                        value = parseFloat(value);
                    } else if (mapping.type === 'checkbox') {
                        value = event.target.checked ? 1 : 0;
                    }
                    
                    // Update the scene parameter
                    updateUniversalParameter(scene, mapping.path, mapping.param, value, paramKey);
                });
            }
            
            /**
             * Set up universal color controls
             */
            function setupUniversalColorControls(scene) {
                console.log('[Universal Controls] Setting up color controls');
                
                // Handle RGB groups
                Object.keys(productMapping.color_mappings || {}).forEach(colorKey => {
                    const colorMapping = productMapping.color_mappings[colorKey];
                    
                    if (colorMapping.is_rgb_group && colorMapping.components) {
                        setupUniversalRGBColorControl(scene, colorKey, colorMapping);
                    } else if (!colorMapping.is_rgb_group) {
                        // Single color control
                        setupUniversalSingleColorControl(scene, colorKey, colorMapping);
                    }
                });
            }
            
            /**
             * Set up universal RGB color control
             */
            function setupUniversalRGBColorControl(scene, colorGroupId, colorMapping) {
                console.log('[Universal Controls] Setting up RGB color control for group:', colorGroupId);
                
                // Find the main color control (usually the R component)
                const rComponent = colorMapping.components.r;
                if (!rComponent || !rComponent.node_id) {
                    console.warn('[Universal Controls] No R component found for RGB group:', colorGroupId);
                    return;
                }
                
                // Find the color container by data-node-id
                const container = document.querySelector(`[data-node-id="${rComponent.node_id}"]`);
                if (!container) {
                    console.warn('[Universal Controls] Container not found for RGB component:', rComponent.node_id);
                    return;
                }
                
                // Set up event listener for color changes
                container.addEventListener('td_color_changed', function(event) {
                    const colorData = event.detail;
                    handleUniversalRGBColorChange(scene, colorMapping, colorData);
                });
                
                // Set up click handlers for color swatches
                const colorRadios = container.querySelectorAll('.color-radio');
                colorRadios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.checked) {
                            const colorKey = this.value;
                            const colorName = this.closest('.color-swatch').querySelector('.swatch-name')?.textContent;
                            
                            // Get RGB values from global color options
                            let rgbValues = null;
                            if (window.tdPolygonjs && window.tdPolygonjs.colorOptions && window.tdPolygonjs.colorOptions[colorKey]) {
                                rgbValues = window.tdPolygonjs.colorOptions[colorKey].rgb;
                            }
                            
                            if (rgbValues && rgbValues.length === 3) {
                                handleUniversalRGBColorChange(scene, colorMapping, { rgb: rgbValues, colorKey, colorName });
                            }
                        }
                    });
                });
            }
            
            /**
             * Handle universal RGB color changes
             */
            function handleUniversalRGBColorChange(scene, colorMapping, colorData) {
                if (!colorData || !colorData.rgb || colorData.rgb.length !== 3) {
                    console.warn('[Universal Controls] Invalid RGB color data:', colorData);
                    return;
                }
                
                const rgb = colorData.rgb;
                console.log('[Universal Controls] Applying RGB color:', rgb);
                
                // Update R component
                if (colorMapping.components.r) {
                    updateUniversalParameter(scene, colorMapping.components.r.path, colorMapping.components.r.param, rgb[0], 'colorr');
                }
                
                // Update G component
                if (colorMapping.components.g) {
                    updateUniversalParameter(scene, colorMapping.components.g.path, colorMapping.components.g.param, rgb[1], 'colorg');
                }
                
                // Update B component
                if (colorMapping.components.b) {
                    updateUniversalParameter(scene, colorMapping.components.b.path, colorMapping.components.b.param, rgb[2], 'colorb');
                }
            }
            
            /**
             * Update a universal parameter with error handling
             */
            function updateUniversalParameter(scene, path, param, value, debugId) {
                try {
                    const node = scene.node(path);
                    if (node && node.p && node.p[param]) {
                        node.p[param].set(value);
                        console.log(`[Universal Controls] ✅ Updated: ${path}.${param} = ${value}`, debugId ? `(${debugId})` : '');
                    } else {
                        console.warn(`[Universal Controls] ❌ Parameter not found: ${path}.${param}`, debugId ? `(${debugId})` : '');
                        
                        // Try alternative parameter names
                        const alternatives = ['value', 'input', 'index'];
                        for (const alt of alternatives) {
                            if (node && node.p && node.p[alt]) {
                                node.p[alt].set(value);
                                console.log(`[Universal Controls] ✅ Updated with alternative: ${path}.${alt} = ${value}`, debugId ? `(${debugId})` : '');
                                return;
                            }
                        }
                    }
                } catch (error) {
                    console.error('[Universal Controls] Error updating parameter:', error, { path, param, value, debugId });
                }
            }
            
            /**
             * Set up single color control (non-RGB)
             */
            function setupUniversalSingleColorControl(scene, colorKey, colorMapping) {
                console.log('[Universal Controls] Setting up single color control:', colorKey);
                
                // Find the color container
                const container = document.querySelector(`[data-node-id="${colorKey}"]`);
                if (!container) {
                    console.warn('[Universal Controls] Container not found for single color:', colorKey);
                    return;
                }
                
                // Set up click handlers for color swatches
                const colorRadios = container.querySelectorAll('.color-radio');
                colorRadios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.checked) {
                            const colorValue = this.value;
                            
                            // For single color controls, update the parameter directly
                            updateUniversalParameter(scene, colorMapping.path, colorMapping.param, colorValue, colorKey);
                        }
                    });
                });
            }
            
            /**
             * Universal parameter update function for external use
             */
            function updateParameter(paramKey, value) {
                const mapping = productMapping.node_mappings[paramKey];
                if (!mapping || !window.polyScene) {
                    console.warn('[Universal Controls] Cannot update parameter:', paramKey);
                    return false;
                }
                
                try {
                    const node = window.polyScene.node(mapping.path);
                    if (node && node.p && node.p[mapping.param]) {
                        node.p[mapping.param].set(value);
                        console.log('[Universal Controls] Externally updated:', mapping.path + '.' + mapping.param + ' = ' + value);
                        return true;
                    }
                } catch (error) {
                    console.error('[Universal Controls] Error updating parameter:', error);
                }
                
                return false;
            }
            
            /**
             * Get current parameter values
             */
            function getParameterValues() {
                const values = {};
                
                if (!window.polyScene) {
                    return values;
                }
                
                Object.keys(productMapping.node_mappings).forEach(paramKey => {
                    const mapping = productMapping.node_mappings[paramKey];
                    
                    try {
                        const node = window.polyScene.node(mapping.path);
                        if (node && node.p && node.p[mapping.param]) {
                            values[paramKey] = node.p[mapping.param].value;
                        }
                    } catch (error) {
                        console.warn('[Universal Controls] Cannot get value for:', paramKey);
                    }
                });
                
                return values;
            }
            
            // Expose API globally
            window.tdUniversalControls_<?php echo $product_id; ?> = {
                init: initUniversalControls,
                updateParameter: updateParameter,
                getValues: getParameterValues,
                mapping: productMapping
            };
            
            // Auto-initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initUniversalControls);
            } else {
                initUniversalControls();
            }
            
            // Also initialize when page loads completely
            window.addEventListener('load', initUniversalControls);
            
            // Initialize when scene is ready (fallback)
            setTimeout(initUniversalControls, 1000);
            
        })();
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate validation report for admin
     * 
     * @param int $product_id Product ID
     * @return string HTML validation report
     */
    public static function generate_validation_report($product_id) {
        $parameters_manager = new TD_Parameters_Manager();
        $validation = $parameters_manager->validate_product_parameters($product_id);
        
        if ($validation['valid'] && empty($validation['warnings']) && empty($validation['suggestions'])) {
            return '<div class="notice notice-success"><p><strong>✅ All parameters are valid and properly configured.</strong></p></div>';
        }
        
        ob_start();
        ?>
        <div class="td-validation-report">
            <?php if (!empty($validation['errors'])): ?>
                <div class="notice notice-error">
                    <h4>❌ Errors (must be fixed)</h4>
                    <ul>
                        <?php foreach ($validation['errors'] as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($validation['warnings'])): ?>
                <div class="notice notice-warning">
                    <h4>⚠️ Warnings (recommended fixes)</h4>
                    <ul>
                        <?php foreach ($validation['warnings'] as $warning): ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($validation['suggestions'])): ?>
                <div class="notice notice-info">
                    <h4>💡 Suggestions (optional improvements)</h4>
                    <ul>
                        <?php foreach ($validation['suggestions'] as $suggestion): ?>
                            <li><?php echo esc_html($suggestion); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Inject universal controls into product pages
     * 
     * @param int $product_id Product ID
     */
    public static function inject_universal_controls($product_id) {
        // Only inject if PolygonJS is enabled
        if (get_post_meta($product_id, '_enable_polygonjs', true) !== 'yes') {
            return;
        }
        
        // Generate and output the JavaScript
        echo self::generate_parameter_update_js($product_id);
        
        // Add debug information if requested
        if (isset($_GET['debug']) && $_GET['debug'] === 'universal') {
            $parameters_manager = new TD_Parameters_Manager();
            $mapping = $parameters_manager->get_universal_mapping($product_id);
            
            echo '<div style="position:fixed; top:10px; right:10px; z-index:99999; background:white; border:2px solid #333; padding:10px; font-size:12px; max-width:400px; box-shadow:0 0 10px rgba(0,0,0,0.3); max-height:80vh; overflow-y:auto;">';
            echo '<h4 style="margin:0 0 10px 0;">Universal Parameter Debug</h4>';
            echo '<strong>Product ID:</strong> ' . $product_id . '<br>';
            echo '<strong>Parameters:</strong> ' . count($mapping['parameters']) . '<br>';
            echo '<strong>Node Mappings:</strong> ' . count($mapping['node_mappings']) . '<br>';
            echo '<strong>Color Mappings:</strong> ' . count($mapping['color_mappings']) . '<br>';
            echo '<details style="margin-top:10px;"><summary>View Mapping Data</summary>';
            echo '<pre style="font-size:10px; max-height:200px; overflow-y:auto; background:#f5f5f5; padding:5px; margin-top:5px;">';
            echo esc_html(json_encode($mapping, JSON_PRETTY_PRINT));
            echo '</pre></details>';
            echo '<button onclick="console.log(\'Universal Mapping:\', window.tdUniversalMapping_' . $product_id . ');">Log to Console</button>';
            echo '<button onclick="this.parentNode.style.display = \'none\';" style="margin-left:5px;">Close</button>';
            echo '</div>';
        }
    }
}