/**
 * Admin Model Viewer JavaScript
 * Handles 3D model parameter injection and iframe communication
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // Check if we have the required data
        if (typeof tdModelViewerData === 'undefined') {
            console.error('TD Model Viewer: Required data not found');
            return;
        }

        // Initialize synced colors and parameters from PHP data
        window.tdSyncedColors = tdModelViewerData.synced_colors || {};
        window.tdSyncedParameters = tdModelViewerData.synced_parameters || {};

        // Make the data globally available for the injected script
        window.tdModelViewerData = tdModelViewerData;

        // Variables to store the state
        let iframeLoaded = false;
        let polygonJsReady = false;
        let pendingParameters = tdModelViewerData.parameters || {};
        let retryCount = 0;
        let maxRetries = 30; // Increased number of retries for more reliability
        
        // Set a maximum time to wait for the loading screen
        const maxLoadingTime = 15000; // 15 seconds
        
        console.log('Model viewer initialized with parameters:', pendingParameters);
        
        // Set a timeout to hide the loader in case the ready event never comes
        setTimeout(() => {
            if ($('#td-iframe-loader').is(':visible')) {
                console.log('Forcing loader to hide after timeout');
                $('#td-iframe-loader').fadeOut();
            }
        }, maxLoadingTime);
        
        // Hide loader when iframe is loaded
        $('#td-model-iframe').on('load', function() {
            iframeLoaded = true;
            console.log('Iframe loaded, ready to interact with PolygonJS');
            
            // Inject our script directly into the iframe
            injectParameterHandler();
            
            // Send parameters immediately
            setTimeout(applyParameters, 1000);
            
            // And apply again after a delay to ensure they're sent after the scene is fully ready
            setTimeout(applyParameters, 3000);
            
            // We'll keep the loader visible until the scene is actually ready
        });
        
        /**
         * Inject parameter handler script directly into the iframe
         */
        function injectParameterHandler() {
            try {
                const iframe = document.getElementById('td-model-iframe');
                if (!iframe || !iframe.contentWindow || !iframe.contentDocument) {
                    console.error('Cannot access iframe');
                    return;
                }
                
                // Create script element
                const script = iframe.contentDocument.createElement('script');
                script.type = 'text/javascript';
                script.innerHTML = generateParameterHandlerScript();
                
                // Add to the document
                iframe.contentDocument.head.appendChild(script);
                console.log('Parameter handler script injected successfully');
                
                // Inject unified sync script if we have frontend state
                if (tdModelViewerData.unified_sync_script) {
                    try {
                        const unifiedScript = iframe.contentDocument.createElement('script');
                        unifiedScript.type = 'text/javascript';
                        unifiedScript.innerHTML = tdModelViewerData.unified_sync_script;
                        iframe.contentDocument.head.appendChild(unifiedScript);
                        console.log('✅ Unified sync script injected with frontend state');
                    } catch (unifiedError) {
                        console.error('❌ Error injecting unified sync script:', unifiedError);
                    }
                } else {
                    console.log('ℹ️ No unified sync state available for this model');
                }
                
            } catch (e) {
                console.error('Error injecting script:', e);
            }
        }

        /**
         * Generate the complete parameter handler script to inject into iframe
         */
        function generateParameterHandlerScript() {
            return `
                // TD Model Viewer Parameter Handler - Directly Injected
                (function() {
                    console.log('[TD Model Viewer] Injected parameter handler ready');
                    
                    // Debug mode for logging
                    const DEBUG = true;
                    
                    // Store parameters for application
                    let pendingParameters = null;
                    
                    /**
                     * Smart node discovery - finds the correct node path by searching the scene
                     */
                    function discoverCorrectNodePath(scene, approximatePath, paramName) {
                        if (!scene || !scene.nodesController || !scene.nodesController.nodes) {
                            return null;
                        }
                        
                        const nodeMap = scene.nodesController.nodes;
                        const targetSegments = approximatePath.replace(/^\//, '').split('/');
                        
                        // Strategy 1: Find nodes that contain the main path segments
                        const candidates = [];
                        for (const [nodePath, nodeObj] of nodeMap) {
                            const pathSegments = nodePath.replace(/^\//, '').split('/');
                            
                            // Check if this path contains key segments from our target
                            let matches = 0;
                            for (const targetSeg of targetSegments) {
                                for (const pathSeg of pathSegments) {
                                    if (pathSeg.toLowerCase().includes(targetSeg.toLowerCase()) || 
                                        targetSeg.toLowerCase().includes(pathSeg.toLowerCase())) {
                                        matches++;
                                        break;
                                    }
                                }
                            }
                            
                            // If we have good matches and the node has the parameter we need
                            if (matches >= targetSegments.length - 1) {
                                try {
                                    const node = scene.node(nodePath);
                                    if (node && node.p && (node.p[paramName] || 
                                        node.p[paramName.toLowerCase()] || 
                                        node.p[paramName.toUpperCase()])) {
                                        candidates.push({
                                            path: nodePath,
                                            matches: matches,
                                            hasParameter: true
                                        });
                                    }
                                } catch (e) {
                                    // Node not accessible, skip
                                }
                            }
                        }
                        
                        // Sort by best matches
                        candidates.sort((a, b) => b.matches - a.matches);
                        
                        if (candidates.length > 0) {
                            log('Smart discovery found candidate: ' + candidates[0].path + ' for ' + approximatePath);
                            return candidates[0].path;
                        }
                        
                        // Strategy 2: If no good matches, try to find nodes with the parameter
                        for (const [nodePath, nodeObj] of nodeMap) {
                            try {
                                const node = scene.node(nodePath);
                                if (node && node.p && (node.p[paramName] || 
                                    node.p[paramName.toLowerCase()] || 
                                    node.p[paramName.toUpperCase()])) {
                                    
                                    // Check if this node path makes sense for our target
                                    const pathLower = nodePath.toLowerCase();
                                    const approxLower = approximatePath.toLowerCase();
                                    
                                    if ((pathLower.includes('ctrl') && approxLower.includes('ctrl')) ||
                                        (pathLower.includes('mat') && approxLower.includes('mat')) ||
                                        (pathLower.includes('control') && approxLower.includes('ctrl')) ||
                                        (pathLower.includes('material') && approxLower.includes('mat'))) {
                                        log('Smart discovery found parameter match: ' + nodePath + ' for ' + approximatePath);
                                        return nodePath;
                                    }
                                }
                            } catch (e) {
                                // Node not accessible, skip
                            }
                        }
                        
                        return null;
                    }
                    
                    /**
                     * Resolve parameter node path and name from synced data (actual PolygonJS paths)
                     */
                    function resolveParameterFromSyncedData(originalNodeId, originalParamName) {
                        // Check if we have synced parameter data from the parent window
                        log('Checking for synced parameters...');
                        log('window.parent exists: ' + !!window.parent);
                        log('window.parent.tdSyncedParameters exists: ' + !!(window.parent && window.parent.tdSyncedParameters));
                        
                        if (!window.parent || !window.parent.tdSyncedParameters) {
                            log('No synced parameters available, using fallback');
                            return null;
                        }
                        
                        const syncedParams = window.parent.tdSyncedParameters;
                        
                        // Strategy 1: Direct parameter ID match
                        if (syncedParams[originalNodeId]) {
                            const mapping = syncedParams[originalNodeId];
                            log('Found direct synced mapping for: ' + originalNodeId);
                            return {
                                nodePath: mapping.actual_node_path,
                                paramName: mapping.actual_param_name
                            };
                        }
                        
                        // Strategy 2: Try building parameter ID from node path and param name
                        if (originalNodeId && originalParamName) {
                            // Convert path format to ID format
                            // e.g., "/sleutelhoes/ctrl" + "width" -> "sleutelhoes-ctrl-width"
                            let potentialId = originalNodeId;
                            if (potentialId.startsWith('/')) {
                                potentialId = potentialId.substring(1);
                            }
                            potentialId = potentialId.replace(/\//g, '-') + '-' + originalParamName;
                            
                            if (syncedParams[potentialId]) {
                                const mapping = syncedParams[potentialId];
                                log('Found synced mapping via constructed ID: ' + potentialId);
                                return {
                                    nodePath: mapping.actual_node_path,
                                    paramName: mapping.actual_param_name
                                };
                            }
                        }
                        
                        // Strategy 3: Search all synced parameters for similar ones
                        for (const [paramId, mapping] of Object.entries(syncedParams)) {
                            if (originalNodeId && originalParamName) {
                                const nodeIdClean = originalNodeId.replace(/^\//, '').replace(/\//g, '-');
                                if (paramId.includes(nodeIdClean) && paramId.includes(originalParamName)) {
                                    log('Found synced mapping via search: ' + paramId);
                                    return {
                                        nodePath: mapping.actual_node_path,
                                        paramName: mapping.actual_param_name
                                    };
                                }
                            }
                        }
                        
                        return null;
                    }
                    
                    // Listen for messages from parent
                    window.addEventListener('message', function(event) {
                        if (!event.data || typeof event.data !== 'object' || !event.data.type) return;
                        
                        log('Received message from parent: ' + event.data.type);
                        
                        // Handle standard parameter update message
                        if (event.data.type === 'updateParameter') {
                            // This is the standard format that PolygonJS expects
                            log('Received updateParameter: ' + 
                                event.data.nodeId + ' / ' + 
                                event.data.paramName + ' = ' + 
                                event.data.value);
                                
                            // Don't rely on PolygonJS handling these messages - apply them ourselves
                            try {
                                const scene = findPolygonScene();
                                if (scene) {
                                    // Universal parameter resolution using HTML snippet extraction
                                    let nodePath = event.data.nodeId;
                                    let paramName = event.data.paramName;
                                    let value = event.data.value;
                                    
                                    // Try to get correct node path and parameter from synced data (actual PolygonJS paths)
                                    const resolvedInfo = resolveParameterFromSyncedData(nodePath, paramName);
                                    if (resolvedInfo) {
                                        nodePath = resolvedInfo.nodePath;
                                        paramName = resolvedInfo.paramName;
                                        log('Resolved from synced data: ' + event.data.nodeId + ' -> ' + nodePath + '.' + paramName);
                                    } else {
                                        // Smart fallback: Try to discover the correct node path automatically
                                        let originalNodePath = nodePath;
                                        
                                        // Clean path of any trailing parameter segments first
                                        if (nodePath && typeof nodePath === 'string') {
                                            // Check for duplicate path/param pattern (most common issue)
                                            if (paramName && typeof paramName === 'string' && nodePath.endsWith('/' + paramName)) {
                                                // Remove the param from the path
                                                nodePath = nodePath.substring(0, nodePath.length - paramName.length - 1);
                                                log('Cleaned duplicate parameter from path: ' + originalNodePath + ' -> ' + nodePath);
                                            }
                                            
                                            // Check for color component paths
                                            if (nodePath.includes('/colorr') || nodePath.includes('/colorg') || nodePath.includes('/colorb')) {
                                                // Remove color component from path
                                                const components = nodePath.split('/');
                                                if (components.length > 0 && 
                                                    (components[components.length-1] === 'colorr' || 
                                                    components[components.length-1] === 'colorg' || 
                                                    components[components.length-1] === 'colorb')) {
                                                    // Remove the last component
                                                    components.pop();
                                                    nodePath = components.join('/');
                                                    log('Cleaned color component from path: ' + nodePath);
                                                }
                                            }
                                        }
                                        
                                        // Try smart discovery to find the correct node
                                        log('Attempting smart discovery for: ' + nodePath + ' param: ' + paramName);
                                        const discoveredPath = discoverCorrectNodePath(scene, nodePath, paramName);
                                        if (discoveredPath) {
                                            log('🎯 Smart discovery corrected path: ' + nodePath + ' -> ' + discoveredPath);
                                            nodePath = discoveredPath;
                                        } else {
                                            log('❌ Smart discovery failed for: ' + nodePath);
                                        }
                                    }
                                    
                                    // Universal scene mappings based on main.js structure
                                    if (nodePath) {
                                        // Define universal path mappings for all scenes
                                        const pathMappings = {
                                            // Doosje scene mappings
                                            '/doos/mat/colorbox': '/doos/MAT/colorBox',
                                            '/doos/mat/colorlid': '/doos/MAT/colorLid',
                                            '/doos/mat/colortekst': '/doos/MAT/colorTekst',
                                            '/doos/ctrl_doos/breedte': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/diepte': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/hoogte': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/dikte_wanden': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/dikte_bodem': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/deksel_dikte': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/tekst': '/doos/ctrl_doos',
                                            '/doos/ctrl_doos/tekst_schaal': '/doos/ctrl_doos',
                                            
                                            // Sleutelhoes scene mappings (from main.js lines 146-166)
                                            '/sleutelhoes/ctrl/width': '/sleutelhoes/CTRL',
                                            '/sleutelhoes/ctrl/height': '/sleutelhoes/CTRL',
                                            '/sleutelhoes/ctrl/length': '/sleutelhoes/CTRL',
                                            '/sleutelhoes/mat/meshstandard1': '/sleutelhoes/MAT/meshStandard1'
                                        };
                                        
                                        // Apply mapping if found
                                        if (pathMappings[nodePath]) {
                                            nodePath = pathMappings[nodePath];
                                            log('Applied path mapping: ' + event.data.nodeId + ' -> ' + nodePath);
                                        }
                                    }
                                    
                                    // Handle color values specially
                                    if (paramName === 'colorr' && typeof value === 'string') {
                                        // Try to find this color in tdPolygonjs.colorOptions
                                        if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
                                            const colorKey = typeof value === 'string' ? value.toLowerCase().replace(/\s+/g, '-') : String(value).toLowerCase().replace(/\s+/g, '-');
                                            if (window.tdPolygonjs.colorOptions[colorKey] && 
                                                window.tdPolygonjs.colorOptions[colorKey].rgb) {
                                                // Get RGB values
                                                const rgb = window.tdPolygonjs.colorOptions[colorKey].rgb;
                                                if (rgb && rgb.length === 3) {
                                                    // Update all RGB components
                                                    log('Found RGB values for ' + value + ': ' + rgb.join(', '));
                                                    value = rgb[0]; // Only use red component for colorr
                                                    
                                                    // Also apply green and blue if available
                                                    try {
                                                        const node = scene.node(nodePath);
                                                        if (node && node.p) {
                                                            if (node.p.colorg) node.p.colorg.set(rgb[1]);
                                                            if (node.p.colorb) node.p.colorb.set(rgb[2]);
                                                        }
                                                    } catch (e) {
                                                        log('Error setting color components: ' + e.message);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Try to find the node
                                    let node = null;
                                    try {
                                        node = scene.node(nodePath);
                                    } catch (e) {
                                        log('Error finding node at ' + nodePath + ': ' + e.message);
                                        
                                        // DEBUG: List all available nodes in the scene to help debug
                                        if (scene && scene.nodesController && scene.nodesController.nodes) {
                                            log('Available nodes in scene:');
                                            const nodeMap = scene.nodesController.nodes;
                                            const availableNodes = [];
                                            for (const [path, nodeObj] of nodeMap) {
                                                if (path.includes('ctrl') || path.includes('mat') || path.includes('control') || path.includes('material')) {
                                                    availableNodes.push(path);
                                                }
                                            }
                                            log('Control/Material nodes: ' + availableNodes.join(', '));
                                        }
                                    }
                                    
                                    // If node found, apply parameter
                                    if (node && node.p) {
                                        if (node.p[paramName]) {
                                            log('Manually applying parameter: ' + nodePath + '.' + paramName);
                                            node.p[paramName].set(value);
                                        } else {
                                            log('Parameter not found: ' + paramName + ' on node ' + nodePath);
                                            log('Available parameters: ' + Object.keys(node.p).join(', '));
                                            
                                            // Try case variations
                                            const variations = [
                                                typeof paramName === 'string' ? paramName.toLowerCase() : String(paramName).toLowerCase(),
                                                typeof paramName === 'string' ? paramName.toUpperCase() : String(paramName).toUpperCase(),
                                                typeof paramName === 'string' ? (paramName.charAt(0).toUpperCase() + paramName.slice(1)) : String(paramName).charAt(0).toUpperCase() + String(paramName).slice(1)
                                            ];
                                            
                                            for (const variant of variations) {
                                                if (node.p[variant]) {
                                                    log('Found parameter with case variation: ' + variant);
                                                    node.p[variant].set(value);
                                                    break;
                                                }
                                            }
                                        }
                                    } else {
                                        log('Node not found at path: ' + nodePath);
                                    }
                                }
                            } catch (e) {
                                log('Error manually applying parameter: ' + e.message);
                            }
                        }
                        
                        // Handle legacy parameter application request
                        else if (event.data.type === 'td_apply_parameters' && event.data.parameters) {
                            log('Received legacy parameters from parent window');
                            pendingParameters = event.data.parameters;
                            applyParameters();
                        }
                        
                        // Handle download request
                        else if (event.data.type === 'download_model') {
                            handleDownload(event.data.format, event.data.filename);
                        }
                    });
                    
                    // Setup a safer way to monitor native messages
                    // In case postMessage interception would cause issues
                    let modelIsReady = false;
                    
                    // Set interval to monitor global variables for scene readiness
                    setInterval(() => {
                        // If we haven't yet detected that model is ready
                        if (!modelIsReady) {
                            // Check if any global indicators suggest model is ready
                            if (window.modelReady || 
                                (window.polyScene && window.polyScene.isReady) ||
                                (window.polygonjs && window.polygonjs.isReady)) {
                                
                                modelIsReady = true;
                                log('Model readiness detected from global state');
                                
                                // Notify parent
                                window.parent.postMessage({ 
                                    type: 'modelReady',
                                    source: 'td_parameter_handler'
                                }, '*');
                                
                                // Apply parameters if needed
                                if (pendingParameters) {
                                    const scene = findPolygonScene();
                                    if (scene) {
                                        log('Auto-applying parameters after model ready detected');
                                        try {
                                            applyParameters();
                                        } catch(e) {
                                            log('Error during parameter application: ' + e.message);
                                        }
                                    }
                                }
                            }
                        }
                    }, 500);
                    
                    // Apply parameters to the scene using the native PolygonJS approach
                    function applyParameters() {
                        if (!pendingParameters) return;
                        
                        // Get scene with fallbacks
                        const scene = findPolygonScene();
                        if (!scene) {
                            log('Scene not found, will attempt again shortly');
                            setTimeout(applyParameters, 500);
                            return;
                        }
                        
                        log('Applying ' + Object.keys(pendingParameters).length + ' parameters to scene using native API');
                        
                        try {
                            // Process parameters
                            Object.keys(pendingParameters).forEach(paramId => {
                                const param = pendingParameters[paramId];
                                if (!param.value) return;
                                
                                // Get node path
                                const nodePath = extractNodePath(paramId, param);
                                if (!nodePath) return;
                                
                                log('Processing parameter: ' + paramId);
                                
                                try {
                                    // Try direct node method first
                                    applyParameterDirectly(scene, nodePath, param);
                                } catch (e) {
                                    log('Error with direct application, falling back to standard methods: ' + e.message);
                                    
                                    // Format value based on type
                                    const formattedValue = formatValueForType(param.value, param.control_type);
                                    
                                    // Try using the native updateParameter method
                                    // This is what PolygonJS expects from its regular UI
                                    if (window.updateParameter && typeof window.updateParameter === 'function') {
                                        log('Using window.updateParameter native method for: ' + nodePath);
                                        window.updateParameter(nodePath, formattedValue);
                                    }
                                    // Alternative: emit a standard updateParameter event
                                    else {
                                        log('Dispatching updateParameter event for: ' + nodePath);
                                        
                                        // Create a standard updateParameter message/event that PolygonJS will recognize
                                        const customEvent = new CustomEvent('updateParameter', {
                                            detail: {
                                                nodePath: nodePath,
                                                paramName: param.param_name || getParameterName(paramId),
                                                value: formattedValue
                                            }
                                        });
                                        
                                        // Dispatch event on both document and window
                                        document.dispatchEvent(customEvent);
                                        window.dispatchEvent(customEvent);
                                    }
                                }
                            });
                            
                            // Force scene update
                            if (typeof scene.processUpdateNodeConnections === 'function') {
                                scene.processUpdateNodeConnections();
                                log('Called scene.processUpdateNodeConnections()');
                            }
                            if (scene.root && typeof scene.root().compute === 'function') {
                                scene.root().compute();
                                log('Scene computed after parameter application');
                            }
                            
                            // Notify parent that parameters were applied
                            window.parent.postMessage({ type: 'parameters_applied' }, '*');
                        } catch (e) {
                            log('Error applying parameters: ' + e.message);
                        }
                        
                        // Clear parameters after application
                        pendingParameters = null;
                    }
                    
                    // Format value based on parameter type
                    function formatValueForType(value, type) {
                        if (type === 'color') {
                            // For colors, convert to RGB array in 0-1 range
                            if (typeof value === 'string') {
                                if (value.startsWith('#')) {
                                    return hexToRgb(value);
                                } else {
                                    // Try common colors
                                    const commonColors = {
                                        'red': [1, 0, 0],
                                        'green': [0, 1, 0],
                                        'blue': [0, 0, 1],
                                        'yellow': [1, 1, 0],
                                        'black': [0, 0, 0],
                                        'white': [1, 1, 1]
                                    };
                                    
                                    const lowerColor = typeof value === 'string' ? value.toLowerCase() : String(value).toLowerCase();
                                    if (commonColors[lowerColor]) {
                                        return commonColors[lowerColor];
                                    }
                                    
                                    // Generate from hash as last resort
                                    return generateColorFromString(value);
                                }
                            } else if (Array.isArray(value)) {
                                // Assume it's already RGB
                                return value.map(v => v > 1 ? v / 255 : v);
                            }
                        }
                        else if (type === 'number' || type === 'slider') {
                            // Make sure it's a number
                            return parseFloat(value);
                        }
                        else if (type === 'checkbox') {
                            // Convert to boolean
                            return value === true || 
                                value === 1 || 
                                value === '1' || 
                                value === 'yes' || 
                                value === 'true';
                        }
                        
                        // Default: return as is
                        return value;
                    }
                    
                    // Apply parameter directly to node
                    function applyParameterDirectly(scene, nodePath, param) {
                        // First try to get the node
                        const node = scene.node(nodePath);
                        if (!node || !node.p) {
                            throw new Error('Node not found or has no parameters: ' + nodePath);
                        }
                        
                        // Determine parameter name and apply based on type
                        const controlType = param.control_type || 'generic';
                        const paramName = param.param_name || getParameterName(param.id || nodePath);
                        const value = formatValueForType(param.value, controlType);
                        
                        // Apply specifically based on control type
                        if (controlType === 'color') {
                            // Handle RGB components
                            if (node.p.colorr && node.p.colorg && node.p.colorb) {
                                node.p.colorr.set(value[0]);
                                node.p.colorg.set(value[1]);
                                node.p.colorb.set(value[2]);
                                log('Set RGB components on ' + nodePath);
                                return true;
                            }
                            else if (node.p.color) {
                                node.p.color.set(value);
                                log('Set color vector on ' + nodePath);
                                return true;
                            }
                        }
                        else {
                            // For other types, try to set directly
                            if (node.p[paramName]) {
                                node.p[paramName].set(value);
                                log('Set ' + paramName + ' on ' + nodePath);
                                return true;
                            }
                            // Try 'value' as fallback
                            else if (node.p.value) {
                                node.p.value.set(value);
                                log('Set value parameter on ' + nodePath);
                                return true;
                            }
                        }
                        
                        throw new Error('No suitable parameter found on node: ' + nodePath);
                    }
                    
                    // Extract node path from parameter
                    function extractNodePath(paramId, param) {
                        // Use node_id from parameter if available
                        if (param.node_id) {
                            return '/' + param.node_id.replace(/-/g, '/');
                        }
                        
                        // Extract from parameter ID
                        if (paramId.includes('-')) {
                            const parts = paramId.split('-');
                            const lastPart = typeof parts[parts.length - 1] === 'string' ? parts[parts.length - 1].toLowerCase() : String(parts[parts.length - 1]).toLowerCase();
                            
                            // Check if last part is a common parameter name
                            const commonParams = ['value', 'color', 'colorr', 'colorg', 'colorb', 
                                                'r', 'g', 'b', 'scale', 'size', 'text'];
                            
                            if (commonParams.includes(lastPart)) {
                                parts.pop();
                            }
                            
                            return '/' + parts.join('/');
                        }
                        
                        return null;
                    }
                    
                    // Get parameter name from ID
                    function getParameterName(paramId) {
                        if (!paramId) return 'value';
                        
                        // If contains dashes, get last part
                        if (paramId.includes('-')) {
                            const parts = paramId.split('-');
                            return parts[parts.length - 1];
                        }
                        
                        return 'value';
                    }
                    
                    // Apply color parameter
                    function applyColorParameter(node, value) {
                        // Extract RGB values
                        const rgb = extractColorValue(value);
                        log('Extracted RGB values: [' + rgb.join(', ') + ']');
                        
                        // Try different color parameters
                        if (node.p.colorr && node.p.colorg && node.p.colorb) {
                            node.p.colorr.set(rgb[0]);
                            node.p.colorg.set(rgb[1]);
                            node.p.colorb.set(rgb[2]);
                            log('Set RGB components');
                            return true;
                        } else if (node.p.color) {
                            node.p.color.set(rgb);
                            log('Set color vector');
                            return true;
                        } else if (node.p.r && node.p.g && node.p.b) {
                            node.p.r.set(rgb[0]);
                            node.p.g.set(rgb[1]);
                            node.p.b.set(rgb[2]);
                            log('Set r,g,b components');
                            return true;
                        } else if (node.p.diffuse) {
                            node.p.diffuse.set(rgb);
                            log('Set diffuse color');
                            return true;
                        } else {
                            // Try to find any color-related parameter
                            for (const paramName in node.p) {
                                if ((typeof paramName === 'string' && paramName.toLowerCase().includes('color')) ||
                                    (typeof paramName === 'string' && paramName.toLowerCase() === 'diffuse')) {
                                    try {
                                        node.p[paramName].set(rgb);
                                        log('Set color via ' + paramName);
                                        return true;
                                    } catch (e) {
                                        // Try next parameter
                                    }
                                }
                            }
                        }
                        return false;
                    }
                    
                    // Extract color value from string, hex, etc.
                    function extractColorValue(value) {
                        // If already an array, use it
                        if (Array.isArray(value)) {
                            return value.map(v => v > 1 ? v / 255 : v);
                        }
                        
                        // If hex color
                        if (typeof value === 'string' && value.startsWith('#')) {
                            return hexToRgb(value);
                        }
                        
                        // If color name, use common colors
                        if (typeof value === 'string') {
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
                            
                            const lowerColor = typeof value === 'string' ? value.toLowerCase() : String(value).toLowerCase();
                            if (commonColors[lowerColor]) {
                                return commonColors[lowerColor];
                            }
                            
                            // Try to find in window.tdPolygonjs.colorOptions
                            if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
                                const colorKey = lowerColor.replace(/\\s+/g, '-');
                                if (window.tdPolygonjs.colorOptions[colorKey]) {
                                    const colorData = window.tdPolygonjs.colorOptions[colorKey];
                                    if (colorData.rgb) {
                                        return colorData.rgb;
                                    } else if (colorData.hex) {
                                        return hexToRgb(colorData.hex);
                                    }
                                }
                            }
                            
                            // Generate from hash as last resort
                            return generateColorFromString(value);
                        }
                        
                        // Default red
                        return [1, 0, 0];
                    }
                    
                    // Apply numeric parameter
                    function applyNumericParameter(node, value, paramId) {
                        const numValue = parseFloat(value);
                        if (isNaN(numValue)) return false;
                        
                        // Extract parameter name from paramId
                        const paramName = extractParameterName(paramId);
                        
                        // Try direct parameter
                        if (node.p[paramName] !== undefined) {
                            try {
                                node.p[paramName].set(numValue);
                                log('Set numeric parameter ' + paramName + ' to ' + numValue);
                                return true;
                            } catch (e) {
                                log('Error setting ' + paramName + ': ' + e.message);
                            }
                        }
                        
                        // Try common numeric parameters
                        const numericParams = ['value', 'val', 'size', 'scale', 'width', 'height', 'depth'];
                        for (const name of numericParams) {
                            if (node.p[name] !== undefined) {
                                try {
                                    node.p[name].set(numValue);
                                    log('Set ' + name + ' parameter to ' + numValue);
                                    return true;
                                } catch (e) {
                                    // Try next parameter
                                }
                            }
                        }
                        
                        // Try all parameters
                        for (const name in node.p) {
                            try {
                                node.p[name].set(numValue);
                                log('Set ' + name + ' parameter to ' + numValue);
                                return true;
                            } catch (e) {
                                // Try next parameter
                            }
                        }
                        
                        return false;
                    }
                    
                    // Apply text parameter
                    function applyTextParameter(node, value, paramId) {
                        // Extract parameter name from paramId
                        const paramName = extractParameterName(paramId);
                        
                        // Try direct parameter
                        if (node.p[paramName] !== undefined) {
                            try {
                                node.p[paramName].set(value);
                                log('Set text parameter ' + paramName);
                                return true;
                            } catch (e) {
                                log('Error setting ' + paramName + ': ' + e.message);
                            }
                        }
                        
                        // Try common text parameters
                        const textParams = ['text', 'string', 'value', 'input', 'label'];
                        for (const name of textParams) {
                            if (node.p[name] !== undefined) {
                                try {
                                    node.p[name].set(value);
                                    log('Set ' + name + ' parameter');
                                    return true;
                                } catch (e) {
                                    // Try next parameter
                                }
                            }
                        }
                        
                        // Try all parameters
                        for (const name in node.p) {
                            try {
                                node.p[name].set(value);
                                log('Set ' + name + ' parameter');
                                return true;
                            } catch (e) {
                                // Try next parameter
                            }
                        }
                        
                        return false;
                    }
                    
                    // Apply boolean parameter
                    function applyBooleanParameter(node, value, paramId) {
                        const boolValue = value === true || value === 1 || value === '1' || 
                                         value === 'yes' || value === 'true';
                        
                        // Extract parameter name from paramId
                        const paramName = extractParameterName(paramId);
                        
                        // Try direct parameter
                        if (node.p[paramName] !== undefined) {
                            try {
                                node.p[paramName].set(boolValue);
                                log('Set boolean parameter ' + paramName);
                                return true;
                            } catch (e) {
                                log('Error setting ' + paramName + ': ' + e.message);
                            }
                        }
                        
                        // Try common boolean parameters
                        const boolParams = ['value', 'enabled', 'visible', 'toggle', 'active'];
                        for (const name of boolParams) {
                            if (node.p[name] !== undefined) {
                                try {
                                    node.p[name].set(boolValue);
                                    log('Set ' + name + ' parameter');
                                    return true;
                                } catch (e) {
                                    // Try next parameter
                                }
                            }
                        }
                        
                        // Try all parameters
                        for (const name in node.p) {
                            try {
                                node.p[name].set(boolValue);
                                log('Set ' + name + ' parameter');
                                return true;
                            } catch (e) {
                                // Try next parameter
                            }
                        }
                        
                        return false;
                    }
                    
                    // Apply generic parameter
                    function applyGenericParameter(node, value, paramId) {
                        // Extract parameter name from paramId
                        const paramName = extractParameterName(paramId);
                        
                        // Try direct parameter
                        if (node.p[paramName] !== undefined) {
                            try {
                                node.p[paramName].set(value);
                                log('Set ' + paramName + ' parameter');
                                return true;
                            } catch (e) {
                                log('Error setting ' + paramName + ': ' + e.message);
                            }
                        }
                        
                        // Try 'value' parameter
                        if (node.p.value !== undefined) {
                            try {
                                node.p.value.set(value);
                                log('Set value parameter');
                                return true;
                            } catch (e) {
                                // Try other parameters
                            }
                        }
                        
                        // Try all parameters
                        for (const name in node.p) {
                            try {
                                node.p[name].set(value);
                                log('Set ' + name + ' parameter');
                                return true;
                            } catch (e) {
                                // Try next parameter
                            }
                        }
                        
                        return false;
                    }
                    
                    // Extract parameter name from paramId
                    function extractParameterName(paramId) {
                        if (!paramId) return 'value';
                        
                        // If contains dashes, get last part
                        if (paramId.includes('-')) {
                            const parts = paramId.split('-');
                            return parts[parts.length - 1];
                        }
                        
                        return 'value';
                    }
                    
                    // Convert hex to RGB
                    function hexToRgb(hex) {
                        hex = hex.replace(/^#/, '');
                        
                        let r, g, b;
                        if (hex.length === 3) {
                            r = parseInt(hex.charAt(0) + hex.charAt(0), 16) / 255;
                            g = parseInt(hex.charAt(1) + hex.charAt(1), 16) / 255;
                            b = parseInt(hex.charAt(2) + hex.charAt(2), 16) / 255;
                        } else {
                            r = parseInt(hex.substr(0, 2), 16) / 255;
                            g = parseInt(hex.substr(2, 2), 16) / 255;
                            b = parseInt(hex.substr(4, 2), 16) / 255;
                        }
                        
                        return [r, g, b];
                    }
                    
                    // Generate color from string
                    function generateColorFromString(str) {
                        let hash = 0;
                        for (let i = 0; i < str.length; i++) {
                            hash = str.charCodeAt(i) + ((hash << 5) - hash);
                        }
                        hash = Math.abs(hash);
                        
                        const r = ((hash & 0xFF0000) >> 16) / 255;
                        const g = ((hash & 0x00FF00) >> 8) / 255;
                        const b = (hash & 0x0000FF) / 255;
                        
                        return [r, g, b];
                    }
                    
                    // Find PolygonJS scene with multiple fallbacks
                    function findPolygonScene() {
                        // Method 1: Check window.polygonjs.scene
                        if (window.polygonjs && window.polygonjs.scene) {
                            log('Found scene at window.polygonjs.scene');
                            return window.polygonjs.scene;
                        }
                        
                        // Method 2: Check window.polyScene
                        if (window.polyScene) {
                            log('Found scene at window.polyScene');
                            return window.polyScene;
                        }
                        
                        // Method 3: Check for any properly named variable
                        for (const key in window) {
                            if (typeof key === 'string' && key.toLowerCase().includes('scene') && 
                                window[key] && typeof window[key] === 'object' &&
                                typeof window[key].node === 'function') {
                                log('Found scene in global window.' + key);
                                return window[key];
                            }
                        }
                        
                        // Method 4: If any of the above "PolygonJS" variables are objects with a root function
                        if (window.polygonjs && typeof window.polygonjs.root === 'function') {
                            log('Found scene at window.polygonjs');
                            return window.polygonjs;
                        }
                        if (window.polyScene && typeof window.polyScene.root === 'function') {
                            log('Found scene at window.polyScene');
                            return window.polyScene;
                        }
                        
                        // Method 5: Check if TDPolygonjs bridge is initialized
                        if (window.TDPolygonjs && typeof window.TDPolygonjs.getScene === 'function') {
                            const scene = window.TDPolygonjs.getScene();
                            if (scene) {
                                log('Found scene via TDPolygonjs.getScene()');
                                return scene;
                            }
                        }
                        
                        log('Scene not found');
                        return null;
                    }
                    
                    // Get all nodes in scene
                    function getAllSceneNodes(scene) {
                        const nodes = [];
                        
                        function collectNodes(node) {
                            if (!node) return;
                            
                            nodes.push(node);
                            
                            // Check for children
                            if (node.children) {
                                for (const child of node.children) {
                                    collectNodes(child);
                                }
                            }
                        }
                        
                        try {
                            if (scene.root) {
                                const root = scene.root();
                                collectNodes(root);
                            }
                        } catch (e) {
                            log('Error collecting nodes: ' + e.message);
                        }
                        
                        return nodes;
                    }
                    
                    // Handle model download
                    function handleDownload(format, filename) {
                        const scene = findPolygonScene();
                        if (!scene) {
                            log('Cannot download, scene not found');
                            window.parent.postMessage({
                                type: 'download_failed',
                                error: 'Scene not found'
                            }, '*');
                            return;
                        }
                        
                        try {
                            // Try to find exporter node
                            let exporterNode = null;
                            
                            // Common paths
                            const paths = [
                                '/doos/exporterGLTF1',
                                '/geo1/exporterGLTF1', 
                                '/case/exporterGLTF1',
                                '/root/exporterGLTF1',
                                '/geo2/exporterGLTF1',
                                '/box/exporterGLTF1'
                            ];
                            
                            // Try each path
                            for (const path of paths) {
                                try {
                                    const node = scene.node(path);
                                    if (node) {
                                        exporterNode = node;
                                        log('Found exporter node at: ' + path);
                                        break;
                                    }
                                } catch (e) {}
                            }
                            
                            // If not found, search for exporter in all nodes
                            if (!exporterNode) {
                                log('Searching for exporter node in all scene nodes');
                                const allNodes = getAllSceneNodes(scene);
                                for (const node of allNodes) {
                                    if (node.name && typeof node.name === 'string' && node.name.toLowerCase().includes('exporter')) {
                                        exporterNode = node;
                                        log('Found exporter node by name search: ' + node.path());
                                        break;
                                    }
                                }
                            }
                            
                            if (!exporterNode) {
                                log('No exporter node found');
                                window.parent.postMessage({
                                    type: 'download_failed',
                                    error: 'No exporter node found'
                                }, '*');
                                return;
                            }
                            
                            // Set filename if it exists
                            if (filename && exporterNode.p.fileName) {
                                exporterNode.p.fileName.set(filename);
                                log('Set export filename to: ' + filename);
                            }
                            
                            // Trigger download
                            if (exporterNode.p.trigger) {
                                exporterNode.p.trigger.pressButton();
                                log('Download triggered for ' + filename);
                                
                                window.parent.postMessage({
                                    type: 'download_started',
                                    format: format
                                }, '*');
                            } else if (exporterNode.p.download) {
                                exporterNode.p.download.pressButton();
                                log('Download triggered using download button for ' + filename);
                                
                                window.parent.postMessage({
                                    type: 'download_started',
                                    format: format
                                }, '*');
                            } else {
                                log('Exporter has no trigger or download parameter');
                                window.parent.postMessage({
                                    type: 'download_failed',
                                    error: 'Exporter has no trigger parameter'
                                }, '*');
                            }
                        } catch (e) {
                            log('Error triggering download: ' + e.message);
                            window.parent.postMessage({
                                type: 'download_failed',
                                error: e.message
                            }, '*');
                        }
                    }
                    
                    // Debug logging
                    function log(message) {
                        if (DEBUG) {
                            console.log('[TD Model Viewer]', message);
                        }
                    }
                    
                    // Notify parent we're ready
                    window.parent.postMessage({ 
                        type: 'td_injected_script_ready',
                        source: 'td_parameter_handler'
                    }, '*');
                    
                    // Listen for native scene ready events directly on window
                    // These might be triggered before our message handler is set up
                    document.addEventListener('sceneReady', function() {
                        log('Native document sceneReady event fired');
                        window.parent.postMessage({ type: 'sceneReady' }, '*');
                    });
                    
                    document.addEventListener('modelReady', function() {
                        log('Native document modelReady event fired');
                        window.parent.postMessage({ type: 'modelReady' }, '*');
                    });
                    
                    // Check for the scene and attempt to apply parameters automatically
                    function checkSceneAndApplyParameters() {
                        const scene = findPolygonScene();
                        if (scene) {
                            window.parent.postMessage({ 
                                type: 'td_polygonjs_ready', 
                                scene: true,
                                source: 'td_parameter_handler'
                            }, '*');
                            
                            // If we have pending parameters, apply them
                            if (pendingParameters) {
                                log('Automatically applying pending parameters');
                                applyParameters();
                            }
                        } else {
                            // Try again in 500ms
                            setTimeout(checkSceneAndApplyParameters, 500);
                        }
                    }
                    
                    // Repeatedly check for scene loading
                    let sceneCheckCount = 0;
                    const maxSceneChecks = 20;
                    const sceneCheckInterval = setInterval(() => {
                        sceneCheckCount++;
                        const scene = findPolygonScene();
                        
                        if (scene) {
                            clearInterval(sceneCheckInterval);
                            log('Scene found after ' + sceneCheckCount + ' attempts');
                            
                            window.parent.postMessage({
                                type: 'td_polygonjs_ready',
                                scene: true,
                                source: 'td_parameter_handler'
                            }, '*');
                            
                            if (pendingParameters) {
                                log('Applying pending parameters');
                                applyParameters();
                            }
                        } else if (sceneCheckCount >= maxSceneChecks) {
                            clearInterval(sceneCheckInterval);
                            log('Failed to find scene after ' + maxSceneChecks + ' attempts');
                            
                            // Notify parent even when we can't find the scene, so the loader disappears
                            window.parent.postMessage({
                                type: 'td_polygonjs_ready',
                                scene: false,
                                error: 'Scene not found',
                                source: 'td_parameter_handler'
                            }, '*');
                        } else {
                            log('Scene check attempt ' + sceneCheckCount + '/' + maxSceneChecks);
                        }
                    }, 500);
                    
                    // Also check for global load events
                    window.addEventListener('load', () => {
                        log('Window load event fired');
                        // Wait a moment after load to allow scene initialization
                        setTimeout(() => {
                            const scene = findPolygonScene();
                            if (scene) {
                                log('Scene found after window load');
                                window.parent.postMessage({
                                    type: 'td_polygonjs_ready',
                                    scene: true,
                                    source: 'td_parameter_handler'
                                }, '*');
                            }
                        }, 1000);
                    });
                })();
            `;
        }

        /**
         * Generate unified sync script if available
         */
        function generateUnifiedSyncScript() {
            // Use the unified sync script generated by PHP
            if (tdModelViewerData.unified_sync_script) {
                return tdModelViewerData.unified_sync_script;
            }
            return '// No unified sync script available';
        }

        // Listen for messages from the iframe
        window.addEventListener('message', function(event) {
            // Verify the message structure
            if (!event.data || typeof event.data !== 'object') {
                return;
            }
            
            console.log('Received message from iframe:', event.data.type);
            
            // Check message source
            const isFromInjectedScript = event.data.source === 'td_parameter_handler';
            
            // Handle script ready message
            if (event.data.type === 'td_injected_script_ready') {
                console.log('Injected parameter handler is ready');
            }
            
            // Handle PolygonJS ready message
            if (event.data.type === 'td_polygonjs_ready') {
                polygonJsReady = true;
                console.log('✅ Received ready message from PolygonJS');
                
                // Hide loader when PolygonJS is actually ready
                $('#td-iframe-loader').fadeOut();
                
                // Apply parameters if we haven't already
                if (pendingParameters) {
                    applyParameters();
                }
            }
            
            // Also listen for the native PolygonJS 'sceneReady' and 'modelReady' messages
            if (event.data.type === 'sceneReady' || event.data.type === 'modelReady') {
                console.log('✅ Received ' + event.data.type + ' message from PolygonJS');
                
                // Hide loader when model is ready
                $('#td-iframe-loader').fadeOut();
                
                // Only apply parameters once
                if (event.data.type === 'modelReady' && pendingParameters) {
                    console.log('Applying parameters after model ready');
                    
                    // Force iframeLoaded to true since we know the model is ready
                    iframeLoaded = true;
                    
                    // Apply parameters with a short delay to ensure model is fully ready
                    setTimeout(applyParameters, 500);
                }
            }
            
            // Handle parameter application confirmation
            if (event.data.type === 'parameters_applied') {
                console.log('✅ Parameters applied successfully');
            }
            
            // Handle download events
            if (event.data.type === 'download_started') {
                console.log('👍 Download started:', event.data.format);
            }
            else if (event.data.type === 'download_failed') {
                console.error('❌ Download failed:', event.data.error);
                alert(tdModelViewerData.strings.download_failed + event.data.error);
                
                // Re-enable download button
                $('#td-download-current-view').prop('disabled', false)
                    .html('<span class="dashicons dashicons-download"></span> ' + tdModelViewerData.strings.download);
            }
        });
        
        // Apply parameters with retry logic
        function applyParameters() {
            // Don't reapply if already done
            if (!pendingParameters) {
                return;
            }
            
            // Get the iframe element
            const iframeElement = document.getElementById('td-model-iframe');
            
            // Check if we can access the iframe
            if (!iframeElement || !iframeElement.contentWindow) {
                console.log('Cannot access iframe, delaying parameter application');
                
                // Retry with exponential backoff
                retryCount++;
                
                if (retryCount <= maxRetries) {
                    setTimeout(applyParameters, Math.min(500 * Math.pow(1.2, retryCount), 5000));
                } else {
                    console.error('Failed to apply parameters - cannot access iframe');
                }
                
                return;
            }
            
            console.log('Applying parameters to PolygonJS scene:', Object.keys(pendingParameters).length, 'parameters');
            
            // Send parameters to the iframe using universal parameter system
            if (iframeElement && iframeElement.contentWindow) {
                try {
                    console.log('Applying parameters using universal parameter system');
                    
                    // Get the universal mapping for this product
                    const universalMapping = tdModelViewerData.universal_mapping || {};
                    
                    // Debug logging for parameter analysis
                    console.log('Raw parameters for product ' + tdModelViewerData.product_id + ':', tdModelViewerData.raw_parameters);
                    console.log('Generated universal mapping:', universalMapping);
                    console.log('Universal mapping node_mappings keys:', Object.keys(universalMapping.node_mappings || {}));
                    
                    // Send the universal mapping data to the iframe first
                    iframeElement.contentWindow.postMessage({
                        type: 'td_set_universal_mapping',
                        mapping: universalMapping
                    }, '*');
                    
                    // Send color options to the iframe
                    iframeElement.contentWindow.postMessage({
                        type: 'td_set_color_options',
                        colorOptions: tdModelViewerData.color_options || {}
                    }, '*');
                    
                    // Wait a bit to ensure color options are processed before sending parameters
                    setTimeout(() => {
                        // Send the parameters using the universal system
                        iframeElement.contentWindow.postMessage({
                            type: 'td_apply_parameters',
                            parameters: pendingParameters,
                            universalMapping: universalMapping
                        }, '*');
                    }, 100); // 100ms delay to ensure color options are received first
                    
                    console.log('Sent parameters and universal mapping to iframe:', {
                        parameters: Object.keys(pendingParameters).length,
                        mappings: Object.keys(universalMapping.node_mappings || {}).length,
                        colorMappings: Object.keys(universalMapping.color_mappings || {}).length
                    });
                    
                    // Legacy fallback - send each parameter individually
                    setTimeout(() => {
                        sendParametersIndividually(iframeElement, pendingParameters);
                        pendingParameters = null; // Clear to prevent duplicate sends
                    }, 200);
                    
                } catch (e) {
                    console.error('Error sending parameters:', e);
                    
                    // Retry if possible
                    retryCount++;
                    if (retryCount <= maxRetries) {
                        console.log(`Will retry sending parameters (attempt ${retryCount}/${maxRetries})`);
                        setTimeout(applyParameters, 1000);
                    }
                }
            } else {
                console.warn('⚠️ Could not access iframe contentWindow');
                
                // Retry if possible
                retryCount++;
                if (retryCount <= maxRetries) {
                    setTimeout(applyParameters, 1000);
                }
            }
        }

        /**
         * Send parameters individually for legacy support
         */
        function sendParametersIndividually(iframeElement, parameters) {
            let paramsSent = 0;
            
            Object.keys(parameters).forEach(paramId => {
                const param = parameters[paramId];
                if (!param || !param.value) return;
                
                // Extract node info
                let nodePath = '';
                let paramName = '';
                
                // Try to get correct path from synced parameter data
                if (window.tdSyncedParameters && window.tdSyncedParameters[paramId]) {
                    const syncedMapping = window.tdSyncedParameters[paramId];
                    nodePath = syncedMapping.actual_node_path;
                    paramName = syncedMapping.actual_param_name;
                    console.log('✅ Universal: Using synced parameter mapping for', paramId, ':', nodePath, paramName);
                }
                
                // Fallback: Extract node path
                if (!nodePath) {
                    if (param.node_id) {
                        nodePath = '/' + param.node_id.replace(/-/g, '/');
                    } else if (paramId.includes('-')) {
                        const parts = paramId.split('-');
                        const lastPart = parts[parts.length - 1].toLowerCase();
                        
                        // Common parameter names to remove from path
                        const commonParams = ['value', 'color', 'colorr', 'colorg', 'colorb', 
                                            'r', 'g', 'b', 'scale', 'size', 'text'];
                        
                        if (commonParams.includes(lastPart)) {
                            parts.pop();
                        }
                        
                        nodePath = '/' + parts.join('/');
                    }
                }
                
                if (!nodePath) return;
                
                // Format the value based on type
                let value = param.value;
                
                // For colors, handle RGB values
                if (param.control_type === 'color') {
                    if (typeof value === 'string' && !value.startsWith('#')) {
                        // Try to get the color from global colors
                        const colorKey = value.toLowerCase().replace(/\s+/g, '-');
                        if (window.tdPolygonjs && window.tdPolygonjs.colorOptions && 
                            window.tdPolygonjs.colorOptions[colorKey]) {
                            value = window.tdPolygonjs.colorOptions[colorKey].rgb || [1, 0, 0];
                        } else {
                            console.log('Using color name directly:', value);
                        }
                    }
                }
                
                // For numeric values, ensure they are actually numbers
                if ((param.control_type === 'number' || param.control_type === 'slider') && param.control_type !== 'color') {
                    value = parseFloat(value);
                }
                
                // Extract parameter name
                if (!paramName) {
                    if (param.param_name) {
                        paramName = param.param_name;
                    } else if (paramId.includes('-')) {
                        const parts = paramId.split('-');
                        paramName = parts[parts.length - 1];
                    } else {
                        paramName = 'value';
                    }
                }
                
                // Fix for parameters duplicated in path and name
                const pathParts = nodePath.split('/');
                const lastSegment = pathParts[pathParts.length - 1];
                if (lastSegment && typeof lastSegment === 'string' && typeof paramName === 'string' && lastSegment.toLowerCase() === paramName.toLowerCase()) {
                    console.log('Found duplicated parameter in path:', lastSegment);
                    // Remove the duplicated parameter from the path
                    pathParts.pop();
                    nodePath = pathParts.join('/');
                    console.log('Corrected path to:', nodePath);
                }
                
                // Apply universal scene mappings
                const pathMappings = {
                    // Doosje scene mappings
                    '/doos/mat/colorbox': '/doos/MAT/colorBox',
                    '/doos/mat/colorlid': '/doos/MAT/colorLid',
                    '/doos/mat/colortekst': '/doos/MAT/colorTekst',
                    '/doos/ctrl_doos/breedte': '/doos/ctrl_doos',
                    '/doos/ctrl_doos/diepte': '/doos/ctrl_doos',
                    '/doos/ctrl_doos/hoogte': '/doos/ctrl_doos',
                    '/doos/ctrl_doos/dikte_wanden': '/doos/ctrl_doos',
                    '/doos/ctrl_doos/dikte_bodem': '/doos/ctrl_doos',
                    '/doos/ctrl_doos/deksel_dikte': '/doos/ctrl_doos',
                    '/doos/ctrl_doos/tekst': '/doos/ctrl_doos',
                    '/doos/ctrl_doos/tekst_schaal': '/doos/ctrl_doos',
                    
                    // Sleutelhoes scene mappings (from main.js lines 146-166)
                    '/sleutelhoes/ctrl/width': '/sleutelhoes/CTRL',
                    '/sleutelhoes/ctrl/height': '/sleutelhoes/CTRL',
                    '/sleutelhoes/ctrl/length': '/sleutelhoes/CTRL',
                    '/sleutelhoes/mat/meshstandard1': '/sleutelhoes/MAT/meshStandard1',
                    
                    // Bloempot (flowerpot) scene mappings
                    '/geo1/mat/meshstandard1': '/geo1/MAT/meshStandard1',
                    '/geo1/cadcone1': '/geo1/CADCone1'
                };
                
                if (pathMappings[nodePath]) {
                    nodePath = pathMappings[nodePath];
                    console.log('Applied path mapping:', paramId, '->', nodePath);
                }
                
                // Special handling for colors
                if (param.control_type === 'color' && typeof value === 'string') {
                    const colorKey = value.toLowerCase().replace(/\s+/g, '-');
                    
                    if (window.tdSyncedColors && window.tdSyncedColors[colorKey]) {
                        const rgbValues = window.tdSyncedColors[colorKey];
                        console.log('Using synced RGB values for color', value, rgbValues);
                        value = rgbValues;
                    }
                }
                
                console.log('Sending parameter to PolygonJS:', {
                    path: nodePath,
                    param: paramName,
                    value: value,
                    type: typeof value
                });
                
                // Send the parameter using native format
                iframeElement.contentWindow.postMessage({
                    type: 'updateParameter',
                    nodeId: nodePath,
                    paramName: paramName,
                    value: value
                }, '*');
                
                paramsSent++;
            });
            
            console.log(`Sent ${paramsSent} parameters to iframe using native format`);
        }
        
        // Handle download button
        $('#td-download-current-view').on('click', function() {
            const iframeEl = document.getElementById('td-model-iframe');
            if (!iframeEl || !iframeEl.contentWindow) return;
            
            // Show loading indicator
            $(this).prop('disabled', true).html('<span class="dashicons dashicons-update fa-spin"></span> ' + tdModelViewerData.strings.downloading);
            
            try {
                // Try to access the PolygonJS scene directly and trigger the exporter
                if (iframeEl.contentWindow.polyScene || 
                    (iframeEl.contentWindow.polygonjs && iframeEl.contentWindow.polygonjs.scene)) {
                    
                    const scene = iframeEl.contentWindow.polyScene || iframeEl.contentWindow.polygonjs.scene;
                    console.log('Found PolygonJS scene, looking for exporter node');
                    
                    // Try scene-specific path first, then common paths
                    const exporterPaths = [
                        '/' + tdModelViewerData.scene_name + '/exporterGLTF1',
                        '/doos/exporterGLTF1',
                        '/geo1/exporterGLTF1',
                        '/geo2/exporterGLTF1',
                        '/case/exporterGLTF1',
                        '/root/exporterGLTF1',
                        '/box/exporterGLTF1'
                    ];
                    
                    let exporterFound = false;
                    for (const path of exporterPaths) {
                        try {
                            const exporter = scene.node(path);
                            if (exporter) {
                                console.log('Found exporter at:', path);
                                
                                // Set filename if possible
                                if (exporter.p && exporter.p.fileName) {
                                    const filename = tdModelViewerData.scene_name + '_' + tdModelViewerData.model_id;
                                    exporter.p.fileName.set(filename);
                                    console.log('Set filename to:', filename);
                                }
                                
                                // Trigger download - try both trigger and download parameters
                                if (exporter.p && exporter.p.trigger) {
                                    console.log('Triggering download via trigger parameter');
                                    exporter.p.trigger.pressButton();
                                    exporterFound = true;
                                } else if (exporter.p && exporter.p.download) {
                                    console.log('Triggering download via download parameter');
                                    exporter.p.download.pressButton();
                                    exporterFound = true;
                                } else {
                                    console.log('Exporter node has no trigger or download parameter, checking all parameters');
                                    // Try to find any parameter that might trigger the download
                                    if (exporter.p) {
                                        for (const paramName in exporter.p) {
                                            if (paramName.toLowerCase().includes('trigger') || 
                                                paramName.toLowerCase().includes('download') || 
                                                paramName.toLowerCase().includes('export')) {
                                                console.log('Trying parameter:', paramName);
                                                if (typeof exporter.p[paramName].pressButton === 'function') {
                                                    exporter.p[paramName].pressButton();
                                                    exporterFound = true;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                if (exporterFound) break;
                            }
                        } catch (e) {
                            console.log('Error accessing exporter at', path, ':', e);
                        }
                    }
                    
                    if (!exporterFound) {
                        // Fallback: Try to find and click the hidden download button in the iframe
                        console.log('Exporter node approach failed, trying to find hidden download button');
                        
                        const iframeDoc = iframeEl.contentDocument || iframeEl.contentWindow.document;
                        
                        // Look for any hidden button
                        const hiddenButtons = iframeDoc.querySelectorAll('button');
                        for (const button of hiddenButtons) {
                            const style = window.getComputedStyle(button);
                            if (style.display === 'none' || button.style.display === 'none') {
                                console.log('Found hidden button, clicking it');
                                button.click();
                                exporterFound = true;
                                break;
                            }
                        }
                        
                        if (!exporterFound) {
                            console.error('Could not find any way to trigger download');
                            alert(tdModelViewerData.strings.download_failed);
                        }
                    }
                    
                    // Reset button
                    setTimeout(() => {
                        $('#td-download-current-view').prop('disabled', false)
                            .html('<span class="dashicons dashicons-download"></span> ' + tdModelViewerData.strings.download);
                    }, 2000);
                } else {
                    console.error('Could not access PolygonJS scene in iframe');
                    alert(tdModelViewerData.strings.scene_access_failed);
                    
                    // Reset button
                    $('#td-download-current-view').prop('disabled', false)
                        .html('<span class="dashicons dashicons-download"></span> ' + tdModelViewerData.strings.download);
                }
            } catch (error) {
                console.error('Error triggering download:', error);
                
                // Reset button
                $('#td-download-current-view').prop('disabled', false)
                    .html('<span class="dashicons dashicons-download"></span> ' + tdModelViewerData.strings.download);
            }
        });
    });

})(jQuery);