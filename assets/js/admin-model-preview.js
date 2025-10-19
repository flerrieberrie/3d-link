/**
 * Admin Model Preview JS
 * 
 * Handles communication between the admin interface and PolygonJS iframes
 * for displaying customer model customizations
 */

(function() {
    /**
     * Listen for messages from the parent window
     */
    window.addEventListener('message', function(event) {
        // Verify message structure
        if (!event.data || typeof event.data !== 'object' || !event.data.type) {
            return;
        }
        
        // Handle universal mapping setup
        if (event.data.type === 'td_set_universal_mapping' && event.data.mapping) {
            console.log('Received universal mapping from admin:', event.data.mapping);
            window.tdUniversalMapping = event.data.mapping;
        }
        
        // Handle parameter application request
        if (event.data.type === 'td_apply_parameters' && event.data.parameters) {
            // Store universal mapping if provided
            if (event.data.universalMapping) {
                window.tdUniversalMapping = event.data.universalMapping;
                console.log('Updated universal mapping from parameter application:', event.data.universalMapping);
            }
            applyCustomerParameters(event.data.parameters);
        }
        
        // Handle camera rotation request
        if (event.data.type === 'rotate_camera' && event.data.rotation) {
            rotateCamera(event.data.rotation);
        }
        
        // Handle camera reset request
        if (event.data.type === 'reset_camera') {
            resetCamera();
        }
        
        // Handle download request
        if (event.data.type === 'download_model') {
            downloadModel(event.data.format, event.data.filename);
        }
    });
    
    /**
     * Apply customer parameters to the PolygonJS scene using universal parameter mapping
     */
    function applyCustomerParameters(parameters) {
        // Make sure PolygonJS is loaded
        if (!window.polygonjs || !window.polygonjs.scene) {
            console.error('PolygonJS not initialized');
            return;
        }
        
        // Get the scene
        const scene = window.polygonjs.scene;
        console.log('Applying parameters to scene using universal mapping:', parameters);
        
        // Check if we have universal mapping data
        if (window.tdUniversalMapping && window.tdUniversalMapping.node_mappings) {
            console.log('Using universal mapping for parameter application');
            applyParametersWithUniversalMapping(scene, parameters, window.tdUniversalMapping);
        } else {
            console.log('No universal mapping found, falling back to legacy method');
            applyParametersLegacyMethod(scene, parameters);
        }
        
        // Force scene update
        scene.processUpdateNodeConnections();
        
        // Confirm parameters were applied
        console.log('All parameters applied to scene');
        
        // Additional scene update for good measure
        setTimeout(() => {
            if (scene.root && typeof scene.root().compute === 'function') {
                try {
                    scene.root().compute();
                    console.log('Scene recomputed after parameter application');
                } catch (e) {
                    console.error('Error computing scene:', e);
                }
            }
        }, 500);
    }
    
    /**
     * Apply parameters using universal mapping data
     */
    function applyParametersWithUniversalMapping(scene, parameters, universalMapping) {
        const nodeMapping = universalMapping.node_mappings;
        const colorMapping = universalMapping.color_mappings || {};
        
        console.log('Universal mapping available:', nodeMapping);
        
        // Process each parameter using the universal mapping
        for (const paramId in parameters) {
            if (!parameters.hasOwnProperty(paramId)) continue;
            
            const param = parameters[paramId];
            
            // Skip parameters without values
            if (param.value === undefined || param.value === null) {
                console.log('Skipping parameter without value:', paramId, param);
                continue;
            }
            
            // Check if we have a mapping for this parameter
            const mapping = nodeMapping[paramId] || nodeMapping[param.node_id];
            
            if (!mapping) {
                console.warn('No universal mapping found for parameter:', paramId, param.node_id);
                // Try fallback method for this parameter
                applyParameterLegacyMethod(scene, paramId, param);
                continue;
            }
            
            console.log('Applying parameter using universal mapping:', paramId, mapping, param.value);
            
            try {
                // Use the exact path and parameter name from the mapping
                const node = scene.node(mapping.path);
                if (node && node.p && node.p[mapping.param]) {
                    let value = param.value;
                    
                    // Type conversion based on mapping type
                    if (mapping.type === 'number') {
                        value = parseFloat(value);
                    } else if (mapping.type === 'checkbox') {
                        value = value === 'yes' || value === true || value === 1 || value === '1' ? 1 : 0;
                    }
                    
                    // Apply the parameter
                    node.p[mapping.param].set(value);
                    console.log(`✅ Successfully applied: ${mapping.path}.${mapping.param} = ${value}`);
                } else {
                    console.warn(`❌ Node or parameter not found: ${mapping.path}.${mapping.param}`);
                }
            } catch (error) {
                console.error(`❌ Error applying universal parameter ${paramId}:`, error);
            }
        }
        
        // Handle color groups with RGB mappings
        for (const colorGroupId in colorMapping) {
            const colorGroup = colorMapping[colorGroupId];
            if (colorGroup.is_rgb_group && colorGroup.components) {
                applyRGBColorGroup(scene, parameters, colorGroup);
            }
        }
    }
    
    /**
     * Apply RGB color group using universal mapping
     */
    function applyRGBColorGroup(scene, parameters, colorGroup) {
        console.log('Applying RGB color group:', colorGroup);
        
        // Find the color parameter (usually the R component)
        let colorParam = null;
        let colorValue = null;
        
        // Look for the color parameter in the provided parameters
        for (const paramId in parameters) {
            const param = parameters[paramId];
            if (param.control_type === 'color' && param.rgb_group === colorGroup.id) {
                colorParam = param;
                colorValue = param.value;
                break;
            }
        }
        
        if (!colorParam || !colorValue) {
            console.log('No color parameter found for RGB group:', colorGroup);
            return;
        }
        
        // Convert color value to RGB
        let rgb = null;
        
        // Check if we have color options available
        if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
            const colorKey = colorValue.toLowerCase().replace(/\s+/g, '-');
            if (window.tdPolygonjs.colorOptions[colorKey] && window.tdPolygonjs.colorOptions[colorKey].rgb) {
                rgb = window.tdPolygonjs.colorOptions[colorKey].rgb;
                console.log('Found RGB values from color options:', rgb);
            }
        }
        
        // Fallback: try to parse hex color
        if (!rgb && typeof colorValue === 'string' && colorValue.startsWith('#')) {
            const hexColor = hexToRgb(colorValue);
            if (hexColor) {
                rgb = [hexColor.r / 255, hexColor.g / 255, hexColor.b / 255];
            }
        }
        
        // Fallback: generate color from name
        if (!rgb) {
            const hash = stringToHash(colorValue);
            const r = ((hash & 0xFF0000) >> 16) / 255;
            const g = ((hash & 0x00FF00) >> 8) / 255;
            const b = (hash & 0x0000FF) / 255;
            rgb = [r, g, b];
            console.log('Generated RGB from color name:', colorValue, rgb);
        }
        
        if (rgb) {
            // Apply RGB components
            if (colorGroup.components.r) {
                try {
                    const rNode = scene.node(colorGroup.components.r);
                    if (rNode && rNode.p && rNode.p.colorr) {
                        rNode.p.colorr.set(rgb[0]);
                        console.log(`✅ Set R component: ${colorGroup.components.r}.colorr = ${rgb[0]}`);
                    }
                } catch (e) {
                    console.error('Error setting R component:', e);
                }
            }
            
            if (colorGroup.components.g) {
                try {
                    const gNode = scene.node(colorGroup.components.g);
                    if (gNode && gNode.p && gNode.p.colorg) {
                        gNode.p.colorg.set(rgb[1]);
                        console.log(`✅ Set G component: ${colorGroup.components.g}.colorg = ${rgb[1]}`);
                    }
                } catch (e) {
                    console.error('Error setting G component:', e);
                }
            }
            
            if (colorGroup.components.b) {
                try {
                    const bNode = scene.node(colorGroup.components.b);
                    if (bNode && bNode.p && bNode.p.colorb) {
                        bNode.p.colorb.set(rgb[2]);
                        console.log(`✅ Set B component: ${colorGroup.components.b}.colorb = ${rgb[2]}`);
                    }
                } catch (e) {
                    console.error('Error setting B component:', e);
                }
            }
        }
    }
    
    /**
     * Legacy method for applying parameters (fallback when no universal mapping available)
     */
    function applyParametersLegacyMethod(scene, parameters) {
        console.log('Using legacy parameter application method');
        
        for (const paramId in parameters) {
            if (!parameters.hasOwnProperty(paramId)) continue;
            const param = parameters[paramId];
            
            if (!param.node_id || !param.value) {
                console.log('Skipping parameter without node_id or value:', paramId, param);
                continue;
            }
            
            applyParameterLegacyMethod(scene, param.node_id, param);
        }
    }
    
    /**
     * Apply a single parameter using legacy method
     */
    function applyParameterLegacyMethod(scene, nodeId, param) {
        console.log('Applying parameter with legacy method:', nodeId, param.value, param.control_type);
        
        try {
            // Handle different parameter types
            switch (param.control_type) {
                case 'color':
                    applyColorParameter(scene, nodeId, param);
                    break;
                    
                case 'slider':
                case 'number':
                    applyNumberParameter(scene, nodeId, param);
                    break;
                    
                case 'checkbox':
                    applyBooleanParameter(scene, nodeId, param);
                    break;
                    
                case 'text':
                    applyTextParameter(scene, nodeId, param);
                    break;
                    
                case 'dropdown':
                    applyDropdownParameter(scene, nodeId, param);
                    break;
                
                default:
                    // Default fallback - try as number
                    applyNumberParameter(scene, nodeId, param);
                    break;
            }
        } catch (error) {
            console.error(`Error applying legacy parameter ${nodeId}:`, error);
        }
    }
    
    /**
     * Apply a color parameter to the scene
     */
    function applyColorParameter(scene, nodeId, param) {
        console.log('Applying color parameter:', nodeId, param.value);
        
        // Handle RGB group parameters
        if (param.is_rgb_component && param.rgb_group) {
            console.log('RGB component parameter:', param);
            // This is part of an RGB group, but we'll still try to apply it individually
        }
        
        // Convert color value to RGB
        let color = null;
        
        // Case 1: Value is already a hex color
        if (typeof param.value === 'string' && param.value.startsWith('#')) {
            color = hexToRgb(param.value);
        }
        // Case 2: Value is a color name, try to look up its hex value
        else if (typeof param.value === 'string') {
            // Try to get color information from global colors if available
            if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
                const colorKey = param.value.toLowerCase().replace(/\s+/g, '-');
                if (window.tdPolygonjs.colorOptions[colorKey] && window.tdPolygonjs.colorOptions[colorKey].rgb) {
                    // Use RGB values directly
                    const rgb = window.tdPolygonjs.colorOptions[colorKey].rgb;
                    color = { r: rgb[0] * 255, g: rgb[1] * 255, b: rgb[2] * 255 };
                    console.log('Found color in global colors:', colorKey, color);
                }
            }
            
            // Fallback: Use a hash of the color name to generate a consistent color
            if (!color) {
                const hash = stringToHash(param.value);
                const r = (hash & 0xFF0000) >> 16;
                const g = (hash & 0x00FF00) >> 8;
                const b = hash & 0x0000FF;
                color = { r, g, b };
                console.log('Generated color from name hash:', param.value, color);
            }
        }
        
        if (!color) {
            console.error('Could not convert color value to RGB:', param.value);
            return;
        }
        
        // Create path from node ID
        const nodePath = '/' + nodeId.replace(/-/g, '/');
        console.log('Node path for color:', nodePath);
        
        // Try to set the color parameter
        try {
            const node = scene.node(nodePath);
            if (node) {
                console.log('Found node for color parameter:', nodePath);
                
                // Try different color parameter patterns
                if (node.p.color !== undefined) {
                    node.p.color.set([color.r/255, color.g/255, color.b/255]);
                    console.log('Set color parameter:', [color.r/255, color.g/255, color.b/255]);
                } else if (node.p.colorr !== undefined && node.p.colorg !== undefined && node.p.colorb !== undefined) {
                    node.p.colorr.set(color.r/255);
                    node.p.colorg.set(color.g/255);
                    node.p.colorb.set(color.b/255);
                    console.log('Set RGB color components:', color.r/255, color.g/255, color.b/255);
                }
                // Additional patterns based on Polygonjs naming conventions
                else if (node.p.r !== undefined && node.p.g !== undefined && node.p.b !== undefined) {
                    node.p.r.set(color.r/255);
                    node.p.g.set(color.g/255);
                    node.p.b.set(color.b/255);
                    console.log('Set r,g,b parameters:', color.r/255, color.g/255, color.b/255);
                }
                else if (node.p.diffuse !== undefined) {
                    node.p.diffuse.set([color.r/255, color.g/255, color.b/255]);
                    console.log('Set diffuse color parameter:', [color.r/255, color.g/255, color.b/255]);
                }
                else {
                    console.warn('Could not find color parameters on node:', nodePath);
                }
            } else {
                console.warn('Node not found for color parameter:', nodePath);
            }
        } catch (e) {
            console.error('Error setting color parameter:', e);
        }
    }
    
    /**
     * Generate a consistent hash from a string
     */
    function stringToHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = ((hash << 5) - hash) + str.charCodeAt(i);
            hash = hash & hash; // Convert to 32bit integer
        }
        return Math.abs(hash) % 0xFFFFFF; // Ensure positive and within color range
    }
    
    /**
     * Apply a number parameter to the scene
     */
    function applyNumberParameter(scene, nodeId, param) {
        console.log('Applying number parameter:', nodeId, param.value);
        
        // Create path from node ID
        const nodePath = '/' + nodeId.replace(/-/g, '/');
        console.log('Node path for number:', nodePath);
        
        // Convert value to number
        const value = parseFloat(param.value);
        if (isNaN(value)) {
            console.warn('Invalid number value:', param.value);
            return;
        }
        
        // Try to set the value parameter
        try {
            const node = scene.node(nodePath);
            if (node) {
                console.log('Found node for number parameter:', nodePath);
                
                // Try different parameter names based on common conventions
                if (node.p.value !== undefined) {
                    node.p.value.set(value);
                    console.log('Set value parameter:', value);
                    return true;
                } 
                else if (node.p.val !== undefined) {
                    node.p.val.set(value);
                    console.log('Set val parameter:', value);
                    return true;
                }
                else if (node.p.size !== undefined) {
                    node.p.size.set(value);
                    console.log('Set size parameter:', value);
                    return true;
                }
                else if (node.p.amount !== undefined) {
                    node.p.amount.set(value);
                    console.log('Set amount parameter:', value);
                    return true;
                }
                else if (node.p.scale !== undefined) {
                    // If scale is a Vec3, set all components; if it's a float, set directly
                    if (typeof node.p.scale.set === 'function') {
                        try {
                            node.p.scale.set(value);
                            console.log('Set scale parameter:', value);
                            return true;
                        } catch (e) {
                            console.error('Error setting scale as float, trying as vector:', e);
                            try {
                                node.p.scale.set([value, value, value]);
                                console.log('Set scale parameter as vector:', value);
                                return true;
                            } catch (e2) {
                                console.error('Error setting scale as vector:', e2);
                            }
                        }
                    }
                }
                
                // Try all parameters that might accept numbers
                let paramSet = false;
                for (const paramName in node.p) {
                    if (node.p[paramName] && typeof node.p[paramName].set === 'function') {
                        try {
                            node.p[paramName].set(value);
                            console.log(`Set ${paramName} parameter:`, value);
                            paramSet = true;
                            break;
                        } catch (e) {
                            // Silently continue to next parameter
                        }
                    }
                }
                
                if (!paramSet) {
                    console.warn('Could not find a suitable parameter to set on node:', nodePath);
                }
            } else {
                console.warn('Node not found for number parameter:', nodePath);
            }
        } catch (e) {
            console.error('Error setting number parameter:', e);
        }
    }
    
    /**
     * Apply a boolean parameter to the scene
     */
    function applyBooleanParameter(scene, nodeId, param) {
        console.log('Applying boolean parameter:', nodeId, param.value);
        
        // Create path from node ID
        const nodePath = '/' + nodeId.replace(/-/g, '/');
        console.log('Node path for boolean:', nodePath);
        
        // Convert value to boolean
        const value = param.value === 'yes' || param.value === true || param.value === 1 || param.value === '1';
        
        // Try to set the boolean parameter
        try {
            const node = scene.node(nodePath);
            if (node) {
                console.log('Found node for boolean parameter:', nodePath);
                
                // Try different parameter names
                if (node.p.value !== undefined) {
                    node.p.value.set(value);
                    console.log('Set value parameter:', value);
                    return true;
                } 
                else if (node.p.toggle !== undefined) {
                    node.p.toggle.set(value);
                    console.log('Set toggle parameter:', value);
                    return true;
                }
                else if (node.p.visible !== undefined) {
                    node.p.visible.set(value);
                    console.log('Set visible parameter:', value);
                    return true;
                }
                else if (node.p.enabled !== undefined) {
                    node.p.enabled.set(value);
                    console.log('Set enabled parameter:', value);
                    return true;
                }
                else {
                    console.warn('Could not find boolean parameter on node:', nodePath);
                }
            } else {
                console.warn('Node not found for boolean parameter:', nodePath);
            }
        } catch (e) {
            console.error('Error setting boolean parameter:', e);
        }
    }
    
    /**
     * Apply a text parameter to the scene
     */
    function applyTextParameter(scene, nodeId, param) {
        console.log('Applying text parameter:', nodeId, param.value);
        
        // Create path from node ID
        const nodePath = '/' + nodeId.replace(/-/g, '/');
        console.log('Node path for text:', nodePath);
        
        // Try to set the text parameter
        try {
            const node = scene.node(nodePath);
            if (node) {
                console.log('Found node for text parameter:', nodePath);
                
                // Try different parameter names
                if (node.p.value !== undefined) {
                    node.p.value.set(param.value);
                    console.log('Set value parameter:', param.value);
                    return true;
                } 
                else if (node.p.string !== undefined) {
                    node.p.string.set(param.value);
                    console.log('Set string parameter:', param.value);
                    return true;
                }
                else if (node.p.text !== undefined) {
                    node.p.text.set(param.value);
                    console.log('Set text parameter:', param.value);
                    return true;
                }
                else if (node.p.input !== undefined) {
                    node.p.input.set(param.value);
                    console.log('Set input parameter:', param.value);
                    return true;
                }
                else {
                    console.warn('Could not find text parameter on node:', nodePath);
                }
            } else {
                console.warn('Node not found for text parameter:', nodePath);
            }
        } catch (e) {
            console.error('Error setting text parameter:', e);
        }
    }
    
    /**
     * Apply a dropdown parameter to the scene
     */
    function applyDropdownParameter(scene, nodeId, param) {
        console.log('Applying dropdown parameter:', nodeId, param.value);
        
        // Create path from node ID
        const nodePath = '/' + nodeId.replace(/-/g, '/');
        console.log('Node path for dropdown:', nodePath);
        
        // Try to convert value to number if it looks like one
        let value = param.value;
        if (typeof value === 'string' && !isNaN(parseFloat(value))) {
            value = parseFloat(value);
            console.log('Converted dropdown value to number:', value);
        }
        
        // Try to set the dropdown parameter
        try {
            const node = scene.node(nodePath);
            if (node) {
                console.log('Found node for dropdown parameter:', nodePath);
                
                // Try different parameter names
                if (node.p.value !== undefined) {
                    node.p.value.set(value);
                    console.log('Set value parameter:', value);
                    return true;
                } 
                else if (node.p.option !== undefined) {
                    node.p.option.set(value);
                    console.log('Set option parameter:', value);
                    return true;
                }
                else if (node.p.choice !== undefined) {
                    node.p.choice.set(value);
                    console.log('Set choice parameter:', value);
                    return true;
                }
                else if (node.p.selectedItem !== undefined) {
                    node.p.selectedItem.set(value);
                    console.log('Set selectedItem parameter:', value);
                    return true;
                }
                else if (node.p.select !== undefined) {
                    node.p.select.set(value);
                    console.log('Set select parameter:', value);
                    return true;
                }
                else {
                    console.warn('Could not find dropdown parameter on node:', nodePath);
                }
            } else {
                console.warn('Node not found for dropdown parameter:', nodePath);
            }
        } catch (e) {
            console.error('Error setting dropdown parameter:', e);
        }
    }
    
    /**
     * Helper function to convert hex color to RGB
     */
    function hexToRgb(hex) {
        // Remove # if present
        hex = hex.replace(/^#/, '');
        
        // Parse hex value
        let bigint = parseInt(hex, 16);
        
        // Handle different hex formats
        if (hex.length === 3) {
            // Short notation #RGB
            const r = ((bigint >> 8) & 15) * 17;
            const g = ((bigint >> 4) & 15) * 17;
            const b = (bigint & 15) * 17;
            return { r, g, b };
        } else if (hex.length === 6) {
            // Standard notation #RRGGBB
            const r = (bigint >> 16) & 255;
            const g = (bigint >> 8) & 255;
            const b = bigint & 255;
            return { r, g, b };
        }
        
        return null;
    }
    
    // Notify parent when PolygonJS is ready
    function checkPolygonJsReady() {
        if (window.polygonjs && window.polygonjs.scene) {
            window.parent.postMessage({ type: 'td_polygonjs_ready' }, '*');
            
            // If this is an admin preview, add a flag to the scene
            if (window.location.search.includes('admin_preview=1')) {
                window.polygonjs.isAdminPreview = true;
            }
            
            // Clear the interval once PolygonJS is ready
            clearInterval(readyCheckInterval);
        }
    }
    
    // Check for PolygonJS readiness periodically
    const readyCheckInterval = setInterval(checkPolygonJsReady, 500);
    
    /**
     * Rotate the camera by specified angles
     */
    function rotateCamera(rotation) {
        if (!window.polygonjs || !window.polygonjs.scene) {
            console.error('PolygonJS not initialized');
            return;
        }
        
        try {
            // Try to find the camera
            const scene = window.polygonjs.scene;
            const cameras = scene.cameraNodes();
            
            if (!cameras || cameras.length === 0) {
                console.error('No cameras found in scene');
                return;
            }
            
            // Use the first camera
            const camera = cameras[0];
            
            // Apply rotation
            if (rotation.x) {
                camera.p.rx.set(camera.p.rx.value + rotation.x);
            }
            
            if (rotation.y) {
                camera.p.ry.set(camera.p.ry.value + rotation.y);
            }
            
            if (rotation.z) {
                camera.p.rz.set(camera.p.rz.value + rotation.z);
            }
            
            // Force scene update
            scene.processUpdateNodeConnections();
        } catch (error) {
            console.error('Error rotating camera:', error);
        }
    }
    
    /**
     * Reset the camera to default position
     */
    function resetCamera() {
        if (!window.polygonjs || !window.polygonjs.scene) {
            console.error('PolygonJS not initialized');
            return;
        }
        
        try {
            // Try to find the camera
            const scene = window.polygonjs.scene;
            const cameras = scene.cameraNodes();
            
            if (!cameras || cameras.length === 0) {
                console.error('No cameras found in scene');
                return;
            }
            
            // Use the first camera
            const camera = cameras[0];
            
            // Reset rotation
            if (camera.p.rx) camera.p.rx.set(0);
            if (camera.p.ry) camera.p.ry.set(0);
            if (camera.p.rz) camera.p.rz.set(0);
            
            // Reset position if possible
            if (camera.p.t) {
                if (camera.p.tx) camera.p.tx.set(0);
                if (camera.p.ty) camera.p.ty.set(0);
                if (camera.p.tz) camera.p.tz.set(5); // Default distance
            }
            
            // Force scene update
            scene.processUpdateNodeConnections();
        } catch (error) {
            console.error('Error resetting camera:', error);
        }
    }
    
    /**
     * Download the model in the specified format
     */
    function downloadModel(format, filename) {
        if (!window.polygonjs || !window.polygonjs.scene) {
            console.error('PolygonJS not initialized');
            return;
        }
        
        try {
            // Try to find the exporter node
            const scene = window.polygonjs.scene;
            let exporterNode = null;
            
            // Common paths for exporter nodes based on scene structure
            const possiblePaths = [
                '/geo1/exporterGLTF1',
                '/doos/exporterGLTF1', 
                '/case/exporterGLTF1',
                '/root/exporterGLTF1',
                '/geo2/exporterGLTF1'
            ];
            
            // Try to find the exporter node
            for (const path of possiblePaths) {
                const node = scene.node(path);
                if (node) {
                    exporterNode = node;
                    console.log('Found exporter node at:', path);
                    break;
                }
            }
            
            if (!exporterNode) {
                console.error('No exporter node found');
                
                // Notify parent that download failed
                window.parent.postMessage({
                    type: 'download_failed',
                    error: 'No exporter node found'
                }, '*');
                return;
            }
            
            // Set filename if provided
            if (filename && exporterNode.p.fileName) {
                exporterNode.p.fileName.set(filename);
            }
            
            // Trigger export
            console.log('Triggering export...');
            if (exporterNode.p.trigger) {
                exporterNode.p.trigger.pressButton();
                
                // Notify parent that download has started
                window.parent.postMessage({
                    type: 'download_started',
                    format: format
                }, '*');
            } else {
                console.error('Exporter node has no trigger parameter');
                
                // Notify parent that download failed
                window.parent.postMessage({
                    type: 'download_failed',
                    error: 'Exporter node has no trigger parameter'
                }, '*');
            }
        } catch (error) {
            console.error('Error downloading model:', error);
            
            // Notify parent that download failed
            window.parent.postMessage({
                type: 'download_failed',
                error: error.message
            }, '*');
        }
    }
    
})();