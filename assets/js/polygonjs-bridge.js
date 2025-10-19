/**
 * TD Link PolygonJS Bridge
 * 
 * Connects WordPress UI controls with PolygonJS scenes
 * with enhanced error handling and debugging
 */
(function($) {
    // Store PolygonJS scene reference
    let polyScene = null;
    let bridgeInitialized = false;
    
    // Debug mode
    const urlParams = new URLSearchParams(window.location.search);
    let debugMode = urlParams.has('debug');
    
    // Control cache for direct parameter access
    const controlCache = {};
    
    // RGB group cache for synchronizing color components
    const rgbGroupCache = {};
    
    // Scene tracking data
    let sceneData = {};
    let lastSceneReadyMessage = null;
    
    // Clear any previous instance of the bridge
    if (window.TDPolygonjs) {
        logDebug('Clearing previous bridge instance');
    }
    
    // Create the public API for the bridge
    window.TDPolygonjs = {
        /**
         * Initialize the bridge with a PolygonJS scene
         * This is called from the PolygonJS scene when it's loaded
         */
        initBridge: function(scene, options) {
            // Check if we're re-initializing with the same product
            const productId = options && options.productId || (window.tdPolygonjs && window.tdPolygonjs.product_id);
            
            if (bridgeInitialized && polyScene) {
                // Check if this is the same product/scene
                if (this.isSceneLoaded(productId)) {
                    logDebug('Bridge already initialized with same product, reusing cached scene');
                    // Fire ready event with cached data
                    $(document).trigger('td_polygonjs_bridge_ready', [scene, sceneData]);
                    return;
                } else {
                    logDebug('Bridge already initialized but different product, reinitializing');
                    this.clearSceneCache();
                }
            }
            
            polyScene = scene;
            debugMode = options && options.debug || debugMode;
            
            // Store product ID in scene data
            if (productId) {
                sceneData.productId = productId;
            }
            
            logDebug('🌉 Bridge initialized with scene for product:', productId);
            
            // Connect UI controls to the scene
            connectControls();
            
            // Register message listener for iframe communication
            setupMessageListener();
            
            // Apply initial values from WordPress controls
            setTimeout(() => {
                this.applyInitialValues(scene);
            }, 100);
            
            // Signal that the bridge is ready
            $(document).trigger('td_polygonjs_bridge_ready', [scene, sceneData]);
            
            bridgeInitialized = true;
        },
        
        /**
         * Apply initial values from all controls
         */
        applyInitialValues: function(scene) {
            if (!scene) return;
            
            logDebug('Applying initial values from WordPress controls');
            
            // First apply non-color controls
            for (const nodeId in controlCache) {
                const control = controlCache[nodeId];
                if (!control.element) continue;
                
                const $control = $(control.element);
                
                // Skip color controls for now
                if ($control.hasClass('color-radio')) continue;
                
                let value;
                if ($control.is(':checkbox')) {
                    value = $control.prop('checked');
                } else {
                    value = $control.val();
                }
                
                if (value !== undefined && value !== null) {
                    const controlType = $control.attr('type') || $control.prop('tagName').toLowerCase();
                    this.updateNodeParameter(nodeId, controlType, value);
                    logDebug(`Applied initial value for ${nodeId}: ${value}`);
                }
            }
            
            // Then apply color controls
            $('.color-swatch.active input[type="radio"]').each((index, element) => {
                const $radio = $(element);
                const colorKey = $radio.val();
                const $container = $radio.closest('[data-node-id]');
                const nodeId = $container.data('node-id');
                
                if (!nodeId) return;
                
                logDebug(`Applying initial color for ${nodeId}: ${colorKey}`);
                
                // Trigger change event to apply the color
                $radio.trigger('change');
            });
            
            // Force scene update
            try {
                if (scene.root && typeof scene.root().compute === 'function') {
                    scene.root().compute();
                    logDebug('Scene computed after initial value application');
                }
            } catch (e) {
                logDebug('Error computing scene:', e);
            }
        },
        
        /**
         * Update a node parameter directly
         * Can be called from external code
         * Enhanced with special pattern detection
         */
        updateNodeParameter: function(nodeId, paramName, value) {
            logDebug('Direct update request:', nodeId, paramName, value);
            
            if (!polyScene) {
                logDebug('⚠️ PolygonJS scene not available');
                return false;
            }
            
            // ===== SPECIAL HANDLING FOR DOT NOTATION FORMAT =====
            
            // Check if nodeId contains a dot notation parameter (e.g., "/doos/mat/colorbox.colorr")
            if (nodeId.includes('.')) {
                const parts = nodeId.split('.');
                const pathPart = parts[0]; // Path before the dot
                let paramPart = parts[1]; // Parameter after the dot
                
                logDebug('Detected dot notation format:', pathPart, paramPart);
                
                // Extract the scene name from URL to handle special cases
                const urlParams = new URLSearchParams(window.location.search);
                const sceneName = urlParams.get("scene") || "";
                
                // Special handling for doosje scene
                if (sceneName === 'doosje') {
                    // Define mappings for known paths with corrections
                    const pathMappings = {
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
                        '/doos/ctrl_doos/tekst_schaal': '/doos/ctrl_doos'
                    };
                    
                    // Check if we have a mapping for this path
                    if (pathMappings[pathPart]) {
                        // Use the corrected path 
                        const correctedPath = pathMappings[pathPart];
                        
                        // For ctrl_doos parameters, the parameter name is the last part of the path
                        if (pathPart.startsWith('/doos/ctrl_doos/')) {
                            paramPart = pathPart.split('/').pop();
                        }
                        
                        logDebug('Using corrected path and param:', correctedPath, paramPart);
                        
                        // Try to update with the corrected path and parameter directly
                        const result = updateByDirectNodePath(correctedPath, paramPart, value);
                        if (result) {
                            return true;
                        }
                    }
                }
                
                // If no specific mapping or not successful, continue with general handling
                // but still split the path and parameter correctly
                return updateByDirectNodePath(pathPart, paramPart, value) || 
                       updateByExtractedPath(pathPart, paramPart, value) || 
                       updateByHTMLSnippet(pathPart, value);
            }
            
            // Special handling for RGB color updates
            if (paramName === 'colorr' && Array.isArray(value) && value.length === 3) {
                logDebug('RGB color update detected:', value);
                
                // Convert node ID to path (e.g., "geo2-MAT-meshStandard1-colorr" -> "/geo2/MAT/meshStandard1")
                let nodePath;
                
                if (nodeId.startsWith('/')) {
                    // Already a path
                    nodePath = nodeId;
                    
                    // Special handling for doosje scene
                    const urlParams = new URLSearchParams(window.location.search);
                    const sceneName = urlParams.get("scene") || "";
                    
                    if (sceneName === 'doosje') {
                        // Comprehensive path mapping for doosje scene
                        const doosPathMap = {
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
                            '/doos/ctrl_doos/tekst_schaal': '/doos/ctrl_doos'
                        };
                        
                        if (doosPathMap[nodeId]) {
                            nodePath = doosPathMap[nodeId];
                            logDebug('Corrected doosje path:', nodeId, '->', nodePath);
                        }
                    }
                } else if (nodeId.includes('-')) {
                    // Convert dashed ID to path, but remove the last part which is the param
                    const parts = nodeId.split('-');
                    if (parts[parts.length - 1] === 'colorr') {
                        parts.pop(); // Remove the colorr part
                    }
                    nodePath = '/' + parts.join('/');
                } else {
                    logDebug('⚠️ Could not extract node path from:', nodeId);
                    return false;
                }
                
                logDebug('Extracted path for RGB update:', nodePath);
                
                try {
                    const node = polyScene.node(nodePath);
                    if (!node || !node.p) {
                        logDebug('⚠️ Node not found or has no parameters:', nodePath);
                        return false;
                    }
                    
                    // Update all three RGB components
                    if (node.p.colorr) {
                        node.p.colorr.set(value[0]);
                        logDebug('Set colorr to:', value[0]);
                    } else {
                        logDebug('⚠️ Node has no colorr parameter');
                    }
                    
                    if (node.p.colorg) {
                        node.p.colorg.set(value[1]);
                        logDebug('Set colorg to:', value[1]);
                    } else {
                        logDebug('⚠️ Node has no colorg parameter');
                    }
                    
                    if (node.p.colorb) {
                        node.p.colorb.set(value[2]);
                        logDebug('Set colorb to:', value[2]);
                    } else {
                        logDebug('⚠️ Node has no colorb parameter');
                    }
                    
                    return true;
                } catch (error) {
                    logDebug('❌ Error setting RGB color:', error);
                    return false;
                }
            }
            
            // Handle RGB color groups from mapped components
            if (window.tdPolygonjs && window.tdPolygonjs.rgbGroups) {
                // Check if this is part of an RGB group
                for (const groupId in window.tdPolygonjs.rgbGroups) {
                    const group = window.tdPolygonjs.rgbGroups[groupId];
                    
                    // If this is an RGB component and the value is a color name
                    // Allow matching either by exact ID or by normalized path (for case variations)
                    const normalizedNodeId = typeof nodeId === 'string' ? nodeId.toLowerCase().replace(/-/g, '/').replace(/^\//, '') : '';
                    const normalizedGroupR = typeof group.components.r === 'string' ? group.components.r.toLowerCase().replace(/-/g, '/').replace(/^\//, '') : '';
                    
                    if (group.components.r === nodeId || normalizedNodeId === normalizedGroupR) {
                        // Try to get RGB values for the selected color
                        const colorKey = typeof value === 'string' ? value.toLowerCase().replace(/\s+/g, '-') : String(value).toLowerCase().replace(/\s+/g, '-');
                        if (window.tdPolygonjs.colorOptions && window.tdPolygonjs.colorOptions[colorKey]) {
                            // Get RGB values
                            const rgb = window.tdPolygonjs.colorOptions[colorKey].rgb;
                            
                            // Update all RGB components
                            if (rgb && rgb.length === 3) {
                                // Update R component
                                const rPath = convertNodeIdToPath(group.components.r, 'colorr').path;
                                logDebug('Updating R component:', rPath, 'colorr', rgb[0]);
                                updateByDirectNodePath(rPath, 'colorr', rgb[0]);
                                
                                // Update G component
                                const gPath = convertNodeIdToPath(group.components.g, 'colorg').path;
                                logDebug('Updating G component:', gPath, 'colorg', rgb[1]);
                                updateByDirectNodePath(gPath, 'colorg', rgb[1]);
                                
                                // Update B component
                                const bPath = convertNodeIdToPath(group.components.b, 'colorb').path;
                                logDebug('Updating B component:', bPath, 'colorb', rgb[2]);
                                updateByDirectNodePath(bPath, 'colorb', rgb[2]);
                                
                                return true;
                            }
                        } else if (Array.isArray(value) && value.length === 3) {
                            // If value is already an RGB array
                            // Update R component
                            updateByDirectNodePath(
                                convertNodeIdToPath(group.components.r, 'colorr').path,
                                'colorr',
                                value[0]
                            );
                            
                            // Update G component
                            updateByDirectNodePath(
                                convertNodeIdToPath(group.components.g, 'colorg').path,
                                'colorg',
                                value[1]
                            );
                            
                            // Update B component
                            updateByDirectNodePath(
                                convertNodeIdToPath(group.components.b, 'colorb').path,
                                'colorb',
                                value[2]
                            );
                            
                            return true;
                        }
                    }
                }
            }
            
            // Standard parameter updates (non-RGB)
            try {
                // Try different methods to update the parameter
                return updateByDirectNodePath(convertNodeIdToPath(nodeId, paramName).path, paramName, value) || 
                       updateByExtractedPath(nodeId, paramName, value) || 
                       updateByHTMLSnippet(nodeId, value);
            } catch (error) {
                logDebug('❌ Error in updateNodeParameter:', error);
                return false;
            }
        },
        
        /**
         * Check if the bridge is initialized
         */
        isInitialized: function() {
            return bridgeInitialized;
        },
        
        /**
         * Get the PolygonJS scene
         */
        getScene: function() {
            return polyScene;
        },
        
        /**
         * Get the configured exporter node path
         * Retrieves the path from tdPolygonjs data or fallback to common paths
         */
        getExporterNodePath: function() {
            // First check if we have a configured path from WordPress
            if (window.tdPolygonjs && window.tdPolygonjs.exporterNodePath) {
                logDebug('Using configured exporter node path:', window.tdPolygonjs.exporterNodePath);
                return window.tdPolygonjs.exporterNodePath;
            }
            
            // Otherwise, try to detect the scene name from URL
            const urlParams = new URLSearchParams(window.location.search);
            const sceneName = urlParams.get("scene");
            
            if (sceneName) {
                // Map common scene names to their typical exporter paths
                const commonPaths = {
                    'telefoonhoes': '/geo2/exporterGLTF1',
                    'sleutelhanger': '/geo1/exporterGLTF1',
                    'doosje': '/doos/exporterGLTF1',
                    'sleutelhoes': '/doos/exporterGLTF1',
                    'sandblock': '/geo1/exporterGLTF1'
                };
                
                if (commonPaths[sceneName]) {
                    logDebug('Using detected exporter node path for scene:', sceneName, commonPaths[sceneName]);
                    return commonPaths[sceneName];
                }
            }
            
            // Final fallback
            return '/geo1/exporterGLTF1';
        },
        
        /**
         * Enable or disable debug mode
         */
        setDebugMode: function(enabled) {
            debugMode = enabled;
            return this;
        },
        
        /**
         * Get stored scene data and model information
         */
        getSceneData: function() {
            return {
                sceneData: sceneData,
                lastSceneReadyMessage: lastSceneReadyMessage,
                modelPath: window.tdPolygonjs && window.tdPolygonjs.model_path || null,
                productId: window.tdPolygonjs && window.tdPolygonjs.product_id || null
            };
        },
        
        /**
         * Get the last scene ready message data
         */
        getLastSceneReadyMessage: function() {
            return lastSceneReadyMessage;
        },
        
        /**
         * Check if a scene is already loaded for a specific product
         */
        isSceneLoaded: function(productId) {
            if (!productId) return false;
            
            // Check if we have scene data for this product
            return sceneData.productId === productId && polyScene !== null;
        },
        
        /**
         * Get cached scene info for a specific product
         */
        getCachedSceneInfo: function(productId) {
            if (this.isSceneLoaded(productId)) {
                return {
                    scene: polyScene,
                    sceneData: sceneData,
                    lastMessage: lastSceneReadyMessage
                };
            }
            return null;
        },
        
        /**
         * Clear scene cache
         */
        clearSceneCache: function() {
            sceneData = {};
            lastSceneReadyMessage = null;
            logDebug('Scene cache cleared');
        }
    };
    
    /**
     * Update parameter using direct node path (e.g., "/geo1/line1" and "pointsCount")
     * Enhanced with case-insensitive lookup and fallback attempts
     */
    function updateByDirectNodePath(nodePath, paramName, value) {
        // Extract the scene name for special handling
        const urlParams = new URLSearchParams(window.location.search);
        const sceneName = urlParams.get("scene") || "";
        
        // First check if nodePath contains a dot-notation parameter
        // Example: "/doos/mat/colorbox.colorr"
        if (typeof nodePath === 'string' && nodePath.includes('.')) {
            const dotParts = nodePath.split('.');
            if (dotParts.length === 2) {
                const pathPart = dotParts[0];
                // If paramName is not explicitly provided, use the part after the dot
                if (!paramName) {
                    paramName = dotParts[1];
                    logDebug('Using parameter from dot notation:', paramName);
                }
                nodePath = pathPart;
                logDebug('Extracted path from dot notation:', nodePath);
            }
        }
        
        // Handle cases where the paramName is duplicated in the last segment of the path
        // Example: "/doos/mat/colorbox/colorr" with paramName="colorr"
        if (typeof nodePath === 'string' && paramName) {
            const pathParts = nodePath.split('/');
            const lastSegment = pathParts[pathParts.length - 1];
            
            // If the last segment matches the parameter name, remove it
            if (lastSegment && typeof lastSegment === 'string' && typeof paramName === 'string' && lastSegment.toLowerCase() === paramName.toLowerCase()) {
                pathParts.pop(); // Remove the last segment
                nodePath = pathParts.join('/');
                logDebug('Removed duplicated parameter from path:', nodePath);
            }
        }
        
        // Special handling for doosje scene paths
        if (sceneName === 'doosje' && typeof nodePath === 'string') {
            // Define known path mappings for the doosje scene
            const pathMappings = {
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
                '/doos/ctrl_doos/tekst_schaal': '/doos/ctrl_doos'
            };
            
            // Check if we have a direct mapping for this path
            if (pathMappings[nodePath]) {
                const correctedPath = pathMappings[nodePath];
                logDebug('Applied doosje path mapping:', nodePath, '->', correctedPath);
                nodePath = correctedPath;
            }
        }
        
        // Check if nodePath starts with a slash, indicating it's a full path
        if (typeof nodePath === 'string' && nodePath.startsWith('/')) {
            try {
                // First try direct lookup with exact path
                let node = polyScene.node(nodePath);
                
                // If node not found, try case variations
                if (!node) {
                    logDebug('⚠️ Node not found with exact path:', nodePath);
                    
                    // Try fixing case issues in the path
                    const pathParts = nodePath.substring(1).split('/');
                    const fixedPath = tryVariousCasePaths(pathParts);
                    
                    if (fixedPath) {
                        logDebug('✓ Found node with adjusted path:', fixedPath);
                        node = polyScene.node(fixedPath);
                    } else {
                        // Still not found after trying various cases
                        logDebug('⚠️ Node not found with any case variations');
                        return false;
                    }
                }
                
                // Check if the parameter exists
                if (!node.p) {
                    logDebug('⚠️ Node has no parameters:', nodePath);
                    return false;
                }
                
                // Try various case variations of the parameter name
                let actualParamName = paramName;
                if (!node.p[paramName]) {
                    // Try lowercase
                    const lowerParam = typeof paramName === 'string' ? paramName.toLowerCase() : String(paramName).toLowerCase();
                    if (node.p[lowerParam]) {
                        actualParamName = lowerParam;
                        logDebug('✓ Found parameter with lowercase:', lowerParam);
                    } 
                    // Try camelCase if parameter has dots (like "breedte.breedte")
                    else if (paramName.includes('.')) {
                        const cleanParam = paramName.split('.')[0];
                        if (node.p[cleanParam]) {
                            actualParamName = cleanParam;
                            logDebug('✓ Found parameter by removing duplicate:', cleanParam);
                        } else {
                            // Try available parameters for a match
                            const paramKeys = Object.keys(node.p);
                            const possibleMatch = paramKeys.find(key => 
                                (typeof key === 'string' && typeof cleanParam === 'string' && 
                                 key.toLowerCase() === cleanParam.toLowerCase() ||
                                 key.toLowerCase().includes(cleanParam.toLowerCase()))
                            );
                            
                            if (possibleMatch) {
                                actualParamName = possibleMatch;
                                logDebug('✓ Found similar parameter:', possibleMatch);
                            } else {
                                logDebug('⚠️ Parameter not found:', nodePath + '.' + paramName);
                                logDebug('⚠️ Available parameters:', Object.keys(node.p).join(', '));
                                return false;
                            }
                        }
                    } else {
                        // Try available parameters for a match
                        const paramKeys = Object.keys(node.p);
                        const possibleMatch = paramKeys.find(key => 
                            (typeof key === 'string' && typeof paramName === 'string' && 
                             key.toLowerCase() === paramName.toLowerCase() ||
                             key.toLowerCase().includes(paramName.toLowerCase()))
                        );
                        
                        if (possibleMatch) {
                            actualParamName = possibleMatch;
                            logDebug('✓ Found similar parameter:', possibleMatch);
                        } else {
                            logDebug('⚠️ Parameter not found:', nodePath + '.' + paramName);
                            logDebug('⚠️ Available parameters:', Object.keys(node.p).join(', '));
                            return false;
                        }
                    }
                }
                
                // Special handling for specific parameters
                let processedValue = value;
                
                // Convert mm to meters for size parameters
                if (['sizeX', 'sizeY', 'sizeZ'].includes(actualParamName) && typeof value === 'number' && value > 1) {
                    processedValue = value * 0.001; // Convert mm to m
                }
                
                logDebug('✅ Setting direct path', nodePath + '.' + actualParamName, 'to:', processedValue);
                node.p[actualParamName].set(processedValue);
                return true;
                
            } catch (error) {
                logDebug('❌ Error setting direct path:', error);
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Try various case combinations for path parts
     * Returns the corrected path if a node is found, null otherwise
     */
    function tryVariousCasePaths(pathParts) {
        // Common case variations to try
        const commonCasePatterns = [
            // Original
            (part) => part,
            // Uppercase
            (part) => part.toUpperCase(),
            // Lowercase
            (part) => part.toLowerCase(),
            // First letter uppercase
            (part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase(),
            // CamelCase (first + uppercase after non-letters)
            (part) => part.replace(/(?:^|\W)\w/g, match => match.toUpperCase()).replace(/\W/g, '')
        ];
        
        // Store path variations to try
        const pathsToTry = [];
        
        // Known common directory names 
        const commonDirNames = {
            'mat': ['MAT', 'Mat', 'mat'],
            'geo': ['GEO', 'Geo', 'geo'],
            'ctrl': ['CTRL', 'Ctrl', 'ctrl']
        };
        
        // First try simple case variations of the entire path
        for (const transform of commonCasePatterns) {
            // Skip identity transform (we already tried the original path)
            if (transform === commonCasePatterns[0]) continue;
            
            const newPath = '/' + pathParts.map(transform).join('/');
            pathsToTry.push(newPath);
        }
        
        // Then try specific variations for the first part (usually the directory)
        if (pathParts.length > 0) {
            const firstPart = typeof pathParts[0] === 'string' ? pathParts[0].toLowerCase() : String(pathParts[0]).toLowerCase();
            const restParts = pathParts.slice(1);
            
            // Check if this is a common directory name
            if (commonDirNames[firstPart]) {
                for (const dirVariation of commonDirNames[firstPart]) {
                    const newPath = '/' + dirVariation + '/' + restParts.join('/');
                    pathsToTry.push(newPath);
                }
            }
        }
        
        // Try the variations one by one
        for (const path of pathsToTry) {
            try {
                const node = polyScene.node(path);
                if (node) {
                    return path;
                }
            } catch (e) {
                // Ignore errors and continue trying
            }
        }
        
        // If we've found no variation that works
        return null;
    }
    
    /**
     * Extract node path from ID (e.g., "geo1-line1-pointsCount" -> "/geo1/line1" and "pointsCount")
     * Enhanced to handle parameter duplication and case variations
     */
    function updateByExtractedPath(nodeId, paramName, value) {
        // Only proceed if not already a path and contains dashes
        if (typeof nodeId === 'string' && !nodeId.startsWith('/') && nodeId.includes('-')) {
            try {
                const parts = nodeId.split('-');
                
                if (parts.length < 2) {
                    return false;
                }
                
                // If paramName is not provided, use the last part of nodeId
                let actualParamName;
                
                if (paramName) {
                    // Handle parameter duplication (e.g., "breedte.breedte")
                    if (paramName.includes('.')) {
                        actualParamName = paramName.split('.')[0];
                        logDebug('Fixed duplicated parameter name:', paramName, '->', actualParamName);
                    } else if (nodeId.includes('/') && nodeId.endsWith('/' + paramName)) {
                        // Handle case where parameter is already at the end of the path
                        // e.g., "/doos/ctrl_doos/tekst" with paramName="tekst"
                        actualParamName = paramName;
                        logDebug('Parameter found in path ending:', nodeId, '->', paramName);
                    } else {
                        actualParamName = paramName;
                    }
                } else {
                    // Use the last part of nodeId
                    let lastPart = parts.pop();
                    
                    // Handle parameter duplication in the node ID
                    if (lastPart.includes('.')) {
                        lastPart = lastPart.split('.')[0];
                        logDebug('Fixed duplicated parameter in nodeId:', nodeId, '->', lastPart);
                    }
                    
                    actualParamName = lastPart;
                }
                
                // Remove the parameter part from the path if it matches
                if (actualParamName && parts.length > 1 && typeof parts[parts.length - 1] === 'string' && typeof actualParamName === 'string' && parts[parts.length - 1].toLowerCase() === actualParamName.toLowerCase()) {
                    parts.pop(); // Remove the last part that duplicates the parameter
                    logDebug('Removed duplicated parameter from path:', parts.join('/'));
                }
                
                // Special handling for cases where the paramName is part of the nodeId prefix
                // For example: "doos-ctrl_doos-tekst" with paramName="tekst"
                // This is a common problematic pattern in the error logs
                const lastPart = parts[parts.length - 1];
                if (lastPart && actualParamName && typeof lastPart === 'string' && typeof actualParamName === 'string' && lastPart.includes(actualParamName.toLowerCase())) {
                    // Check if the last part ends with the paramName (e.g., tekst_schaal and tekst_schaal)
                    // This is a strict check to avoid removing parts that just happen to contain the param name
                    if (typeof lastPart === 'string' && typeof actualParamName === 'string' && lastPart === actualParamName.toLowerCase()) {
                        logDebug('Found exact parameter match in path:', lastPart);
                        parts.pop(); // Remove duplicated parameter from path
                    }
                }
                
                // Build path from remaining parts
                const pathParts = parts.join('/');
                const nodePath = '/' + pathParts;
                
                logDebug('Extracted path:', nodePath, 'and param:', actualParamName, 'from:', nodeId);
                
                return updateByDirectNodePath(nodePath, actualParamName, value);
                
            } catch (error) {
                logDebug('❌ Error extracting path:', error);
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Update using HTML snippet from parameters
     * Enhanced to handle path case sensitivity and parameter duplication
     */
    function updateByHTMLSnippet(nodeId, value) {
        if (!window.tdPolygonjs || !window.tdPolygonjs.parameters) {
            return false;
        }
        
        const parameter = window.tdPolygonjs.parameters[nodeId];
        
        if (!parameter || !parameter.html_snippet) {
            return false;
        }
        
        try {
            const extractResult = extractNodeInfoFromHtmlSnippet(parameter.html_snippet);
            
            if (!extractResult.success) {
                logDebug('⚠️ Failed to extract node info from HTML snippet');
                return false;
            }
            
            let { nodePath, paramName } = extractResult;
            
            // Fix duplicated parameters (e.g., "breedte.breedte")
            if (paramName.includes('.')) {
                paramName = paramName.split('.')[0];
                logDebug('Fixed duplicated parameter from HTML snippet:', paramName);
            }
            
            logDebug('Extracted from HTML snippet:', nodePath, paramName);
            
            return updateByDirectNodePath(nodePath, paramName, value);
            
        } catch (error) {
            logDebug('❌ Error using HTML snippet:', error);
            return false;
        }
    }
    
    /**
     * Connect all UI controls to the scene
     */
    function connectControls() {
        if (!window.tdPolygonjs || !window.tdPolygonjs.parameters) {
            logDebug('No parameters found for PolygonJS integration, checking DOM directly');
            connectDOMControls();
            return;
        }
        
        const parameters = window.tdPolygonjs.parameters;
        logDebug('Connecting controls with parameters');
        
        // First, build RGB group cache
        if (window.tdPolygonjs.rgbGroups) {
            for (const groupId in window.tdPolygonjs.rgbGroups) {
                const group = window.tdPolygonjs.rgbGroups[groupId];
                rgbGroupCache[groupId] = {
                    displayName: group.display_name,
                    components: group.components
                };
                
                logDebug('Cached RGB group:', groupId, 'with components:', group.components);
            }
        }
        
        // Connect each control by finding it and caching
        $('.polygonjs-control').each(function() {
            const $control = $(this);
            const controlId = $control.attr('id');
            const $container = $control.closest('[data-node-id]');
            
            if (!$container.length) return;
            
            const nodeId = $container.data('node-id');
            
            if (!nodeId) {
                logDebug('⚠️ No node ID found for control:', controlId);
                return;
            }
            
            logDebug('Found control:', controlId, 'for node:', nodeId);
            
            // Cache the control for direct access
            controlCache[nodeId] = {
                element: $control[0],
                id: controlId
            };
            
            // Check if this is part of an RGB group
            const rgbGroup = $container.data('rgb-group');
            if (rgbGroup) {
                logDebug('Control is part of RGB group:', rgbGroup);
                
                // Get the G and B component IDs
                const gComponentId = $container.data('rgb-g');
                const bComponentId = $container.data('rgb-b');
                
                if (gComponentId && bComponentId) {
                    logDebug('RGB group components - G:', gComponentId, 'B:', bComponentId);
                    
                    // Add to RGB group cache if not already there
                    if (!rgbGroupCache[rgbGroup]) {
                        rgbGroupCache[rgbGroup] = {
                            components: {
                                r: nodeId,
                                g: gComponentId,
                                b: bComponentId
                            }
                        };
                    }
                }
            }
        });
        
        // Cache color controls 
        $('.color-radio').each(function() {
            const $radio = $(this);
            const $container = $radio.closest('[data-node-id]');
            
            if (!$container.length) return;
            
            const nodeId = $container.data('node-id');
            
            if (!nodeId) return;
            
            if (!controlCache[nodeId]) {
                controlCache[nodeId] = {
                    colorOptions: []
                };
            }
            
            controlCache[nodeId].colorOptions = controlCache[nodeId].colorOptions || [];
            
            const $swatch = $radio.closest('.color-swatch');
            controlCache[nodeId].colorOptions.push({
                element: $radio[0],
                colorKey: $radio.val(),
                colorName: $swatch.find('.swatch-name').text()
            });
            
            // Check if this is an RGB color control
            const rgbGroup = $container.data('rgb-group');
            if (rgbGroup) {
                logDebug('Color control is part of RGB group:', rgbGroup);
                
                // Add event listener to update RGB components
                $radio.on('change', function() {
                    const colorKey = $(this).val();
                    
                    // Get RGB values from global colors
                    if (window.tdPolygonjs.colorOptions && window.tdPolygonjs.colorOptions[colorKey]) {
                        const rgb = window.tdPolygonjs.colorOptions[colorKey].rgb;
                        logDebug('Updating RGB components with values:', rgb);
                        
                        // Update all RGB components
                        if (rgb && rgb.length === 3 && rgbGroupCache[rgbGroup]) {
                            const components = rgbGroupCache[rgbGroup].components;
                            
                            // Update R, G, B components
                            window.TDPolygonjs.updateNodeParameter(components.r, 'colorr', rgb[0]);
                            window.TDPolygonjs.updateNodeParameter(components.g, 'colorg', rgb[1]);
                            window.TDPolygonjs.updateNodeParameter(components.b, 'colorb', rgb[2]);
                        }
                    }
                });
            }
        });
        
        logDebug('Control cache built');
    }
    
    /**
     * Connect DOM-based controls (alternative to parameter-based controls)
     */
    function connectDOMControls() {
        // Minimal implementation to scan the DOM for controls
        logDebug('Connecting DOM-based controls');
        
        // Similar to connectControls, but scans for controls without requiring parameters data
    }
    
    /**
     * Set up message listener for iframe communication
     * Enhanced to handle case sensitivity and parameter duplication
     * Now with special pattern matching for doosje scene
     */
    function setupMessageListener() {
        window.addEventListener('message', function(event) {
            try {
                const data = event.data;
                
                if (!data || !data.type) return;
                
                if (data.type === 'updateParameter') {
                    logDebug('Received updateParameter message:', data);
                    
                    if (data.nodeId) {
                        // Define sanitized variables for path and parameters
                        let sanitizedNodeId = data.nodeId;
                        let extractedParamName = data.paramName;
                        
                        // Check if nodeId has a dot-format parameter included
                        // Example: "/doos/mat/colorbox.colorr"
                        if (data.nodeId.includes('.')) {
                            const dotParts = data.nodeId.split('.');
                            // Only process if it looks like path.param format
                            if (dotParts.length === 2) {
                                sanitizedNodeId = dotParts[0]; // Path before the dot
                                // Only override paramName if not explicitly provided 
                                if (!data.paramName) {
                                    extractedParamName = dotParts[1]; // Parameter after the dot
                                    logDebug('Extracted parameter from dot notation:', sanitizedNodeId, extractedParamName);
                                }
                            }
                        }
                        
                        // ===== SPECIAL HANDLING FOR EXACT PATTERNS SEEN IN LOGS =====
                        
                        // Extract the scene name from URL to identify which scene we're working with
                        const urlParams = new URLSearchParams(window.location.search);
                        const sceneName = urlParams.get("scene") || "";
                        
                        // Direct path handling for known problematic paths in doosje scene
                        if (sceneName === 'doosje') {
                            // Special cases for common doosje paths with exact correction map
                            const doosPathMap = {
                                '/doos/mat/colorbox': {
                                    path: '/doos/MAT/colorBox', 
                                    paramMap: {'colorr': 'colorr', 'colorg': 'colorg', 'colorb': 'colorb'}
                                },
                                '/doos/mat/colorlid': {
                                    path: '/doos/MAT/colorLid', 
                                    paramMap: {'colorr': 'colorr', 'colorg': 'colorg', 'colorb': 'colorb'}
                                },
                                '/doos/mat/colortekst': {
                                    path: '/doos/MAT/colorTekst', 
                                    paramMap: {'colorr': 'colorr', 'colorg': 'colorg', 'colorb': 'colorb'}
                                }, 
                                '/doos/ctrl_doos/breedte': {
                                    path: '/doos/ctrl_doos', 
                                    paramMap: {'breedte': 'breedte'}
                                },
                                '/doos/ctrl_doos/diepte': {
                                    path: '/doos/ctrl_doos', 
                                    paramMap: {'diepte': 'diepte'}
                                },
                                '/doos/ctrl_doos/hoogte': {
                                    path: '/doos/ctrl_doos', 
                                    paramMap: {'hoogte': 'hoogte'}
                                }, 
                                '/doos/ctrl_doos/dikte_wanden': {
                                    path: '/doos/ctrl_doos', 
                                    paramMap: {'dikte_wanden': 'dikte_wanden'}
                                },
                                '/doos/ctrl_doos/dikte_bodem': {
                                    path: '/doos/ctrl_doos', 
                                    paramMap: {'dikte_bodem': 'dikte_bodem'}
                                },
                                '/doos/ctrl_doos/deksel_dikte': {
                                    path: '/doos/ctrl_doos', 
                                    paramMap: {'deksel_dikte': 'deksel_dikte'}
                                },
                                '/doos/ctrl_doos/tekst': {
                                    path: '/doos/ctrl_doos', 
                                    paramMap: {'tekst': 'tekst'}
                                },
                                '/doos/ctrl_doos/tekst_schaal': {
                                    path: '/doos/ctrl_doos', 
                                    paramMap: {'tekst_schaal': 'tekst_schaal'}
                                }
                            };
                            
                            // Also handle the case where nodeId doesn't have the exact path but ends with one of our problematic parameters
                            let foundMapping = null;
                            if (!doosPathMap[data.nodeId]) {
                                // Check if this is a path ending with a known problem parameter
                                const pathParts = data.nodeId.split('/');
                                const lastSegment = pathParts[pathParts.length - 1];
                                
                                // Map of problem parameters to their parent paths
                                const paramPathMap = {
                                    'tekst': '/doos/ctrl_doos',
                                    'tekst_schaal': '/doos/ctrl_doos',
                                    'breedte': '/doos/ctrl_doos',
                                    'diepte': '/doos/ctrl_doos',
                                    'hoogte': '/doos/ctrl_doos',
                                    'dikte_wanden': '/doos/ctrl_doos',
                                    'dikte_bodem': '/doos/ctrl_doos',
                                    'deksel_dikte': '/doos/ctrl_doos',
                                    'colorbox': '/doos/MAT/colorBox',
                                    'colorlid': '/doos/MAT/colorLid',
                                    'colortekst': '/doos/MAT/colorTekst'
                                };
                                
                                if (paramPathMap[lastSegment]) {
                                    // Reconstruct the corrected path and parameter mapping
                                    foundMapping = {
                                        path: paramPathMap[lastSegment],
                                        paramMap: { [lastSegment]: lastSegment }
                                    };
                                    
                                    logDebug('Found parameter match at path end:', lastSegment, '->', paramPathMap[lastSegment]);
                                }
                            }
                            
                            // Check if we have a direct mapping for this path
                            const mapping = doosPathMap[data.nodeId] || foundMapping;
                            if (mapping) {
                                const correctPath = mapping.path;
                                let correctParam = data.paramName;
                                
                                // If no explicit param name is provided, use the last segment of the nodeId
                                if (!correctParam && data.nodeId.includes('/')) {
                                    const parts = data.nodeId.split('/');
                                    correctParam = parts[parts.length - 1];
                                    logDebug('Using path segment as parameter:', correctParam);
                                }
                                
                                // Clean parameter (remove duplicated part if present)
                                if (correctParam && correctParam.includes('.')) {
                                    correctParam = correctParam.split('.')[0];
                                }
                                
                                // Get the correct parameter name from the mapping
                                if (mapping.paramMap[correctParam]) {
                                    correctParam = mapping.paramMap[correctParam];
                                }
                                
                                logDebug('✅ Using direct path mapping:', 
                                    data.nodeId + ' → ' + correctPath, 
                                    data.paramName + ' → ' + correctParam);
                                
                                // Try to directly update with the corrected path and parameter
                                const result = updateByDirectNodePath(correctPath, correctParam, data.value);
                                
                                if (result) {
                                    // Skip the general parameter update if this succeeded
                                    return;
                                }
                            }
                        }
                        
                        // ===== GENERAL CASE HANDLING =====
                        
                        // Handle nodeId case variations and formats
                        let nodeId = sanitizedNodeId;
                        
                        // Clean up nodeId if it has case issues or trailing component parts
                        if (nodeId.includes('-')) {
                            // Check for common path parts that should be uppercase/camelcase
                            const parts = nodeId.split('-');
                            const firstPart = typeof parts[0] === 'string' ? parts[0].toLowerCase() : String(parts[0]).toLowerCase();
                            
                            // Apply case transformations to known folder names
                            if (firstPart === 'mat') {
                                parts[0] = 'MAT';
                            } else if (firstPart === 'geo1' || firstPart === 'geo2') {
                                // Keep as is
                            } else if (firstPart === 'doos') {
                                // Keep as is
                            }
                            
                            // Rebuild the nodeId with corrected parts
                            nodeId = parts.join('-');
                        }
                        
                        // Clean up paramName if it has duplication
                        let paramName = extractedParamName || data.paramName;
                        if (paramName && paramName.includes('.')) {
                            paramName = paramName.split('.')[0];
                            logDebug('Fixed duplicated parameter:', data.paramName, '->', paramName);
                        }
                        
                        // Apply the parameter
                        window.TDPolygonjs.updateNodeParameter(
                            nodeId,
                            paramName,
                            data.value
                        );
                    }
                }
                
                if (data.type === 'updateModel') {
                    logDebug('Received updateModel message:', data);
                    
                    // Process each property that might contain an update
                    if (data.height !== undefined && data.height_mapping) {
                        applyUpdateFromMapping(data.height_mapping, data.height);
                    }
                    
                    if (data.width !== undefined && data.width_mapping) {
                        applyUpdateFromMapping(data.width_mapping, data.width);
                    }
                    
                    if (data.depth !== undefined && data.depth_mapping) {
                        applyUpdateFromMapping(data.depth_mapping, data.depth);
                    }
                    
                    if (data.shelvesCount !== undefined && data.shelves_mapping) {
                        applyUpdateFromMapping(data.shelves_mapping, data.shelvesCount);
                    }
                    
                    if (data.customText !== undefined && data.text_mapping) {
                        applyUpdateFromMapping(data.text_mapping, data.customText);
                    }
                    
                    if (data.color !== undefined && data.color_mapping) {
                        // Handle RGB color mappings
                        if (data.color_mapping.is_rgb_group && data.color_mapping.components) {
                            const colorComponents = data.color_mapping.components;
                            const colorValue = data.color;
                            
                            // Try to get RGB values for the selected color
                            let rgb = null;
                            
                            if (typeof colorValue === 'string') {
                                // Try to get RGB from color name
                                const colorKey = typeof colorValue === 'string' ? colorValue.toLowerCase().replace(/\s+/g, '-') : String(colorValue).toLowerCase().replace(/\s+/g, '-');
                                if (window.tdPolygonjs && window.tdPolygonjs.colorOptions && 
                                    window.tdPolygonjs.colorOptions[colorKey]) {
                                    rgb = window.tdPolygonjs.colorOptions[colorKey].rgb;
                                }
                            } else if (Array.isArray(colorValue) && colorValue.length === 3) {
                                // Direct RGB values
                                rgb = colorValue;
                            }
                            
                            if (rgb) {
                                logDebug('Applying RGB color values:', rgb);
                                // Update each color component with enhanced error handling
                                if (colorComponents.r) {
                                    // Try case variations for component paths
                                    const rPath = fixNodePath(colorComponents.r);
                                    updateByDirectNodePath(rPath, 'colorr', rgb[0]);
                                }
                                if (colorComponents.g) {
                                    const gPath = fixNodePath(colorComponents.g);
                                    updateByDirectNodePath(gPath, 'colorg', rgb[1]);
                                }
                                if (colorComponents.b) {
                                    const bPath = fixNodePath(colorComponents.b);
                                    updateByDirectNodePath(bPath, 'colorb', rgb[2]);
                                }
                            }
                        } else {
                            applyUpdateFromMapping(data.color_mapping, data.color);
                        }
                    }
                }
                
                // Handle scene ready message
                if (data.type === 'sceneReady') {
                    logDebug('Received sceneReady message:', data);
                    
                    // Store the entire scene ready message
                    lastSceneReadyMessage = data;
                    
                    // Extract and store scene information
                    if (data.sceneInfo) {
                        sceneData.sceneInfo = data.sceneInfo;
                    }
                    
                    if (data.modelInfo) {
                        sceneData.modelInfo = data.modelInfo;
                    }
                    
                    if (data.parameterInfo) {
                        sceneData.parameterInfo = data.parameterInfo;
                    }
                    
                    // Store product and model path
                    if (data.productId) {
                        sceneData.productId = data.productId;
                    }
                    
                    if (data.modelPath) {
                        sceneData.modelPath = data.modelPath;
                    }
                    
                    // Trigger custom event for other parts of the plugin
                    $(document).trigger('td_polygonjs_scene_ready', [sceneData, data]);
                    
                    logDebug('Scene data stored:', sceneData);
                }
                
                // Signal that we're ready
                if (data.type === 'checkReady') {
                    logDebug('Received checkReady message, responding');
                    if (event.source) {
                        event.source.postMessage({ type: 'bridgeReady' }, '*');
                    }
                }
                
                // Handle scene status request
                if (data.type === 'getSceneStatus') {
                    logDebug('Received getSceneStatus request');
                    
                    const response = {
                        type: 'sceneStatusResponse',
                        isInitialized: bridgeInitialized,
                        hasScene: polyScene !== null,
                        sceneData: sceneData,
                        productId: sceneData.productId || null
                    };
                    
                    if (event.source) {
                        event.source.postMessage(response, '*');
                    }
                }
                
            } catch (error) {
                logDebug('❌ Error processing message:', error);
            }
        });
        
        logDebug('Message listener set up');
    }
    
    /**
     * Helper function to fix known path case issues
     */
    function fixNodePath(path) {
        if (!path) return path;
        
        // If path already starts with slash, it's a full path
        if (path.startsWith('/')) {
            return path;
        }
        
        // Convert dashed to path
        if (path.includes('-')) {
            const parts = path.split('-');
            
            // Apply known transformations
            if (typeof parts[0] === 'string' && parts[0].toLowerCase() === 'mat') {
                parts[0] = 'MAT';
            } else if (typeof parts[0] === 'string' && parts[0].toLowerCase() === 'doos') {
                // Keep as is - lowercase doos
            }
            
            // Convert colorbox/colorlid to camelCase if found
            for (let i = 0; i < parts.length; i++) {
                if (typeof parts[i] === 'string' && parts[i].toLowerCase() === 'colorbox') {
                    parts[i] = 'colorBox';
                } else if (typeof parts[i] === 'string' && parts[i].toLowerCase() === 'colorlid') {
                    parts[i] = 'colorLid';
                } else if (typeof parts[i] === 'string' && parts[i].toLowerCase() === 'colortekst') {
                    parts[i] = 'colorTekst';
                }
            }
            
            return '/' + parts.join('/');
        }
        
        return path;
    }
    
    /**
     * Apply an update from a mapping
     * Enhanced with case sensitivity and parameter handling
     */
    function applyUpdateFromMapping(mapping, value) {
        if (!mapping) return false;
        
        try {
            if (mapping.type === 'node' && mapping.path && mapping.param) {
                // Fix path case issues
                const fixedPath = fixNodePath(mapping.path);
                
                // Handle parameter duplication
                let fixedParam = mapping.param;
                if (fixedParam.includes('.')) {
                    fixedParam = fixedParam.split('.')[0];
                    logDebug('Fixed duplicated parameter in mapping:', mapping.param, '->', fixedParam);
                }
                
                logDebug('Applying update with fixed mapping:', fixedPath, fixedParam);
                return updateByDirectNodePath(fixedPath, fixedParam, value);
            } 
            else if (mapping.type === 'snippet' && mapping.snippet) {
                logDebug('Executing snippet from mapping');
                const execFunction = new Function('scene', 'value', mapping.snippet);
                execFunction(polyScene, value);
                return true;
            }
        } catch (error) {
            logDebug('❌ Error applying update from mapping:', error);
            return false;
        }
        
        return false;
    }
    
    /**
     * Extract node path and parameter name from an HTML snippet
     */
    function extractNodeInfoFromHtmlSnippet(htmlSnippet) {
        try {
            // First try to extract the ID from the input tag
            const inputMatch = htmlSnippet.match(/<input[^>]*id=['"]([\w\-\.]+)['"]/);
            
            if (!inputMatch || !inputMatch[1]) {
                // Try to extract from the label 
                const labelMatch = htmlSnippet.match(/<label[^>]*for=['"]([\w\-\.]+)['"]/);
                
                if (!labelMatch || !labelMatch[1]) {
                    return { success: false };
                }
                
                return extractPathAndParamFromId(labelMatch[1]);
            }
            
            return extractPathAndParamFromId(inputMatch[1]);
        } catch (error) {
            logDebug('❌ Error extracting node info from HTML:', error);
            return { success: false };
        }
    }
    
    /**
     * Extract path and parameter from an ID
     */
    function extractPathAndParamFromId(id) {
        // Format is typically 'geo1-line1-pointsCount' where:
        // - geo1/line1 is the path
        // - pointsCount is the parameter name
        const parts = id.split('-');
        
        if (parts.length < 2) {
            return { success: false };
        }
        
        // The last part is the parameter name
        const paramName = parts.pop();
        
        // The rest is the path
        const pathParts = parts.join('/');
        const nodePath = '/' + pathParts;
        
        return {
            success: true,
            nodePath,
            paramName
        };
    }
    
    /**
     * Convert node ID to path and parameter
     * Used to get consistent node path from different ID formats
     */
    function convertNodeIdToPath(nodeId, paramName) {
        let path = null;
        let actualParamName = paramName;
        
        // Handle path that starts with slash
        if (nodeId.startsWith('/')) {
            path = nodeId;
        }
        // Handle ID with dashes (component format)
        else if (nodeId.includes('-')) {
            const parts = nodeId.split('-');
            
            // If paramName is not provided or is 'value', use the last part as the param name
            if (!paramName || paramName === 'value') {
                // Extract the last part as the parameter name
                actualParamName = parts.pop();
                // Join remaining parts with slashes to form the path
                path = '/' + parts.join('/');
            } else {
                // Use the provided paramName
                path = '/' + parts.join('/');
            }
        }
        
        return { path, actualParamName };
    }
    
    /**
     * Log debug messages
     */
    function logDebug(...args) {
        if (debugMode) {
            console.log('🔗 [Bridge]', ...args);
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('[TD Bridge] Script loaded, initializing bridge...');
        logDebug('TD Link PolygonJS Bridge script loaded');
        
        // Enable debug mode from URL parameter
        if (debugMode) {
            logDebug('Debug mode enabled');
        }
        
        // Check if we can connect to an embedded iframe
        if (window.tdPolygonjs && window.tdPolygonjs.model_path) {
            logDebug('PolygonJS model path found:', window.tdPolygonjs.model_path);
        }
        
        // Notify any parent frames that we're ready
        if (window.parent && window.parent !== window) {
            try {
                window.parent.postMessage({ type: 'bridgeScriptReady' }, '*');
                logDebug('Notified parent frame that bridge script is ready');
            } catch (error) {
                // Ignore cross-origin errors
            }
        }
        
        // Check for existing PolygonJS scenes and initialize if found
        if (window.polyScene) {
            console.log('[TD Bridge] Found existing PolygonJS scene, initializing bridge...');
            window.TDPolygonjs.initBridge(window.polyScene, { debug: debugMode });
        }
    });
    
})(jQuery);