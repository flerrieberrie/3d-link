<?php
/**
 * Universal Parameter Parser for PolygonJS HTML Snippets
 * 
 * This class automatically extracts parameter information from HTML snippets
 * and creates universal parameter mappings that work with any PolygonJS scene
 * without requiring hardcoded backend structures.
 */

defined('ABSPATH') || exit;

class TD_Universal_Parameter_Parser {
    
    /**
     * Parse HTML snippet and extract parameter information
     * 
     * @param string $html_snippet The HTML snippet from PolygonJS
     * @return array Parsed parameter information
     */
    public static function parse_html_snippet($html_snippet) {
        if (empty($html_snippet)) {
            return false;
        }

        // Initialize result array
        $parsed = [
            'node_id' => '',
            'node_path' => '',
            'param_name' => '',
            'control_type' => 'number',
            'min' => '',
            'max' => '',
            'step' => '',
            'value' => '',
            'input_type' => 'number',
            'label_text' => '',
            'suggested_display_name' => ''
        ];

        // Extract input element attributes using regex
        if (preg_match('/<input[^>]*>/i', $html_snippet, $input_matches)) {
            $input_tag = $input_matches[0];
            
            // Extract id attribute (this is the key to everything)
            if (preg_match('/id=[\'"]([^\'"]+)[\'"]/', $input_tag, $id_matches)) {
                $parsed['node_id'] = $id_matches[1];
                
                // Parse the node path from the ID
                $path_info = self::parse_node_id($id_matches[1]);
                $parsed['node_path'] = $path_info['node_path'];
                $parsed['param_name'] = $path_info['param_name'];
                $parsed['suggested_display_name'] = $path_info['display_name'];
            }
            
            // Extract input type
            if (preg_match('/type=[\'"]([^\'"]+)[\'"]/', $input_tag, $type_matches)) {
                $parsed['input_type'] = $type_matches[1];
                $parsed['control_type'] = self::determine_control_type($type_matches[1]);
            }
            
            // Extract numeric attributes
            if (preg_match('/min=[\'"]?([^\'">\s]+)[\'"]?/', $input_tag, $min_matches)) {
                $parsed['min'] = $min_matches[1];
            }
            
            if (preg_match('/max=[\'"]?([^\'">\s]+)[\'"]?/', $input_tag, $max_matches)) {
                $parsed['max'] = $max_matches[1];
            }
            
            if (preg_match('/step=[\'"]?([^\'">\s]+)[\'"]?/', $input_tag, $step_matches)) {
                $parsed['step'] = $step_matches[1];
            }
            
            if (preg_match('/value=[\'"]?([^\'">\s]+)[\'"]?/', $input_tag, $value_matches)) {
                $parsed['value'] = $value_matches[1];
            }
        }

        // Extract label text
        if (preg_match('/<label[^>]*for=[\'"]([^\'"]+)[\'"][^>]*>([^<]*)<\/label>/i', $html_snippet, $label_matches)) {
            $parsed['label_text'] = trim($label_matches[2]);
            
            // If no display name was generated from ID, use label text
            if (empty($parsed['suggested_display_name'])) {
                $parsed['suggested_display_name'] = self::humanize_string($parsed['label_text']);
            }
        }

        // Final validation
        if (empty($parsed['node_id'])) {
            return false;
        }

        return $parsed;
    }

    /**
     * Parse node ID to extract path and parameter information
     * 
     * Examples:
     * - sleutelhoes-CTRL-height → path: /sleutelhoes/CTRL, param: height
     * - doos-ctrl_doos-breedte → path: /doos/ctrl_doos, param: breedte  
     * - geo1-MAT-meshStandard1-colorr → path: /geo1/MAT/meshStandard1, param: colorr
     * 
     * @param string $node_id The element ID from HTML
     * @return array Parsed path information
     */
    public static function parse_node_id($node_id) {
        $parts = explode('-', $node_id);
        
        if (count($parts) < 2) {
            return [
                'node_path' => '/' . $node_id,
                'param_name' => 'value',
                'display_name' => self::humanize_string($node_id)
            ];
        }

        // Last part is typically the parameter name
        $param_name = array_pop($parts);
        
        // Join remaining parts to form the node path
        $node_path = '/' . implode('/', $parts);
        
        // Generate a human-readable display name
        $display_name = self::generate_display_name($parts, $param_name);

        return [
            'node_path' => $node_path,
            'param_name' => $param_name,
            'display_name' => $display_name
        ];
    }

