/**
 * Enhanced Admin Model Viewer JS
 * 
 * Handles applying customer parameters to PolygonJS scenes in the admin preview
 * with robust parameter detection, multiple scene object approaches, and improved error handling.
 * 
 * Version: 2.0
 */

(function() {
    console.log('TD ADMIN MODELS VIEWER SCRIPT LOADED - VERSION 2.0');
    
    // Debug mode for verbose logging
    const DEBUG = true;
    
    // Maximum number of retries for scene detection
    const MAX_RETRIES = 15;
    let retryCount = 0;
    
    // Maximum retries for parameter application
    const MAX_PARAM_RETRIES = 3;
    let paramRetryCount = 0;
    
    // Store parameters for repeated application
    let pendingParameters = null;
    
    // Track initialization status
    let initialized = false;
    
    /**
     * Listen for messages from the parent window
     */
    window.addEventListener('message', function(event) {
        // Verify message structure
        if (!event.data || typeof event.data !== 'object' || !event.data.type) {
            return;
        }
        
        logDebug('Received message from parent:', event.data);
        
        // Handle parameter application request
        if (event.data.type === 'td_apply_parameters' && event.data.parameters) {
            logDebug('Received parameters from admin panel');
            
            // Store parameters globally for retries
            pendingParameters = event.data.parameters;
            
            // Reset retry counter
            paramRetryCount = 0;
            
            // Try to apply parameters immediately
            tryApplyParameters();
        }
        
        // Handle download request
        if (event.data.type === 'download_model') {
            downloadModel(event.data.format, event.data.filename);
        }
    });
    
    /**
     * Attempt to apply parameters with retry logic
     */
    function tryApplyParameters() {
        if (!pendingParameters) {
            logDebug('No parameters to apply');
            return;
        }
        
        // Get the scene or retry later
        const scene = getPolygonJsScene();
        if (!scene) {
            paramRetryCount++;
            if (paramRetryCount <= MAX_PARAM_RETRIES) {
                logDebug(`Scene not ready, will retry parameter application in 1 second (Attempt ${paramRetryCount}/${MAX_PARAM_RETRIES})`);
                setTimeout(tryApplyParameters, 1000);
            } else {
                logError(`Failed to apply parameters after ${MAX_PARAM_RETRIES} attempts`);
            }
            return;
        }
        
        // Apply the parameters
        applyCustomerParameters(pendingParameters, scene);
        
        // Apply parameters again after a short delay to handle race conditions
        // where the scene might not be fully loaded yet
        setTimeout(function() {
            if (pendingParameters) {
                logDebug('Reapplying parameters after delay to ensure they are applied');
                applyCustomerParameters(pendingParameters, scene);
                
                // Clear pending parameters to avoid further automatic reapplication
                pendingParameters = null;
            }
        }, 2000);
    }
    
    /**
     * Get the PolygonJS scene using multiple detection methods
     */
    function getPolygonJsScene() {
        // Method 1: Check window.polygonjs.scene
        if (window.polygonjs && window.polygonjs.scene) {
            logDebug('Found scene at window.polygonjs.scene');
            return window.polygonjs.scene;
        }
        
        // Method 2: Check window.polyScene (alternate location)
        if (window.polyScene) {
            logDebug('Found scene at window.polyScene');
            return window.polyScene;
        }
        
        // Method 3: Check if TDPolygonjs bridge is initialized
        if (window.TDPolygonjs && typeof window.TDPolygonjs.getScene === 'function') {
            const scene = window.TDPolygonjs.getScene();
            if (scene) {
                logDebug('Found scene via TDPolygonjs.getScene()');
                return scene;
            }
        }
        
        // Method 4: Check scene in global window object
        for (const key in window) {
            if (
                key.toLowerCase().includes('scene') &&
                window[key] &&
                typeof window[key] === 'object' &&
                typeof window[key].node === 'function'
            ) {
                logDebug(`Found scene in global window.${key}`);
                return window[key];
            }
        }
        
        logDebug('PolygonJS scene not found');
        return null;
    }
    
    /**
     * Apply customer parameters to the PolygonJS scene
     */
    function applyCustomerParameters(parameters, scene) {
        if (!scene) {
            logError('Cannot apply parameters: PolygonJS scene is null');
            return;
        }
        
        logDebug('Applying parameters to scene', Object.keys(parameters).length);
        
        // Track RGB groups to avoid duplicate processing
        const processedRgbGroups = {};
        
        // Process each parameter
        for (const paramId in parameters) {
            if (!parameters.hasOwnProperty(paramId)) continue;
            
            const param = parameters[paramId];
            logDebug(`Processing parameter: ${paramId}`, param);
            
            // Skip parameters without required data
            if (!paramId || !param || !param.value) {
                logWarning(`Invalid parameter data for ${paramId}`);
                continue;
            }
            
            // Handle RGB group components
            if (param.is_rgb_group && param.rgb_group) {
                if (param.is_rgb_component && param.is_rgb_component !== 'r') {
                    // Skip non-red components of RGB groups
                    logDebug(`Skipping non-red RGB component: ${paramId}`);
                    continue;
                }
                
                // Skip if we already processed this RGB group
                if (processedRgbGroups[param.rgb_group]) {
                    logDebug(`Already processed RGB group: ${param.rgb_group}`);
                    continue;
                }
                
                // Mark this RGB group as processed
                processedRgbGroups[param.rgb_group] = true;
            }
            
            try {
                // Apply parameter based on its control type
                switch (param.control_type) {
                    case 'color':
                        applyColorParameter(paramId, param, scene);
                        break;
                    
                    case 'slider':
                    case 'number':
                        applyNumericParameter(paramId, param, scene);
                        break;
                    
                    case 'text':
                        applyTextParameter(paramId, param, scene);
                        break;
                    
                    case 'checkbox':
                        applyBooleanParameter(paramId, param, scene);
                        break;
                    
                    case 'dropdown':
                        applyDropdownParameter(paramId, param, scene);
                        break;
                    
                    default:
                        // Try a generic approach for unknown types
                        applyGenericParameter(paramId, param, scene);
                }
            } catch (error) {
                logError(`Error applying parameter ${paramId}:`, error);
            }
        }
        
        // Force scene update
        try {
            if (typeof scene.processUpdateNodeConnections === 'function') {
                scene.processUpdateNodeConnections();
                logDebug('Called scene.processUpdateNodeConnections()');
            }
            
            if (scene.root && typeof scene.root().compute === 'function') {
                scene.root().compute();
                logDebug('Called scene.root().compute()');
            }
        } catch (e) {
            logError('Error forcing scene update:', e);
        }
    }
    
    /**
     * Apply a color parameter
     */
    function applyColorParameter(paramId, param, scene) {
        logDebug(`Applying color parameter: ${paramId}`, param);
        
        // Extract RGB values from the parameter
        const rgbValues = extractColorValues(param);
        
        if (!rgbValues) {
            logError(`Could not extract RGB values for color parameter ${paramId}`);
            return;
        }
        
        // Normalize RGB values to 0-1 range for PolygonJS
        const r = normalizeColorValue(rgbValues[0]);
        const g = normalizeColorValue(rgbValues[1]);
        const b = normalizeColorValue(rgbValues[2]);
        
        logDebug(`Normalized RGB values: [${r}, ${g}, ${b}]`);
        
        // Get node path
        let nodePath = extractNodePath(paramId, param);
        if (!nodePath) {
            logError(`Could not extract node path for parameter ${paramId}`);
            return;
        }
        
        logDebug(`Using node path: ${nodePath}`);
        
        // Get the node
        try {
            const node = scene.node(nodePath);
            if (!node || !node.p) {
                logError(`Node not found or has no parameters: ${nodePath}`);
                return;
            }
            
            // Try various color parameter patterns
            
            // 1. RGB components (most common)
            if (node.p.colorr !== undefined && node.p.colorg !== undefined && node.p.colorb !== undefined) {
                node.p.colorr.set(r);
                node.p.colorg.set(g);
                node.p.colorb.set(b);
                logDebug(`Set RGB components on ${nodePath}`);
                return;
            }
            
            // 2. Single color vector
            if (node.p.color !== undefined) {
                node.p.color.set([r, g, b]);
                logDebug(`Set color vector on ${nodePath}`);
                return;
            }
            
            // 3. Alternative RGB component names
            if (node.p.r !== undefined && node.p.g !== undefined && node.p.b !== undefined) {
                node.p.r.set(r);
                node.p.g.set(g);
                node.p.b.set(b);
                logDebug(`Set r,g,b components on ${nodePath}`);
                return;
            }
            
            // 4. Material diffuse color
            if (node.p.diffuse !== undefined) {
                node.p.diffuse.set([r, g, b]);
                logDebug(`Set diffuse color on ${nodePath}`);
                return;
            }
            
            // 5. Material baseColor
            if (node.p.baseColor !== undefined) {
                node.p.baseColor.set([r, g, b]);
                logDebug(`Set baseColor on ${nodePath}`);
                return;
            }
            
            // If none of the specific patterns matched, try a generic approach
            let paramFound = false;
            for (const paramName in node.p) {
                // Look for parameters that suggest they're related to color
                if (
                    paramName.toLowerCase().includes('color') ||
                    paramName.toLowerCase() === 'diffuse' ||
                    paramName.toLowerCase() === 'albedo' ||
                    paramName.toLowerCase() === 'tint'
                ) {
                    try {
                        // Try to set as array for vector params
                        node.p[paramName].set([r, g, b]);
                        logDebug(`Set color via generic param ${paramName} as vector`);
                        paramFound = true;
                        break;
                    } catch (e) {
                        // If that fails, it might be a single channel
                        try {
                            if (paramName.toLowerCase().includes('r') ||
                                paramName.toLowerCase().endsWith('red')) {
                                node.p[paramName].set(r);
                                
                                // Look for matching green and blue components
                                const baseName = paramName.replace(/r$|red$/i, '');
                                if (node.p[baseName + 'g'] || node.p[baseName + 'green']) {
                                    const greenParam = node.p[baseName + 'g'] ? baseName + 'g' : baseName + 'green';
                                    node.p[greenParam].set(g);
                                }
                                
                                if (node.p[baseName + 'b'] || node.p[baseName + 'blue']) {
                                    const blueParam = node.p[baseName + 'b'] ? baseName + 'b' : baseName + 'blue';
                                    node.p[blueParam].set(b);
                                }
                                
                                logDebug(`Set color via split RGB params starting with ${paramName}`);
                                paramFound = true;
                                break;
                            }
                        } catch (e2) {
                            // Continue to next parameter
                        }
                    }
                }
            }
            
            if (!paramFound) {
                logWarning(`Could not find suitable color parameters on node: ${nodePath}`);
            }
            
        } catch (error) {
            logError(`Error setting color on ${nodePath}:`, error);
        }
    }
    
    /**
     * Extract RGB color values from a parameter
     */
    function extractColorValues(param) {
        // 1. If we have _color_data with RGB values, use that
        if (param._color_data && param._color_data.rgb && Array.isArray(param._color_data.rgb)) {
            return param._color_data.rgb;
        }
        
        // 2. If we have _color_data with hex, convert to RGB
        if (param._color_data && param._color_data.hex) {
            return hexToRgb(param._color_data.hex);
        }
        
        // 3. If value is already a hex color
        if (typeof param.value === 'string' && param.value.startsWith('#')) {
            return hexToRgb(param.value);
        }
        
        // 4. If value is an array of RGB values
        if (Array.isArray(param.value) && param.value.length === 3) {
            return param.value;
        }
        
        // 5. Try to get color from global colors by name
        if (typeof param.value === 'string') {
            const colorName = param.value.toLowerCase();
            
            // First check local tdPolygonjs.colorOptions
            if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
                const colorKey = colorName.replace(/\\s+/g, '-');
                if (window.tdPolygonjs.colorOptions[colorKey]) {
                    const colorData = window.tdPolygonjs.colorOptions[colorKey];
                    if (colorData.rgb) {
                        return colorData.rgb;
                    } else if (colorData.hex) {
                        return hexToRgb(colorData.hex);
                    }
                }
            }
            
            // Then try parent window colors
            try {
                if (window.parent && window.parent.tdPolygonjs && window.parent.tdPolygonjs.colorOptions) {
                    const colorKey = colorName.replace(/\\s+/g, '-');
                    if (window.parent.tdPolygonjs.colorOptions[colorKey]) {
                        const colorData = window.parent.tdPolygonjs.colorOptions[colorKey];
                        if (colorData.rgb) {
                            return colorData.rgb;
                        } else if (colorData.hex) {
                            return hexToRgb(colorData.hex);
                        }
                    }
                }
            } catch (e) {
                logWarning('Error accessing parent window colors:', e);
            }
            
            // Try common color names
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
            
            if (commonColors[colorName]) {
                return commonColors[colorName];
            }
            
            // Generate a deterministic color from the name as last resort
            const hash = stringToHash(param.value);
            return [
                ((hash & 0xFF0000) >> 16) / 255,
                ((hash & 0x00FF00) >> 8) / 255,
                (hash & 0x0000FF) / 255
            ];
        }
        
        // Default to red if all else fails
        return [1, 0, 0];
    }
    
    /**
     * Apply a numeric parameter
     */
    function applyNumericParameter(paramId, param, scene) {
        logDebug(`Applying numeric parameter: ${paramId}`, param);
        
        const value = parseFloat(param.value);
        if (isNaN(value)) {
            logError(`Invalid numeric value for ${paramId}: ${param.value}`);
            return;
        }
        
        // Get node path
        let nodePath = extractNodePath(paramId, param);
        if (!nodePath) {
            logError(`Could not extract node path for parameter ${paramId}`);
            return;
        }
        
        // Try to update the parameter
        try {
            const node = scene.node(nodePath);
            if (!node || !node.p) {
                logError(`Node not found or has no parameters: ${nodePath}`);
                return;
            }
            
            // Get parameter name from the end of the paramId or use "value" as default
            const paramName = getParameterName(paramId);
            
            // Detect parameter pattern and apply value
            const parameterApplied = applyValueToNode(node, paramName, value, { 
                isNumeric: true, 
                nodeId: paramId,
                nodePath: nodePath 
            });
            
            if (!parameterApplied) {
                logWarning(`Could not apply numeric value to any parameter on node: ${nodePath}`);
            }
            
        } catch (error) {
            logError(`Error setting numeric parameter on ${nodePath}:`, error);
        }
    }
    
    /**
     * Apply a text parameter
     */
    function applyTextParameter(paramId, param, scene) {
        logDebug(`Applying text parameter: ${paramId}`, param);
        
        const value = param.value;
        
        // Get node path
        let nodePath = extractNodePath(paramId, param);
        if (!nodePath) {
            logError(`Could not extract node path for parameter ${paramId}`);
            return;
        }
        
        // Try to update the parameter
        try {
            const node = scene.node(nodePath);
            if (!node || !node.p) {
                logError(`Node not found or has no parameters: ${nodePath}`);
                return;
            }
            
            // Text parameters commonly use these names
            const textParamNames = [
                'text', 'string', 'value', 'input', 'content', 'label'
            ];
            
            // Get parameter name from the end of the paramId
            const paramName = getParameterName(paramId);
            
            // First try the specific parameter name if it exists
            if (node.p[paramName] !== undefined) {
                try {
                    node.p[paramName].set(value);
                    logDebug(`Set text parameter ${paramName} on ${nodePath}`);
                    return;
                } catch (e) {
                    logDebug(`Error setting specific text parameter ${paramName}:`, e);
                }
            }
            
            // Try common text parameter names
            for (const textParam of textParamNames) {
                if (node.p[textParam] !== undefined) {
                    try {
                        node.p[textParam].set(value);
                        logDebug(`Set text parameter ${textParam} on ${nodePath}`);
                        return;
                    } catch (e) {
                        // Continue to next parameter
                    }
                }
            }
            
            // If all else fails, try all parameters
            for (const key in node.p) {
                try {
                    node.p[key].set(value);
                    logDebug(`Set text to parameter ${key} on ${nodePath}`);
                    return;
                } catch (e) {
                    // Continue to next parameter
                }
            }
            
            logWarning(`Could not apply text value to any parameter on node: ${nodePath}`);
            
        } catch (error) {
            logError(`Error setting text parameter on ${nodePath}:`, error);
        }
    }
    
    /**
     * Apply a boolean parameter
     */
    function applyBooleanParameter(paramId, param, scene) {
        logDebug(`Applying boolean parameter: ${paramId}`, param);
        
        // Convert to boolean
        const boolValue = (
            param.value === true || 
            param.value === 1 || 
            param.value === '1' || 
            param.value === 'yes' || 
            param.value === 'true'
        );
        
        // Get node path
        let nodePath = extractNodePath(paramId, param);
        if (!nodePath) {
            logError(`Could not extract node path for parameter ${paramId}`);
            return;
        }
        
        // Boolean parameters commonly use these names
        const boolParamNames = [
            'value', 'enabled', 'visible', 'active', 'toggle', 'on', 'display'
        ];
        
        // Try to update the parameter
        try {
            const node = scene.node(nodePath);
            if (!node || !node.p) {
                logError(`Node not found or has no parameters: ${nodePath}`);
                return;
            }
            
            // Get parameter name from the end of the paramId
            const paramName = getParameterName(paramId);
            
            // First try the specific parameter name if it exists
            if (node.p[paramName] !== undefined) {
                try {
                    node.p[paramName].set(boolValue);
                    logDebug(`Set boolean parameter ${paramName} on ${nodePath}`);
                    return;
                } catch (e) {
                    logDebug(`Error setting specific boolean parameter ${paramName}:`, e);
                }
            }
            
            // Try common boolean parameter names
            for (const boolParam of boolParamNames) {
                if (node.p[boolParam] !== undefined) {
                    try {
                        node.p[boolParam].set(boolValue);
                        logDebug(`Set boolean parameter ${boolParam} on ${nodePath}`);
                        return;
                    } catch (e) {
                        // Continue to next parameter
                    }
                }
            }
            
            // If all else fails, try all parameters
            for (const key in node.p) {
                try {
                    node.p[key].set(boolValue);
                    logDebug(`Set boolean to parameter ${key} on ${nodePath}`);
                    return;
                } catch (e) {
                    // Continue to next parameter
                }
            }
            
            logWarning(`Could not apply boolean value to any parameter on node: ${nodePath}`);
            
        } catch (error) {
            logError(`Error setting boolean parameter on ${nodePath}:`, error);
        }
    }
    
    /**
     * Apply a dropdown parameter
     */
    function applyDropdownParameter(paramId, param, scene) {
        logDebug(`Applying dropdown parameter: ${paramId}`, param);
        
        // Extract the actual value, handling "value - label" format
        let value = param.value;
        if (typeof value === 'string' && value.includes(' - ')) {
            value = value.split(' - ')[0];
        }
        
        // Try to convert to number if it looks numeric
        if (!isNaN(parseFloat(value)) && isFinite(value)) {
            value = parseFloat(value);
        }
        
        // Get node path
        let nodePath = extractNodePath(paramId, param);
        if (!nodePath) {
            logError(`Could not extract node path for parameter ${paramId}`);
            return;
        }
        
        // Dropdown parameters commonly use these names
        const dropdownParamNames = [
            'value', 'option', 'choice', 'select', 'selectedItem', 'selection', 'input'
        ];
        
        // Try to update the parameter
        try {
            const node = scene.node(nodePath);
            if (!node || !node.p) {
                logError(`Node not found or has no parameters: ${nodePath}`);
                return;
            }
            
            // Get parameter name from the end of the paramId
            const paramName = getParameterName(paramId);
            
            // First try the specific parameter name if it exists
            if (node.p[paramName] !== undefined) {
                try {
                    node.p[paramName].set(value);
                    logDebug(`Set dropdown parameter ${paramName} on ${nodePath}`);
                    return;
                } catch (e) {
                    logDebug(`Error setting specific dropdown parameter ${paramName}:`, e);
                }
            }
            
            // Try common dropdown parameter names
            for (const dropdownParam of dropdownParamNames) {
                if (node.p[dropdownParam] !== undefined) {
                    try {
                        node.p[dropdownParam].set(value);
                        logDebug(`Set dropdown parameter ${dropdownParam} on ${nodePath}`);
                        return;
                    } catch (e) {
                        // Continue to next parameter
                    }
                }
            }
            
            // If all else fails, try all parameters
            for (const key in node.p) {
                try {
                    node.p[key].set(value);
                    logDebug(`Set dropdown value to parameter ${key} on ${nodePath}`);
                    return;
                } catch (e) {
                    // Continue to next parameter
                }
            }
            
            logWarning(`Could not apply dropdown value to any parameter on node: ${nodePath}`);
            
        } catch (error) {
            logError(`Error setting dropdown parameter on ${nodePath}:`, error);
        }
    }
    
    /**
     * Apply a generic parameter when type is unknown
     */
    function applyGenericParameter(paramId, param, scene) {
        logDebug(`Applying generic parameter: ${paramId}`, param);
        
        const value = param.value;
        
        // Get node path
        let nodePath = extractNodePath(paramId, param);
        if (!nodePath) {
            logError(`Could not extract node path for parameter ${paramId}`);
            return;
        }
        
        // Try to update the parameter
        try {
            const node = scene.node(nodePath);
            if (!node || !node.p) {
                logError(`Node not found or has no parameters: ${nodePath}`);
                return;
            }
            
            // Get parameter name from the end of the paramId
            const paramName = getParameterName(paramId);
            
            // Try to set the value to the specific parameter
            if (node.p[paramName] !== undefined) {
                try {
                    node.p[paramName].set(value);
                    logDebug(`Set generic parameter ${paramName} on ${nodePath}`);
                    return;
                } catch (e) {
                    logDebug(`Error setting specific generic parameter ${paramName}:`, e);
                }
            }
            
            // Try the 'value' parameter as a fallback
            if (node.p.value !== undefined) {
                try {
                    node.p.value.set(value);
                    logDebug(`Set generic value parameter on ${nodePath}`);
                    return;
                } catch (e) {
                    logDebug(`Error setting generic value parameter:`, e);
                }
            }
            
            // If all else fails, try all parameters
            for (const key in node.p) {
                try {
                    node.p[key].set(value);
                    logDebug(`Set generic value to parameter ${key} on ${nodePath}`);
                    return;
                } catch (e) {
                    // Continue to next parameter
                }
            }
            
            logWarning(`Could not apply generic value to any parameter on node: ${nodePath}`);
            
        } catch (error) {
            logError(`Error setting generic parameter on ${nodePath}:`, error);
        }
    }
    
    /**
     * Get a parameter name from parameter ID
     */
    function getParameterName(paramId) {
        if (!paramId) return 'value';
        
        // Extract the last part of the ID as parameter name
        if (paramId.includes('-')) {
            const parts = paramId.split('-');
            const lastPart = parts[parts.length - 1];
            return lastPart;
        }
        
        // If there's a node_id in the parameter, use it for path and assume 'value' for parameter
        if (param && param.node_id) {
            return 'value';
        }
        
        return 'value'; // Default parameter name
    }
    
    /**
     * Try to apply a value to a node with appropriate type detection
     */
    function applyValueToNode(node, paramName, value, options = {}) {
        const { isNumeric, nodeId, nodePath } = options;
        
        // Detect the pattern and apply value
        let applied = false;
        
        // 1. Try direct match for the specified parameter name
        if (node.p[paramName] !== undefined) {
            try {
                node.p[paramName].set(value);
                logDebug(`Set parameter ${paramName} on ${nodePath}`);
                applied = true;
            } catch (e) {
                logWarning(`Error setting ${paramName} directly:`, e);
            }
        }
        
        // 2. If that didn't work and it's a numeric value, try common numerical parameters
        if (!applied && isNumeric) {
            const numericParamNames = [
                'value', 'val', 'size', 'scale', 'amount', 'radius', 'length', 'width', 'height', 'depth'
            ];
            
            for (const numParamName of numericParamNames) {
                if (node.p[numParamName] !== undefined) {
                    try {
                        node.p[numParamName].set(value);
                        logDebug(`Set numeric parameter ${numParamName} on ${nodePath}`);
                        applied = true;
                        break;
                    } catch (e) {
                        // Continue to next parameter
                    }
                }
            }
            
            // Special case for scale which might be a vector
            if (!applied && node.p.scale !== undefined) {
                try {
                    // Try setting as a scalar value
                    node.p.scale.set(value);
                    logDebug(`Set scale parameter as scalar on ${nodePath}`);
                    applied = true;
                } catch (e) {
                    // Try setting as a vector (uniform scale)
                    try {
                        node.p.scale.set([value, value, value]);
                        logDebug(`Set scale parameter as vector on ${nodePath}`);
                        applied = true;
                    } catch (e2) {
                        logWarning('Error setting scale parameter:', e2);
                    }
                }
            }
            
            // Special case for size X, Y, Z
            if (!applied) {
                const sizeParams = ['sizeX', 'sizeY', 'sizeZ', 'sx', 'sy', 'sz'];
                
                for (const sizeParam of sizeParams) {
                    if (node.p[sizeParam] !== undefined) {
                        try {
                            // Convert mm to m if needed (PolygonJS uses meters)
                            const convertedValue = (value > 1 && sizeParam.startsWith('size')) ? 
                                value * 0.001 : value;
                            
                            node.p[sizeParam].set(convertedValue);
                            logDebug(`Set size parameter ${sizeParam} to ${convertedValue} on ${nodePath}`);
                            applied = true;
                            break;
                        } catch (e) {
                            // Continue to next parameter
                        }
                    }
                }
            }
        }
        
        // 3. Try all parameters as a last resort
        if (!applied) {
            for (const key in node.p) {
                if (typeof node.p[key].set === 'function') {
                    try {
                        node.p[key].set(value);
                        logDebug(`Set ${key} parameter on ${nodePath}`);
                        applied = true;
                        break;
                    } catch (e) {
                        // Continue to next parameter
                    }
                }
            }
        }
        
        return applied;
    }
    
    /**
     * Extract node path from parameter ID or node_id
     */
    function extractNodePath(paramId, param) {
        // First try to use node_id from the parameter if available
        if (param && param.node_id) {
            return '/' + param.node_id.replace(/-/g, '/');
        }
        
        // Otherwise extract from parameter ID
        if (paramId && paramId.includes('-')) {
            const parts = paramId.split('-');
            
            // Check if the last part is a parameter name, and if so, remove it
            const lastPart = parts[parts.length - 1].toLowerCase();
            const commonParameterNames = [
                'value', 'color', 'colorr', 'colorg', 'colorb', 'r', 'g', 'b',
                'size', 'scale', 'width', 'height', 'depth', 'text', 'string',
                'enabled', 'visible', 'toggle', 'option', 'choice', 'selected'
            ];
            
            if (commonParameterNames.includes(lastPart)) {
                parts.pop();
            }
            
            return '/' + parts.join('/');
        }
        
        return null;
    }
    
    /**
     * Download model in the requested format
     */
    function downloadModel(format, filename) {
        logDebug('downloadModel function called - this script should not be running in the iframe!');
        
        // This function should not be executed if we're inside the PolygonJS iframe
        // The download should be handled by the PolygonJS bundle itself
        
        // If we somehow end up here, try to trigger the download button that PolygonJS creates
        try {
            // Look for the hidden download button that PolygonJS creates
            const hiddenButton = document.querySelector('button[style*="display: none"]');
            if (hiddenButton) {
                logDebug('Found hidden download button, clicking it');
                hiddenButton.click();
                return;
            }
            
            // Also try common button selectors
            const downloadSelectors = [
                '#download-button',
                'button#download',
                'button.download-button',
                'button[data-download]',
                '[data-role="download-button"]'
            ];
            
            for (const selector of downloadSelectors) {
                const btn = document.querySelector(selector);
                if (btn) {
                    logDebug(`Found download button with selector ${selector}, clicking it`);
                    btn.click();
                    return;
                }
            }
            
            logError('No download button found to click');
        } catch (error) {
            logError('Error in downloadModel:', error);
        }
    }
    
    /**
     * Initialize script and check for PolygonJS readiness
     */
    function initialize() {
        if (initialized) return;
        
        logDebug('Initializing Admin Model Viewer...');
        
        // Check for PolygonJS readiness periodically
        const readyCheckInterval = setInterval(function() {
            const scene = getPolygonJsScene();
            
            if (scene) {
                clearInterval(readyCheckInterval);
                logDebug('✅ PolygonJS scene detected and ready');
                
                // Send ready message to parent
                window.parent.postMessage({ 
                    type: 'td_polygonjs_ready',
                    scene: true
                }, '*');
                
                // Apply any pending parameters
                if (pendingParameters) {
                    applyCustomerParameters(pendingParameters, scene);
                }
                
                initialized = true;
            } else {
                retryCount++;
                if (retryCount > MAX_RETRIES) {
                    clearInterval(readyCheckInterval);
                    logError(`Failed to detect PolygonJS scene after ${MAX_RETRIES} attempts`);
                }
            }
        }, 500);
        
        // Copy global colors from parent window if available
        tryToCopyGlobalColors();
    }
    
    /**
     * Try to copy global colors from parent window
     */
    function tryToCopyGlobalColors() {
        try {
            if (window.parent && window.parent.tdPolygonjs && window.parent.tdPolygonjs.colorOptions) {
                // Create object if it doesn't exist
                if (!window.tdPolygonjs) {
                    window.tdPolygonjs = {};
                }
                
                if (!window.tdPolygonjs.colorOptions) {
                    window.tdPolygonjs.colorOptions = {};
                }
                
                // Copy colors
                const parentColors = window.parent.tdPolygonjs.colorOptions;
                Object.keys(parentColors).forEach(key => {
                    window.tdPolygonjs.colorOptions[key] = JSON.parse(JSON.stringify(parentColors[key]));
                });
                
                logDebug(`Copied ${Object.keys(window.tdPolygonjs.colorOptions).length} colors from parent window`);
                return true;
            }
        } catch (e) {
            logWarning('Error copying global colors from parent window:', e);
        }
        return false;
    }
    
    /* ===== Utility Functions ===== */
    
    /**
     * Normalize a color value to be in the 0-1 range
     */
    function normalizeColorValue(value) {
        if (typeof value !== 'number') {
            value = parseFloat(value);
            if (isNaN(value)) return 0;
        }
        
        // If value is larger than 1, assume it's in 0-255 range
        return value > 1 ? value / 255 : value;
    }
    
    /**
     * Convert hex color to RGB
     */
    function hexToRgb(hex) {
        // Remove # if present
        hex = hex.replace(/^#/, '');
        
        // Parse hex value
        let r, g, b;
        
        if (hex.length === 3) {
            // Short notation #RGB
            r = parseInt(hex.charAt(0) + hex.charAt(0), 16) / 255;
            g = parseInt(hex.charAt(1) + hex.charAt(1), 16) / 255;
            b = parseInt(hex.charAt(2) + hex.charAt(2), 16) / 255;
        } else if (hex.length === 6) {
            // Standard notation #RRGGBB
            r = parseInt(hex.substring(0, 2), 16) / 255;
            g = parseInt(hex.substring(2, 4), 16) / 255;
            b = parseInt(hex.substring(4, 6), 16) / 255;
        } else {
            logWarning(`Invalid hex format: ${hex}`);
            return [1, 0, 0]; // Default to red
        }
        
        return [r, g, b];
    }
    
    /**
     * Generate a hash from a string
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
     * Logging utilities
     */
    function logDebug(...args) {
        if (DEBUG) console.log('[TD Model Viewer]', ...args);
    }
    
    function logWarning(...args) {
        console.warn('[TD Model Viewer]', ...args);
    }
    
    function logError(...args) {
        console.error('[TD Model Viewer]', ...args);
    }
    
    // Initialize the script
    initialize();
})();