    /**
     * Generate a human-readable display name from path parts and parameter name
     * 
     * @param array $path_parts The node path parts
     * @param string $param_name The parameter name
     * @return string Human-readable display name
     */
    private static function generate_display_name($path_parts, $param_name) {
        $scene_name = isset($path_parts[0]) ? $path_parts[0] : '';
        
        // Map common parameter names to human readable names
        $param_mappings = [
            'width' => 'Width',
            'height' => 'Height', 
            'length' => 'Length',
            'breedte' => 'Width',
            'hoogte' => 'Height',
            'diepte' => 'Depth',
            'dikte_wanden' => 'Wall Thickness',
            'dikte_bodem' => 'Bottom Thickness',
            'tekst' => 'Text',
            'tekst_schaal' => 'Text Scale',
            'colorr' => 'Color (Red)',
            'colorg' => 'Color (Green)', 
            'colorb' => 'Color (Blue)',
            'radius' => 'Radius',
            'deksel_dikte' => 'Lid Thickness',
            'deksel_op_af' => 'Lid On/Off'
        ];

        // Use mapped name if available, otherwise humanize the parameter name
        $param_display = isset($param_mappings[$param_name]) 
            ? $param_mappings[$param_name] 
            : self::humanize_string($param_name);

        // For color components, just return the color part
        if (in_array($param_name, ['colorr', 'colorg', 'colorb'])) {
            return $param_display;
        }

        // For other parameters, prefix with product name if it makes sense
        $scene_display = self::humanize_string($scene_name);
        
        // Don't duplicate if the parameter already contains the scene name
        if (stripos($param_display, $scene_display) !== false) {
            return $param_display;
        }

        return $param_display;
    }

    /**
     * Convert a string to human-readable format
     * 
     * @param string $string The string to humanize
     * @return string Humanized string
     */
    private static function humanize_string($string) {
        // Replace underscores and dashes with spaces
        $string = str_replace(['_', '-'], ' ', $string);
        
        // Convert to title case
        $string = ucwords(strtolower($string));
        
        // Clean up multiple spaces
        $string = preg_replace('/\s+/', ' ', $string);
        
        return trim($string);
    }

    /**
     * Determine appropriate control type based on input type and attributes
     * 
     * @param string $input_type The HTML input type
     * @return string The control type for the parameter system
     */
    private static function determine_control_type($input_type) {
        switch (strtolower($input_type)) {
            case 'number':
            case 'range':
                return 'number';
            case 'text':
                return 'text';
            case 'checkbox':
                return 'checkbox';
            case 'color':
                return 'color';
            default:
                return 'number';
        }
    }

    /**
     * Auto-populate parameter settings from parsed HTML snippet
     * 
     * @param array $existing_param Existing parameter data
     * @param string $html_snippet HTML snippet to parse
     * @return array Updated parameter data
     */
    public static function auto_populate_parameter($existing_param, $html_snippet) {
        $parsed = self::parse_html_snippet($html_snippet);
        
        if (!$parsed) {
            return $existing_param;
        }

        // Only auto-populate empty fields to avoid overwriting user customizations
        $updated = $existing_param;
        
        if (empty($updated['node_id'])) {
            $updated['node_id'] = $parsed['node_id'];
        }
        
        if (empty($updated['display_name'])) {
            $updated['display_name'] = $parsed['suggested_display_name'];
        }
        
        if (empty($updated['control_type'])) {
            $updated['control_type'] = $parsed['control_type'];
        }
        
        if (empty($updated['default_value'])) {
            $updated['default_value'] = $parsed['value'];
        }
        
        if (empty($updated['min']) && !empty($parsed['min'])) {
            $updated['min'] = $parsed['min'];
        }
        
        if (empty($updated['max']) && !empty($parsed['max'])) {
            $updated['max'] = $parsed['max'];
        }
        
        if (empty($updated['step']) && !empty($parsed['step'])) {
            $updated['step'] = $parsed['step'];
        }

        return $updated;
    }

    /**
     * Generate dynamic JavaScript controls based on parsed parameters
     * 
     * @param array $parameters Array of parameter data
     * @return string JavaScript code for dynamic controls
     */
    public static function generate_dynamic_controls_js($parameters) {
        if (empty($parameters)) {
            return '';
        }

        $js_code = "// Auto-generated dynamic controls\n";
        $js_code .= "function setupDynamicControls(scene) {\n";
        $js_code .= "    console.log('[Dynamic Controls] Setting up controls for scene...');\n\n";

        foreach ($parameters as $param) {
            if (empty($param['node_id']) || empty($param['html_snippet'])) {
                continue;
            }

            $parsed = self::parse_html_snippet($param['html_snippet']);
            if (!$parsed) {
                continue;
            }

            $element_id = $parsed['node_id'];
            $node_path = $parsed['node_path'];
            $param_name = $parsed['param_name'];
            $control_type = $parsed['control_type'];

            // Generate appropriate event listener based on control type
            $js_code .= "    // Control for: {$param['display_name']}\n";
            $js_code .= "    document.getElementById('{$element_id}')?.addEventListener('input', function(event){\n";

            // Handle different parameter types
            if ($control_type === 'checkbox') {
                $js_code .= "        const value = event.target.checked ? 1 : 0;\n";
            } elseif ($control_type === 'number') {
                $js_code .= "        const value = parseFloat(event.target.value);\n";
            } else {
                $js_code .= "        const value = event.target.value;\n";
            }

            $js_code .= "        scene.node('{$node_path}').p.{$param_name}.set(value);\n";
            $js_code .= "    });\n\n";
        }

        $js_code .= "}\n\n";
        $js_code .= "// Export for use in main.js\n";
        $js_code .= "window.setupDynamicControls = setupDynamicControls;\n";

        return $js_code;
    }

    /**
     * Create universal parameter mapping for frontend/backend communication
     * 
     * @param array $parameters Array of parameter data
     * @return array Universal mapping structure
     */
    public static function create_universal_mapping($parameters) {
        $mapping = [
            'parameters' => [],
            'node_mappings' => [],
            'color_mappings' => [],
            'rgb_groups' => []
        ];

        // First pass: collect RGB groups
        $rgb_groups = [];
        foreach ($parameters as $index => $param) {
            if (!empty($param['rgb_group']) && !empty($param['is_rgb_component'])) {
                $rgb_group = $param['rgb_group'];
                $component = $param['is_rgb_component'];
                
                if (!isset($rgb_groups[$rgb_group])) {
                    $rgb_groups[$rgb_group] = [
                        'display_name' => $param['display_name'] ?? 'Color',
                        'components' => []
                    ];
                }
                
                $rgb_groups[$rgb_group]['components'][$component] = $param;
            }
        }

        foreach ($parameters as $index => $param) {
            if (empty($param['html_snippet'])) {
                continue;
            }

            $parsed = self::parse_html_snippet($param['html_snippet']);
            if (!$parsed) {
                continue;
            }

            $param_key = $param['node_id'] ?? $parsed['node_id'];
            
            // Basic parameter mapping with enhanced path normalization
            // Use the actual parameter control type from the product data, not the parsed default
            $control_type = $param['control_type'] ?? $parsed['control_type'];
            
            $mapping_data = [
                'path' => self::normalize_node_path($parsed['node_path']),
                'param' => $parsed['param_name'],
                'type' => $control_type,
                'default_value' => $param['default_value'] ?? $parsed['value']
            ];
            
            // Create mapping for multiple key variations to handle case mismatches
            $key_variations = [
                $param_key,
                strtolower($param_key),
                str_replace('-', '_', $param_key),
                str_replace('_', '-', $param_key)
            ];
            
            foreach ($key_variations as $key) {
                if (!empty($key)) {
                    $mapping['node_mappings'][$key] = $mapping_data;
                }
            }

            // Enhanced color parameter handling
            if ($param['control_type'] === 'color' || 
                in_array($parsed['param_name'], ['colorr', 'colorg', 'colorb'])) {
                
                // Check if this is part of an RGB group
                if (!empty($param['rgb_group'])) {
                    $rgb_group = $param['rgb_group'];
                    $component = $param['is_rgb_component'] ?? 'r';
                    
                    if (!isset($mapping['color_mappings'][$rgb_group])) {
                        $mapping['color_mappings'][$rgb_group] = [
                            'is_rgb_group' => true,
                            'display_name' => $param['display_name'] ?? 'Color',
                            'components' => []
                        ];
                    }
                    
                    // Store the complete node path for each component
                    $mapping['color_mappings'][$rgb_group]['components'][$component] = [
                        'path' => self::normalize_node_path($parsed['node_path']),
                        'param' => $parsed['param_name'],
                        'node_id' => $param_key
                    ];
                    
                    // Also add to rgb_groups for frontend compatibility
                    if (!isset($mapping['rgb_groups'][$rgb_group])) {
                        $mapping['rgb_groups'][$rgb_group] = [
                            'display_name' => $param['display_name'] ?? 'Color',
                            'components' => []
                        ];
                    }
                    
                    $mapping['rgb_groups'][$rgb_group]['components'][$component] = $param_key;
                } else {
                    // Single color parameter (not RGB group)
                    $mapping['color_mappings'][$param_key] = [
                        'is_rgb_group' => false,
                        'path' => self::normalize_node_path($parsed['node_path']),
                        'param' => $parsed['param_name'],
                        'type' => 'single_color'
                    ];
                }
            }

            // Store the parameter data with enhanced information
            $mapping['parameters'][$param_key] = [
                'display_name' => $param['display_name'] ?? $parsed['suggested_display_name'],
                'control_type' => $param['control_type'] ?? $parsed['control_type'],
                'default_value' => $param['default_value'] ?? $parsed['value'],
                'node_path' => self::normalize_node_path($parsed['node_path']),
                'param_name' => $parsed['param_name'],
                'html_snippet' => $param['html_snippet'],
                'section' => $param['section'] ?? '',
                'min' => $param['min'] ?? '',
                'max' => $param['max'] ?? '',
                'step' => $param['step'] ?? '',
                'is_rgb_component' => $param['is_rgb_component'] ?? '',
                'rgb_group' => $param['rgb_group'] ?? ''
            ];
        }

        return $mapping;
    }
    
    /**
     * Normalize node path for consistent handling
     * 
     * @param string $path Raw node path
     * @return string Normalized path
     */
    public static function normalize_node_path($path) {
        if (empty($path)) {
            return '';
        }
        
        // Ensure path starts with /
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }
        
        // Handle common case variations for known scenes
        $path_fixes = [
            '/doos/mat/' => '/doos/MAT/',
            '/geo1/mat/' => '/geo1/MAT/',
            '/geo2/mat/' => '/geo2/MAT/',
            '/sleutelhoes/mat/' => '/sleutelhoes/MAT/',
            '/colorbox' => '/colorBox',
            '/colorlid' => '/colorLid',
            '/colortekst' => '/colorTekst'
        ];
        
        foreach ($path_fixes as $search => $replace) {
            if (stripos($path, $search) !== false) {
                $path = str_ireplace($search, $replace, $path);
            }
        }
        
        return $path;
    }

    /**
     * Validate parameter structure and suggest fixes
     * 
     * @param array $parameters Array of parameter data
     * @return array Validation results with suggestions
     */
    public static function validate_parameters($parameters) {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'suggestions' => []
        ];

        foreach ($parameters as $index => $param) {
            $param_name = $param['display_name'] ?? "Parameter #" . ($index + 1);
            
            // Check for required fields
            if (empty($param['html_snippet'])) {
                $results['errors'][] = "{$param_name}: Missing HTML snippet";
                $results['valid'] = false;
                continue;
            }

            // Try to parse the HTML snippet
            $parsed = self::parse_html_snippet($param['html_snippet']);
            if (!$parsed) {
                $results['errors'][] = "{$param_name}: Invalid HTML snippet format";
                $results['valid'] = false;
                continue;
            }

            // Check for potential improvements
            if (empty($param['display_name'])) {
                $results['suggestions'][] = "{$param_name}: Consider setting a custom display name (suggested: {$parsed['suggested_display_name']})";
            }

            if (empty($param['section'])) {
                $results['suggestions'][] = "{$param_name}: Consider assigning to a section for better organization";
            }

            // Validate numeric parameters
            if (in_array($param['control_type'] ?? '', ['number', 'slider'])) {
                if (empty($param['min']) || empty($param['max'])) {
                    $results['warnings'][] = "{$param_name}: Numeric parameter should have min/max values set";
                }
            }
        }

        return $results;
    }
}