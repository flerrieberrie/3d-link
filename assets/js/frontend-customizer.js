/**
 * TD Link Product Customizer Script
 * 
 * Handles UI interactions for the customizer controls
 * and ensures proper connection with the PolygonJS scene
 */
(function($) {
    // Debug mode
    const urlParams = new URLSearchParams(window.location.search);
    const debug = urlParams.has('debug');
    
    // Track if bridge is ready
    let bridgeReady = false;
    
    // Store control values to apply when bridge becomes ready
    const pendingUpdates = {};
    
    // Cache for RGB group data
    const rgbGroups = {};
    
    $(document).ready(function() {
        console.log('[TD Customizer] Script loaded, initializing...');
        logDebug('TD Link customizer script loaded');
        initCustomizer();
    });
    
    /**
     * Enhanced function to ensure out-of-stock colors display correctly
     */
    function checkOutOfStockColors() {
        logDebug('Checking for out-of-stock colors...');
        
        // Find all color swatches
        const swatches = document.querySelectorAll('.color-swatch');
        logDebug(`Found ${swatches.length} color swatches total`);
        
        // Count and log out-of-stock swatches for debugging
        let outOfStockCount = 0;
        swatches.forEach(swatch => {
            if (swatch.classList.contains('out-of-stock')) {
                outOfStockCount++;
                logDebug('Found out-of-stock swatch:', swatch.querySelector('.swatch-name')?.textContent);
            }
        });
        
        logDebug(`Found ${outOfStockCount} out-of-stock swatches`);
        
        // Force apply styling to all out-of-stock swatches
        document.querySelectorAll('.color-swatch.out-of-stock').forEach(swatch => {
            // Ensure the swatch has all needed styling
            Object.assign(swatch.style, {
                position: 'relative',
                border: '1px solid #d63638',
                backgroundColor: 'rgba(214, 54, 56, 0.05)',
                borderRadius: '4px',
                padding: '5px',
                cursor: 'not-allowed',
                opacity: '0.9'
            });
            
            // Add the out-of-stock label if it doesn't exist
            if (!swatch.querySelector('.out-of-stock-label')) {
                const label = document.createElement('span');
                label.className = 'out-of-stock-label';
                label.textContent = 'Out of Stock';
                Object.assign(label.style, {
                    display: 'block',
                    fontSize: '10px',
                    color: '#d63638',
                    fontWeight: '600',
                    marginTop: '4px',
                    textTransform: 'uppercase',
                    background: 'rgba(214, 54, 56, 0.1)',
                    padding: '2px 4px',
                    borderRadius: '2px',
                    textAlign: 'center'
                });
                swatch.appendChild(label);
            }
            
            // Add the OUT OF STOCK badge
            let badge = swatch.querySelector('.out-of-stock-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'out-of-stock-badge';
                badge.textContent = 'OUT OF STOCK';
                Object.assign(badge.style, {
                    position: 'absolute',
                    top: '-8px',
                    right: '-8px',
                    background: '#d63638',
                    color: 'white',
                    fontSize: '9px',
                    fontWeight: 'bold',
                    padding: '2px 6px',
                    borderRadius: '3px',
                    letterSpacing: '0.5px',
                    boxShadow: '0 1px 2px rgba(0,0,0,0.2)',
                    zIndex: '2'
                });
                swatch.appendChild(badge);
            }
            
            // Add diagonal line and overlay to the color swatch
            const colorSwatch = swatch.querySelector('.swatch-color');
            if (colorSwatch) {
                // Make sure position is set to relative
                colorSwatch.style.position = 'relative';
                
                // Add diagonal line
                let diagonalLine = colorSwatch.querySelector('.diagonal-line');
                if (!diagonalLine) {
                    diagonalLine = document.createElement('span');
                    diagonalLine.className = 'diagonal-line';
                    Object.assign(diagonalLine.style, {
                        position: 'absolute',
                        top: '0',
                        left: '0',
                        width: '100%',
                        height: '100%',
                        borderRadius: '50%',
                        background: 'linear-gradient(to top right, transparent calc(50% - 1px), #d63638, transparent calc(50% + 1px))',
                        zIndex: '1'
                    });
                    colorSwatch.appendChild(diagonalLine);
                }
                
                // Add dimmed overlay
                let overlay = colorSwatch.querySelector('.dimmed-overlay');
                if (!overlay) {
                    overlay = document.createElement('span');
                    overlay.className = 'dimmed-overlay';
                    Object.assign(overlay.style, {
                        position: 'absolute',
                        top: '0',
                        left: '0',
                        right: '0',
                        bottom: '0',
                        borderRadius: '50%',
                        background: 'rgba(255, 255, 255, 0.4)',
                        zIndex: '0'
                    });
                    colorSwatch.appendChild(overlay);
                }
            }
        });
    }
    
    // Track if we've already applied defaults to prevent repetition
    let defaultsApplied = false;
    
    // Track if we're currently applying defaults to prevent overlap
    let applyingDefaults = false;
    
    // Track if user is currently interacting with controls
    let userInteracting = false;
    
    // Store reference to the active scene
    let activeScene = null;
    
    /**
     * Get the active PolygonJS scene from various possible locations
     */
    function getActiveScene() {
        // Check our stored reference first
        if (activeScene) return activeScene;
        
        // Try iframe
        const iframe = document.querySelector('iframe[src*="scene="]');
        if (iframe && iframe.contentWindow && iframe.contentWindow.polyScene) {
            return iframe.contentWindow.polyScene;
        }
        
        // Try window
        if (window.polyScene) return window.polyScene;
        
        // Try from bridge
        if (window.TDPolygonjs && window.TDPolygonjs.getScene) {
            return window.TDPolygonjs.getScene();
        }
        
        return null;
    }
    
    /**
     * Initialize the customizer functionality
     */
    function initCustomizer() {
        console.log('[TD Customizer] Starting initialization...');
        
        // Wait for DOM to be fully ready
        setTimeout(function() {
            // Initialize RGB groups cache
            if (window.tdPolygonjs && window.tdPolygonjs.rgbGroups) {
                for (const groupId in window.tdPolygonjs.rgbGroups) {
                    rgbGroups[groupId] = window.tdPolygonjs.rgbGroups[groupId];
                }
                logDebug('Initialized RGB groups:', rgbGroups);
            }
            
            // Set up event listeners for controls
            setupSliders();
            setupNumberInputs();
            setupTextInputs();
            setupCheckboxes();
            setupColorOptions();
            setupDropdowns();
            
            // NEW: Watch for PolygonJS scene availability
            let sceneCheckAttempts = 0;
            let sceneCheckInterval = setInterval(function() {
                sceneCheckAttempts++;
                
                // Try multiple ways to find the scene
                let scene = null;
                
                // Method 1: Check window.polyScene
                if (window.polyScene) {
                    scene = window.polyScene;
                    console.log('[TD Customizer] Found scene at window.polyScene');
                }
                // Method 2: Check through iframe
                else if (document.querySelector('iframe')) {
                    try {
                        const iframe = document.querySelector('iframe');
                        if (iframe.contentWindow && iframe.contentWindow.polyScene) {
                            scene = iframe.contentWindow.polyScene;
                            console.log('[TD Customizer] Found scene in iframe');
                        }
                    } catch (e) {
                        // Cross-origin error, ignore
                    }
                }
                // Method 3: Try to find through TDPolygonjs
                else if (window.TDPolygonjs && window.TDPolygonjs.getScene) {
                    scene = window.TDPolygonjs.getScene();
                    if (scene) {
                        console.log('[TD Customizer] Found scene through TDPolygonjs.getScene()');
                    }
                }
                
                if (scene) {
                    console.log('[TD Customizer] PolygonJS scene detected, applying defaults...');
                    clearInterval(sceneCheckInterval);
                    window.sceneFound = true; // Mark that scene was found
                    activeScene = scene; // Store the scene reference
                    
                    // Initialize bridge if not already done
                    if (window.TDPolygonjs && !window.TDPolygonjs.isInitialized()) {
                        console.log('[TD Customizer] Initializing bridge manually...');
                        window.TDPolygonjs.initBridge(scene, { debug: debug });
                    }
                    
                    // Apply defaults only if not already applied
                    if (!defaultsApplied && !applyingDefaults) {
                        setTimeout(() => {
                            forceApplyAllDefaultValues(scene);
                        }, 500);
                    }
                } else if (sceneCheckAttempts % 10 === 0) {
                    console.log(`[TD Customizer] Still looking for scene... (attempt ${sceneCheckAttempts})`);
                }
            }, 100);
            
            // Stop checking after 10 seconds
            setTimeout(() => {
                clearInterval(sceneCheckInterval);
                if (!window.sceneFound) {
                    console.log('[TD Customizer] Scene not found after 10 seconds');
                }
            }, 10000);
            
            // Handle PolygonJS bridge initialization
            $(document).on('td_polygonjs_bridge_ready', function(event, scene) {
                console.log('[TD Customizer] Bridge ready event received');
                logDebug('PolygonJS bridge is ready, applying pending updates');
                bridgeReady = true;
                
                // Apply any pending updates
                applyPendingUpdates(scene);
                
                // Apply defaults only if not already applied
                if (!defaultsApplied && !applyingDefaults) {
                    forceApplyAllDefaultValues(scene);
                }
                
                // Check for out-of-stock colors and apply styling
                setTimeout(checkOutOfStockColors, 500);
            });
            
            // Check if bridge is already ready
            if (window.TDPolygonjs && window.TDPolygonjs.isInitialized && window.TDPolygonjs.isInitialized()) {
                logDebug('Bridge already initialized, triggering ready event');
                $(document).trigger('td_polygonjs_bridge_ready', [window.TDPolygonjs.getScene()]);
            } else {
                logDebug('Waiting for bridge to initialize...');
            }
            
            // Poll for bridge readiness as a fallback
            const bridgePollInterval = setInterval(function() {
                if (window.TDPolygonjs && window.TDPolygonjs.isInitialized && window.TDPolygonjs.isInitialized()) {
                    clearInterval(bridgePollInterval);
                    if (!bridgeReady) {
                        logDebug('Bridge detected through polling, triggering ready event');
                        $(document).trigger('td_polygonjs_bridge_ready', [window.TDPolygonjs.getScene()]);
                    }
                }
            }, 500);
            
            // Add a timeout to check if bridge is still not ready after 5 seconds
            setTimeout(function() {
                if (!bridgeReady && !defaultsApplied) {
                    console.log('[TD Customizer] Bridge not ready after 5 seconds, forcing value application');
                    logDebug('⚠️ Bridge not ready after 5 seconds');
                    
                    // Try to apply values directly to scene
                    if (window.polyScene) {
                        forceApplyAllDefaultValues(window.polyScene);
                    } else if (window.TDPolygonjs && window.TDPolygonjs.getScene) {
                        forceApplyAllDefaultValues(window.TDPolygonjs.getScene());
                    }
                }
            }, 5000);
            
            // Ensure form fields are added to the add to cart form
            setupAddToCartForm();
            
            // Apply out-of-stock styles regardless of bridge status
            // Run this after a short delay to ensure all elements are rendered
            setTimeout(checkOutOfStockColors, 500);
            
            // Also check again when the window loads fully
            $(window).on('load', function() {
                setTimeout(checkOutOfStockColors, 300);
            });
            
            // Watch for iframe load
            const checkForIframe = setInterval(() => {
                const iframe = document.querySelector('iframe[src*="scene="]');
                if (iframe) {
                    console.log('[TD Customizer] PolygonJS iframe found, setting up load listener');
                    clearInterval(checkForIframe);
                    
                    iframe.addEventListener('load', () => {
                        console.log('[TD Customizer] PolygonJS iframe loaded');
                        
                        // Give it a moment for the scene to initialize
                        setTimeout(() => {
                            // Try to access the scene directly from iframe
                            try {
                                if (iframe.contentWindow && iframe.contentWindow.polyScene && !defaultsApplied && !applyingDefaults) {
                                    console.log('[TD Customizer] Found scene in loaded iframe');
                                    activeScene = iframe.contentWindow.polyScene; // Store the scene reference
                                    forceApplyAllDefaultValues(iframe.contentWindow.polyScene);
                                }
                            } catch (e) {
                                console.log('[TD Customizer] Could not access iframe scene:', e.message);
                            }
                        }, 1000);
                    });
                }
            }, 100);
            
            // Stop checking for iframe after 5 seconds
            setTimeout(() => clearInterval(checkForIframe), 5000);
            
            // LAST RESORT: Listen for any message from the iframe
            window.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'sceneReady' && event.source) {
                    console.log('[TD Customizer] Received sceneReady message from iframe');
                    
                    // Try to get the scene from the iframe
                    try {
                        const iframes = document.querySelectorAll('iframe');
                        for (const iframe of iframes) {
                            if (iframe.contentWindow === event.source) {
                                if (iframe.contentWindow.polyScene && !defaultsApplied && !applyingDefaults) {
                                    console.log('[TD Customizer] Got scene from message source');
                                    forceApplyAllDefaultValues(iframe.contentWindow.polyScene);
                                }
                                break;
                            }
                        }
                    } catch (e) {
                        console.log('[TD Customizer] Error accessing scene from message:', e.message);
                    }
                }
                
                // Also check for modelReady message
                if (event.data && event.data.type === 'modelReady') {
                    console.log('[TD Customizer] Received modelReady message, attempting to apply defaults');
                    
                    // No need to request the scene - it will be available when ready
                }
                
                // Handle sceneInfo response
                if (event.data && event.data.type === 'sceneInfo') {
                    console.log('[TD Customizer] Received sceneInfo:', event.data);
                    
                    // Try to access the scene now that it's been exposed
                    setTimeout(() => {
                        const iframe = document.querySelector('iframe[src*="scene="]');
                        if (iframe && iframe.contentWindow && iframe.contentWindow.polyScene) {
                            console.log('[TD Customizer] Found exposed scene, applying defaults');
                            forceApplyAllDefaultValues(iframe.contentWindow.polyScene);
                        }
                    }, 100);
                }
            });
            
            logDebug('Customizer initialization complete');
        }, 100);
    }
    
    /**
     * NEW FUNCTION: Force apply all default values in the correct order
     * This ensures that parameters from WordPress override PolygonJS defaults
     */
    function forceApplyAllDefaultValues(scene) {
        if (!scene) {
            console.log('[TD Customizer] No scene available for default value application');
            return;
        }
        
        // Don't apply defaults if already applied
        if (defaultsApplied) {
            console.log('[TD Customizer] Defaults already applied, skipping');
            return;
        }
        
        // Don't apply defaults if currently in progress
        if (applyingDefaults) {
            console.log('[TD Customizer] Defaults application already in progress, skipping');
            return;
        }
        
        // Don't apply defaults if user is currently interacting
        if (userInteracting) {
            console.log('[TD Customizer] Skipping default application - user is interacting');
            return;
        }

        applyingDefaults = true;
        console.log('[TD Customizer] Force applying all default values from WordPress');
        
        // Step 1: Apply all non-color controls first (sliders, number inputs, text, checkboxes, dropdowns)
        const nonColorControls = [
            '.visible-slider',
            '.visible-number-input', 
            'input[type="text"].polygonjs-control',
            'input[type="checkbox"].polygonjs-control',
            'select.polygonjs-control'
        ];
        
        nonColorControls.forEach(selector => {
            $(selector).each(function() {
                const $control = $(this);
                const $container = $control.closest('[data-node-id]');
                const nodeId = $container.data('node-id');
                
                if (!nodeId) return;
                
                // Get the current value
                let value;
                if ($control.is(':checkbox')) {
                    value = $control.prop('checked');
                } else {
                    value = $control.val();
                }
                
                logDebug(`[TD Customizer] Applying default for ${nodeId}: ${value}`);
                
                // Try to apply directly to scene
                applyValueDirectlyToScene(scene, nodeId, value);
            });
        });
        
        // Step 2: Apply color parameters (these often depend on other parameters)
        setTimeout(() => {
            $('.color-swatch.active').each(function() {
                const $swatch = $(this);
                const $radio = $swatch.find('input[type="radio"]');
                const $container = $radio.closest('[data-node-id]');
                const nodeId = $container.data('node-id');
                const colorKey = $radio.val();
                const colorName = $swatch.find('.swatch-name').text();
                
                logDebug(`[TD Customizer] Applying default color for ${nodeId}: ${colorName}`);
                
                // Skip out-of-stock colors
                if ($swatch.hasClass('out-of-stock')) {
                    return;
                }
                
                // Check for RGB group
                const rgbGroup = $container.data('rgb-group');
                
                // Get RGB values and apply them
                if (window.tdPolygonjs && window.tdPolygonjs.colorOptions &&
                    window.tdPolygonjs.colorOptions[colorKey] &&
                    window.tdPolygonjs.colorOptions[colorKey].rgb) {
                    
                    const rgbValues = window.tdPolygonjs.colorOptions[colorKey].rgb;
                    logDebug(`[TD Customizer] RGB values for ${colorName}:`, rgbValues);
                    
                    if (rgbGroup && window.tdPolygonjs.rgbGroups && window.tdPolygonjs.rgbGroups[rgbGroup]) {
                        const components = window.tdPolygonjs.rgbGroups[rgbGroup].components;
                        
                        if (components.r && components.g && components.b) {
                            applyRGBDirectlyToScene(scene, components.r, components.g, components.b, rgbValues);
                        }
                    } else {
                        applyRGBDirectlyToScene(scene, nodeId, null, null, rgbValues);
                    }
                }
            });
            
            // Force scene update after colors
            if (scene && scene.root && typeof scene.root().compute === 'function') {
                try {
                    scene.root().compute();
                    console.log('[TD Customizer] Scene recomputed after color application');
                } catch (e) {
                    console.log('[TD Customizer] Error during scene recompute:', e);
                }
            }
        }, 100);
        
        // Step 3: Final force update after a delay to catch any slow-loading parameters
        setTimeout(() => {
            // Force scene to recompute all
            if (scene && scene.root && typeof scene.root().compute === 'function') {
                try {
                    scene.root().compute();
                    console.log('[TD Customizer] Final scene recompute completed');
                } catch (e) {
                    console.log('[TD Customizer] Error during final scene recompute:', e);
                }
            }
            
            // Mark as complete
            defaultsApplied = true;
            applyingDefaults = false;
        }, 1000);
    }
    
    /**
     * Apply a value directly to the PolygonJS scene without going through the bridge
     */
    function applyValueDirectlyToScene(scene, nodeId, value) {
        if (!scene || !nodeId) return;
        
        // Don't skip during user interaction - we want real-time updates
        // The duplicate prevention is handled in updateSceneParameter
        
        try {
            // Convert node ID to path
            let nodePath = nodeId;
            let paramName = null;
            
            if (!nodeId.startsWith('/')) {
                if (nodeId.includes('-')) {
                    const parts = nodeId.split('-');
                    paramName = parts.pop();
                    nodePath = '/' + parts.join('/');
                } else {
                    nodePath = '/' + nodeId;
                }
            }
            
            // console.log(`[TD Customizer] Trying to apply ${value} to ${nodePath}.${paramName}`);
            
            const node = scene.node(nodePath);
            if (node && node.p) {
                // Try common parameter names
                const possibleParams = [paramName, 'value', 'input', 'index', 'switch', 'selection', 'option'];
                
                for (const param of possibleParams) {
                    if (param && node.p[param]) {
                        node.p[param].set(value);
                        // console.log(`[TD Customizer] Successfully set ${nodePath}.${param} to ${value}`);
                        return true;
                    }
                }
                
                // Try the first available parameter if none of the common ones work
                const paramKeys = Object.keys(node.p);
                if (paramKeys.length > 0) {
                    const firstParam = paramKeys[0];
                    node.p[firstParam].set(value);
                    // console.log(`[TD Customizer] Set ${nodePath}.${firstParam} to ${value} (fallback)`);
                    return true;
                }
            }
        } catch (e) {
            console.log(`[TD Customizer] Error applying value directly:`, e);
        }
        
        return false;
    }
    
    /**
     * Apply RGB color values directly to the scene
     */
    function applyRGBDirectlyToScene(scene, rNodeId, gNodeId, bNodeId, rgbValues) {
        if (!scene || !rgbValues || rgbValues.length !== 3) return;
        
        try {
            // If we have separate RGB node IDs
            if (gNodeId && bNodeId) {
                // Apply to R component
                const rPath = convertNodeIdToScenePath(rNodeId);
                const rNode = scene.node(rPath);
                if (rNode && rNode.p && rNode.p.colorr) {
                    rNode.p.colorr.set(rgbValues[0]);
                    console.log(`[TD Customizer] Set ${rPath}.colorr to ${rgbValues[0]}`);
                }
                
                // Apply to G component
                const gPath = convertNodeIdToScenePath(gNodeId);
                const gNode = scene.node(gPath);
                if (gNode && gNode.p && gNode.p.colorg) {
                    gNode.p.colorg.set(rgbValues[1]);
                    console.log(`[TD Customizer] Set ${gPath}.colorg to ${rgbValues[1]}`);
                }
                
                // Apply to B component
                const bPath = convertNodeIdToScenePath(bNodeId);
                const bNode = scene.node(bPath);
                if (bNode && bNode.p && bNode.p.colorb) {
                    bNode.p.colorb.set(rgbValues[2]);
                    console.log(`[TD Customizer] Set ${bPath}.colorb to ${rgbValues[2]}`);
                }
            } else {
                // Apply all RGB to a single node
                const path = convertNodeIdToScenePath(rNodeId);
                const node = scene.node(path);
                
                if (node && node.p) {
                    if (node.p.colorr) {
                        node.p.colorr.set(rgbValues[0]);
                        console.log(`[TD Customizer] Set ${path}.colorr to ${rgbValues[0]}`);
                    }
                    if (node.p.colorg) {
                        node.p.colorg.set(rgbValues[1]);
                        console.log(`[TD Customizer] Set ${path}.colorg to ${rgbValues[1]}`);
                    }
                    if (node.p.colorb) {
                        node.p.colorb.set(rgbValues[2]);
                        console.log(`[TD Customizer] Set ${path}.colorb to ${rgbValues[2]}`);
                    }
                }
            }
        } catch (e) {
            console.log(`[TD Customizer] Error applying RGB directly:`, e);
        }
    }
    
    /**
     * Convert a node ID to a scene path
     */
    function convertNodeIdToScenePath(nodeId) {
        if (nodeId.startsWith('/')) {
            return nodeId;
        }
        
        if (nodeId.includes('-')) {
            const parts = nodeId.split('-');
            // Remove parameter suffix if present
            if (['colorr', 'colorg', 'colorb'].includes(parts[parts.length - 1])) {
                parts.pop();
            }
            return '/' + parts.join('/');
        }
        
        return '/' + nodeId;
    }
    
    /**
     * Apply any pending updates to the scene
     */
    function applyPendingUpdates(scene) {
        if (!scene) return;
        
        for (const nodeId in pendingUpdates) {
            const update = pendingUpdates[nodeId];
            logDebug('Applying pending update for', nodeId, ':', update.value);
            
            if (window.TDPolygonjs && window.TDPolygonjs.updateNodeParameter) {
                window.TDPolygonjs.updateNodeParameter(update.nodeId, update.paramName, update.value);
            }
        }
        
        // Clear pending updates
        Object.keys(pendingUpdates).forEach(key => delete pendingUpdates[key]);
    }
    
    /**
     * Set up slider controls
     */
    function setupSliders() {
        // Connect the visible sliders to the hidden polygonjs controls
        $('.visible-slider').each(function() {
            const $visibleSlider = $(this);
            const sliderId = $visibleSlider.attr('id');
            const $container = $visibleSlider.closest('[data-node-id]');
            const nodeId = $container.data('node-id');
            
            // We need to find all related elements
            const $polygonJSSlider = $container.find('.polygonjs-control');
            const $textInput = $('#' + sliderId.replace('-slider', '-text'));
            
            logDebug('Setting up slider:', sliderId, 'for node:', nodeId);
            
            // Initialize the text input with the current value
            $textInput.val($visibleSlider.val());
            
            // Handle real-time slider updates
            let sliderTimeout = null;
            $visibleSlider.on('input', function() {
                userInteracting = true;
                const value = parseFloat($visibleSlider.val());
                
                // Update the text display immediately
                if ($textInput.length) {
                    $textInput.val(value);
                }
                
                // Clear any pending timeout
                if (sliderTimeout) {
                    clearTimeout(sliderTimeout);
                }
                
                // Update the scene in real-time
                if (nodeId) {
                    updateSceneParameter(nodeId, 'slider', value);
                }
                
                logDebug('Visible slider input:', value, 'for node:', nodeId);
                
                // Reset interaction flag shortly after user stops dragging
                sliderTimeout = setTimeout(() => { 
                    userInteracting = false;
                }, 300);
            });
            
            // Handle final value when slider is released
            $visibleSlider.on('change', function() {
                const value = parseFloat($visibleSlider.val());
                
                // Update the hidden PolygonJS control
                if ($polygonJSSlider.length) {
                    $polygonJSSlider.val(value);
                }
                
                // Final update to ensure consistency
                if (nodeId) {
                    updateSceneParameter(nodeId, 'slider', value);
                }
                
                logDebug('Visible slider value finalized:', value, 'for node:', nodeId);
            });
            
            // Update the visible slider and hidden input when text input changes
            $textInput.on('input change', function() {
                userInteracting = true;
                const value = parseFloat($textInput.val());
                
                // Ensure value stays within min/max bounds
                const min = parseFloat($visibleSlider.attr('min'));
                const max = parseFloat($visibleSlider.attr('max'));
                const validValue = Math.min(Math.max(value || min, min), max);
                
                // Update the visible slider
                $visibleSlider.val(validValue);
                
                // Update the actual PolygonJS control
                if ($polygonJSSlider.length) {
                    $polygonJSSlider.val(validValue);
                }
                
                // If node ID is available, directly update the scene
                if (nodeId) {
                    updateSceneParameter(nodeId, 'slider', validValue);
                }
                
                // Reset interaction flag after a short delay
                setTimeout(() => { userInteracting = false; }, 100);
            });
            
            // Double-click on the slider to reset to center
            $visibleSlider.on('dblclick', function() {
                const min = parseFloat($visibleSlider.attr('min'));
                const max = parseFloat($visibleSlider.attr('max'));
                const centerValue = (min + max) / 2;
                
                $visibleSlider.val(centerValue);
                $textInput.val(centerValue);
                
                // Update the actual PolygonJS control
                if ($polygonJSSlider.length) {
                    $polygonJSSlider.val(centerValue);
                }
                
                // If node ID is available, directly update the scene
                if (nodeId) {
                    updateSceneParameter(nodeId, 'slider', centerValue);
                }
            });
            
            // Don't trigger change automatically - let forceApplyAllDefaultValues handle initial setup
        });
    }
    
    /**
     * Set up number input controls
     */
    function setupNumberInputs() {
        // Connect the visible number inputs to the hidden polygonjs controls
        $('.visible-number-input').each(function() {
            const $visibleInput = $(this);
            const inputId = $visibleInput.attr('id');
            const $container = $visibleInput.closest('[data-node-id]');
            const nodeId = $container.data('node-id');
            
            // We need to find the actual PolygonJS control
            const $polygonJSInput = $container.find('.polygonjs-control');
            
            // Get min and max if they exist
            const min = $visibleInput.attr('min') !== undefined ? parseFloat($visibleInput.attr('min')) : null;
            const max = $visibleInput.attr('max') !== undefined ? parseFloat($visibleInput.attr('max')) : null;
            
            logDebug('Setting up number input:', inputId, 'for node:', nodeId, 'min:', min, 'max:', max);
            
            $visibleInput.on('input change', function() {
                let value = parseFloat($visibleInput.val());
                
                // Validate the value against min/max if defined
                if (min !== null && !isNaN(min) && (isNaN(value) || value < min)) {
                    value = min;
                    $visibleInput.val(min);
                }
                
                if (max !== null && !isNaN(max) && value > max) {
                    value = max;
                    $visibleInput.val(max);
                }
                
                // Update the actual PolygonJS control
                if ($polygonJSInput.length) {
                    $polygonJSInput.val(value).trigger('change');
                }
                
                // If node ID is available, directly update the scene
                if (nodeId) {
                    updateSceneParameter(nodeId, 'number', value);
                }
                
                logDebug('Number input changed:', value, 'for node:', nodeId);
            });
            
            // Double-click to reset to middle value if min/max are defined
            $visibleInput.on('dblclick', function() {
                if (min !== null && max !== null && !isNaN(min) && !isNaN(max)) {
                    const middle = (min + max) / 2;
                    $visibleInput.val(middle);
                    
                    // Update the actual PolygonJS control
                    if ($polygonJSInput.length) {
                        $polygonJSInput.val(middle).trigger('change');
                    }
                    
                    // If node ID is available, directly update the scene
                    if (nodeId) {
                        updateSceneParameter(nodeId, 'number', middle);
                    }
                }
            });
            
            // Don't trigger change automatically - let forceApplyAllDefaultValues handle initial setup
        });
    }
    
    /**
     * Set up text input controls
     */
    function setupTextInputs() {
        $('input[type="text"].polygonjs-control').each(function() {
            const $input = $(this);
            const inputId = $input.attr('id');
            const $container = $input.closest('[data-node-id]');
            const nodeId = $container.data('node-id');
            
            logDebug('Setting up text input:', inputId, 'for node:', nodeId);
            
            $input.on('input change', function() {
                const value = $input.val();
                
                // If node ID is available, update the scene
                if (nodeId) {
                    updateSceneParameter(nodeId, 'text', value);
                }
            });
            
            // Don't trigger change automatically - let forceApplyAllDefaultValues handle initial setup
        });
    }
    
    /**
     * Set up checkbox controls
     */
    function setupCheckboxes() {
        $('input[type="checkbox"].polygonjs-control').each(function() {
            const $checkbox = $(this);
            const checkboxId = $checkbox.attr('id');
            const $container = $checkbox.closest('[data-node-id]');
            const nodeId = $container.data('node-id');
            
            logDebug('Setting up checkbox:', checkboxId, 'for node:', nodeId);
            
            $checkbox.on('change', function() {
                const value = $checkbox.prop('checked');
                
                // If node ID is available, update the scene
                if (nodeId) {
                    updateSceneParameter(nodeId, 'checkbox', value);
                }
            });
            
            // Don't trigger change automatically - let forceApplyAllDefaultValues handle initial setup
        });
    }
    
    /**
     * Set up dropdown select controls
     */
    function setupDropdowns() {
        $('select.polygonjs-control').each(function() {
            const $select = $(this);
            const selectId = $select.attr('id');
            const $container = $select.closest('[data-node-id]');
            const nodeId = $container.data('node-id');
            
            logDebug('Setting up dropdown:', selectId, 'for node:', nodeId);
            
            $select.on('change', function() {
                const value = $select.val();
                
                // If node ID is available, update the scene
                if (nodeId) {
                    updateSceneParameter(nodeId, 'dropdown', value);
                }
            });
            
            // Don't trigger change automatically - let forceApplyAllDefaultValues handle initial setup
        });
    }
    
    /**
     * Set up color option controls
     */
    function setupColorOptions() {
        // Check for out-of-stock colors in the global color options
        if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
            console.log("Checking global colorOptions for out-of-stock colors:");
            for (const key in window.tdPolygonjs.colorOptions) {
                const color = window.tdPolygonjs.colorOptions[key];
                if (color.in_stock === false) {
                    console.log(`Color ${color.name} (${key}) is out of stock!`);
                }
            }
        }

        // Process all color swatches and mark out-of-stock ones
        // This ensures colors are correctly marked even if JavaScript data is loaded later
        if (window.tdPolygonjs && window.tdPolygonjs.colorOptions) {
            $('.color-swatch').each(function() {
                const $swatch = $(this);
                const $radio = $swatch.find('.color-radio');
                
                if ($radio.length) {
                    const colorKey = $radio.val();
                    if (colorKey && window.tdPolygonjs.colorOptions[colorKey] && 
                        window.tdPolygonjs.colorOptions[colorKey].in_stock === false) {
                        
                        console.log(`Marking color ${colorKey} as out of stock`);
                        $swatch.addClass('out-of-stock');
                        
                        // Replace radio with marker
                        $radio.replaceWith('<span class="out-of-stock-marker"></span>');
                        
                        // Add out-of-stock label if it doesn't exist
                        if (!$swatch.find('.out-of-stock-label').length) {
                            $swatch.append('<span class="out-of-stock-label">Out of Stock</span>');
                        }
                    }
                }
            });
        }

        // Setup radio button color swatches
        $('.color-radio').each(function() {
            const $radio = $(this);
            const $swatch = $radio.closest('.color-swatch');
            const $container = $radio.closest('[data-node-id]');
            const nodeId = $container.data('node-id');
            const $hiddenInput = $container.find('input[type="hidden"]');
            
            logDebug('Setting up color radio:', $radio.attr('name'), 'for node:', nodeId);
            
            $radio.on('change', function() {
                const colorKey = $radio.val();
                const colorName = $swatch.find('.swatch-name').text();
                
                // Skip out-of-stock colors
                if ($swatch.hasClass('out-of-stock')) {
                    logDebug('Ignoring out-of-stock color:', colorName);
                    return;
                }
                
                // Remove active class from all swatches
                $container.find('.color-swatch').removeClass('active');
                
                // Add active class to selected swatch
                $swatch.addClass('active');
                
                // Update hidden input
                if ($hiddenInput.length) {
                    $hiddenInput.val(colorName);
                }
                
                // Check if this is part of an RGB group
                const rgbGroup = $container.data('rgb-group');
                
                // If node ID is available, update the scene
                if (nodeId) {
                    // Get RGB values for the color
                    if (window.tdPolygonjs && window.tdPolygonjs.colorOptions && 
                        window.tdPolygonjs.colorOptions[colorKey] &&
                        window.tdPolygonjs.colorOptions[colorKey].rgb) {
                        
                        const rgbValues = window.tdPolygonjs.colorOptions[colorKey].rgb;
                        logDebug('Selected color RGB values:', rgbValues);
                        
                        if (rgbGroup) {
                            updateRGBColor(nodeId, rgbGroup, colorKey, rgbValues);
                        } else {
                            updateSceneParameter(nodeId, 'color', rgbValues);
                        }
                    } else {
                        logDebug('No RGB values found for color key:', colorKey);
                        updateSceneParameter(nodeId, 'color', colorName);
                    }
                }
            });
        });
        
        // Clicking on swatch should trigger radio button
        $('.color-swatch').on('click', function(e) {
            // Prevent clicking on out-of-stock colors
            if ($(this).hasClass('out-of-stock')) {
                e.preventDefault();
                return false;
            }
            
            if (!$(e.target).is('input')) {
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            }
        });
        
        // Don't trigger change automatically - let forceApplyAllDefaultValues handle initial setup
        
        // Run the out-of-stock marker function after a short delay
        setTimeout(checkOutOfStockColors, 500);
    }
    
    /**
     * Update RGB color values for all components
     * This revised function ensures all three components use the same base path
     */
    function updateRGBColor(nodeId, rgbGroup, colorKey, rgbValues) {
        if (!rgbValues || rgbValues.length !== 3) {
            logDebug('Invalid RGB values for color update');
            return;
        }
        
        logDebug('Updating RGB color for group:', rgbGroup, 'values:', rgbValues);
        
        // First, extract the base node path from the R component
        let basePath = extractBasePathFromNodeId(nodeId);
        if (!basePath) {
            logDebug('Could not extract base path from node ID:', nodeId);
            return;
        }
        
        logDebug('Using base path for RGB components:', basePath);
        
        // Update each component individually using the same base path
        updateSceneComponent(nodeId, 'colorr', rgbValues[0]);
        
        // For G and B components, use the same base path with appropriate parameter
        const gNodeId = basePath + '-colorg';
        const bNodeId = basePath + '-colorb';
        
        updateSceneComponent(gNodeId, 'colorg', rgbValues[1]);
        updateSceneComponent(bNodeId, 'colorb', rgbValues[2]);
    }

    /**
     * Extract the base path from a node ID (remove parameter suffix)
     */
    function extractBasePathFromNodeId(nodeId) {
        // For IDs like "geo2-MAT-meshStandard1-colorr", return "geo2-MAT-meshStandard1"
        if (!nodeId || !nodeId.includes('-')) {
            return null;
        }
        
        const parts = nodeId.split('-');
        
        // Check if the last part is a color component
        const lastPart = parts[parts.length - 1];
        if (['colorr', 'colorg', 'colorb'].includes(lastPart)) {
            // Remove the last part
            parts.pop();
        }
        
        return parts.join('-');
    }

    /**
     * Update a single component of an RGB color
     */
    function updateSceneComponent(nodeId, paramName, value) {
        logDebug(`Updating ${nodeId}.${paramName} = ${value}`);
        
        // Extract the path in the format that PolygonJS expects
        let nodePath = null;
        
        if (nodeId.includes('-')) {
            const parts = nodeId.split('-');
            nodePath = '/' + parts.join('/');
            logDebug('Converted node ID to path:', nodePath);
        }
        
        // If bridge is ready, update directly
        if (bridgeReady && window.TDPolygonjs && window.TDPolygonjs.updateNodeParameter) {
            if (nodePath) {
                // Use the extracted path directly
                window.TDPolygonjs.updateNodeParameter(nodePath, paramName, value);
            } else {
                // Fall back to normal update
                window.TDPolygonjs.updateNodeParameter(nodeId, paramName, value);
            }
        } else {
            // Store for later
            pendingUpdates[nodeId + '-' + paramName] = {
                nodeId: nodeId,
                paramName: paramName,
                value: value
            };
        }
        
        // Send message to any iframes
        sendMessageToIframes(nodeId, paramName, value, 'color-component');
    }
    
    /**
     * Update a parameter in the PolygonJS scene
     */
    // Track the last update to prevent duplicate updates
    let lastUpdateTimestamp = {};
    
    function updateSceneParameter(nodeId, controlType, value) {
        // Don't update if we're applying defaults
        if (applyingDefaults) {
            logDebug('Skipping scene update - applying defaults');
            return;
        }
        
        // Prevent duplicate updates within a short timeframe
        const updateKey = `${nodeId}-${value}`;
        const now = Date.now();
        if (lastUpdateTimestamp[updateKey] && (now - lastUpdateTimestamp[updateKey]) < 50) {
            logDebug('Skipping duplicate update');
            return;
        }
        lastUpdateTimestamp[updateKey] = now;
        
        logDebug('Updating scene parameter:', nodeId, controlType, value);
        
        // Extract parameter name from nodeId (if needed)
        const idParts = nodeId.split('-');
        let paramName = idParts.length > 1 ? idParts[idParts.length - 1] : nodeId;
        
        // For sliders, the parameter name is typically the last part
        if (controlType === 'slider' && idParts.length > 2) {
            // For IDs like "doos-ctrl_doos-breedte", extract "breedte"
            paramName = idParts[idParts.length - 1];
        }
        
        // Special handling for RGB color values
        if (controlType === 'color' && Array.isArray(value) && value.length === 3) {
            logDebug('RGB color array detected:', value);
            
            // If bridge is ready, update directly
            if (bridgeReady && window.TDPolygonjs && window.TDPolygonjs.updateNodeParameter) {
                window.TDPolygonjs.updateNodeParameter(nodeId, 'colorr', value);
            } else {
                // Store for later
                pendingUpdates[nodeId] = {
                    nodeId: nodeId,
                    paramName: 'colorr', // Use colorr as the main param name
                    value: value,
                    controlType: controlType
                };
            }
            
            // Send message to iframes
            sendMessageToIframes(nodeId, 'colorr', value, controlType);
            
            return;
        }
        
        // For dropdown, we pass the selected value directly
        if (controlType === 'dropdown') {
            // Convert to number if it looks like a number
            const numValue = parseFloat(value);
            const finalValue = !isNaN(numValue) ? numValue : value;
            
            // NEW CODE: Extract the parameter name from nodeId if possible
            let paramToUse = 'value'; // Default parameter name
            
            // Try to extract parameter name from the node ID (e.g., geo1-switch2-input -> input)
            if (nodeId && nodeId.includes('-')) {
                const parts = nodeId.split('-');
                const lastPart = parts[parts.length - 1];
                
                // Common parameter names for switches and other controls
                const knownParams = ['input', 'index', 'switch', 'selection', 'option'];
                if (knownParams.includes(lastPart)) {
                    paramToUse = lastPart;
                    logDebug(`Detected parameter name from node ID: ${paramToUse}`);
                }
            }
            
            // Look for parameter hint in idParts (more specific targeting)
            if (nodeId && idParts.length > 2) {
                const possibleParam = idParts[idParts.length - 1];
                // If the last part looks like a parameter name, use it
                if (/^(input|index|option|value|param|select)$/i.test(possibleParam)) {
                    paramToUse = possibleParam;
                    logDebug(`Using parameter name from ID: ${paramToUse}`);
                }
            }
            
            // Send the update with the detected parameter name
            if (bridgeReady && window.TDPolygonjs && window.TDPolygonjs.updateNodeParameter) {
                window.TDPolygonjs.updateNodeParameter(nodeId, paramToUse, finalValue);
                logDebug(`Sending dropdown value to parameter: ${paramToUse}`);
            } else {
                pendingUpdates[nodeId] = {
                    nodeId: nodeId,
                    paramName: paramToUse,
                    value: finalValue,
                    controlType: controlType
                };
            }
            
            // Send message to iframes with the proper parameter name
            sendMessageToIframes(nodeId, paramToUse, finalValue, controlType);
            
            return;
        }
        
        // Normal parameter updates (non-RGB, non-dropdown)
        // If bridge is ready, update directly
        if (bridgeReady && window.TDPolygonjs && window.TDPolygonjs.updateNodeParameter) {
            // Only use the bridge during initialization or when user is not interacting
            if (!userInteracting || applyingDefaults) {
                logDebug('Bridge ready, updating node parameter directly');
                window.TDPolygonjs.updateNodeParameter(nodeId, paramName, value);
            } else {
                // During user interaction, update the scene directly to avoid bridge overhead
                logDebug('User interacting, updating scene directly');
                // Try different ways to access the scene
                const scene = getActiveScene();
                if (scene) {
                    applyValueDirectlyToScene(scene, nodeId, value);
                } else {
                    // Fallback to normal bridge update
                    window.TDPolygonjs.updateNodeParameter(nodeId, paramName, value);
                }
            }
        } else {
            // Otherwise, store for later
            logDebug('Bridge not ready, storing update for later');
            pendingUpdates[nodeId] = {
                nodeId: nodeId,
                paramName: paramName,
                value: value,
                controlType: controlType
            };
        }
        
        // Send message to iframes
        sendMessageToIframes(nodeId, paramName, value, controlType);
    }
    
    // Track last iframe message to prevent duplicates
    let lastIframeMessage = {};
    
    /**
     * Send a message to all iframes
     */
    function sendMessageToIframes(nodeId, paramName, value, controlType) {
        // Skip during direct user interaction to prevent circular updates
        if (userInteracting && !applyingDefaults) {
            return;
        }
        
        // Prevent duplicate messages
        const messageKey = `${nodeId}-${paramName}-${value}`;
        const now = Date.now();
        if (lastIframeMessage[messageKey] && (now - lastIframeMessage[messageKey]) < 100) {
            return;
        }
        lastIframeMessage[messageKey] = now;
        
        try {
            const iframes = document.querySelectorAll('iframe');
            if (iframes.length > 0) {
                logDebug('Sending message to', iframes.length, 'iframes');
                
                iframes.forEach(function(iframe) {
                    try {
                        iframe.contentWindow.postMessage({
                            type: 'updateParameter',
                            nodeId: nodeId,
                            paramName: paramName,
                            value: value,
                            controlType: controlType
                        }, '*');
                    } catch (error) {
                        // Ignore cross-origin errors
                    }
                });
            }
        } catch (error) {
            logDebug('Error sending message to iframes:', error);
        }
    }
    
    /**
     * Ensure all customizer fields are added to the add to cart form
     */
    function setupAddToCartForm() {
        const $form = $('form.cart');
        if (!$form.length) return;
        
        // Get all control elements
        const $controls = $('.polygonjs-control');
        
        $form.on('submit', function() {
            // Ensure all controls are included in the form
            $controls.each(function() {
                const $control = $(this);
                const name = $control.attr('name');
                
                // Skip if the control is already in the form or is a radio button (we use the hidden field)
                if (!name || name.endsWith('_radio') || $form.find(`input[name="${name}"]`).length > 0) {
                    return;
                }
                
                // Create a hidden input with the control's value
                const value = $control.is(':checkbox') 
                    ? ($control.is(':checked') ? '1' : '0')
                    : $control.val();
                
                $('<input>').attr({
                    type: 'hidden',
                    name: name,
                    value: value
                }).appendTo($form);
            });
        });
    }
    
    /**
     * Log debug messages to console if debug mode is enabled
     */
    function logDebug(...args) {
        if (debug) {
            console.log('👉 [Customizer]', ...args);
            
            // Add to visual debug panel if it exists
            const $debugPanel = $('#td-debug-panel');
            if ($debugPanel.length) {
                const timestamp = new Date().toLocaleTimeString();
                const message = args.map(arg => {
                    if (typeof arg === 'object') {
                        return JSON.stringify(arg);
                    }
                    return String(arg);
                }).join(' ');
                
                $debugPanel.append(`<div class="debug-message"><span class="debug-time">${timestamp}</span> ${message}</div>`);
                
                // Scroll to bottom
                $debugPanel.scrollTop($debugPanel[0].scrollHeight);
            }
        }
    }
    
    // Add a debug panel to the page if in debug mode
    if (debug) {
        $('body').append(`
            <div id="td-debug-console">
                <div id="td-debug-header">
                    <span>Debug Console</span>
                    <button id="td-debug-toggle">Hide</button>
                </div>
                <div id="td-debug-content">
                    <div id="td-debug-unit-panel">
                        <h4>Measurement Units</h4>
                        <p>Current unit: <strong>${window.tdPolygonjs?.measurementUnit || "Not set"}</strong></p>
                        <div class="unit-test">
                            <span>Test unit for Width: 
                                <span id="test-unit-width" style="font-weight:bold;"></span>
                            </span>
                        </div>
                    </div>
                    <div id="td-debug-panel"></div>
                </div>
            </div>
            <style>
                #td-debug-console {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    width: 400px;
                    background: #fff;
                    border: 1px solid #ccc;
                    border-radius: 5px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    z-index: 9999;
                    font-family: monospace;
                }
                #td-debug-header {
                    padding: 8px;
                    background: #f5f5f5;
                    border-bottom: 1px solid #ccc;
                    display: flex;
                    justify-content: space-between;
                }
                #td-debug-toggle {
                    border: none;
                    background: #e0e0e0;
                    padding: 2px 5px;
                    cursor: pointer;
                }
                #td-debug-content {
                    max-height: 300px;
                    overflow-y: auto;
                }
                #td-debug-panel {
                    padding: 10px;
                    font-size: 12px;
                }
                #td-debug-unit-panel {
                    padding: 10px;
                    border-bottom: 1px solid #eee;
                }
                #td-debug-unit-panel h4 {
                    margin: 0 0 5px 0;
                }
                .debug-message {
                    margin-bottom: 5px;
                    word-break: break-word;
                }
                .debug-time {
                    color: #999;
                    margin-right: 5px;
                }
                .unit-test {
                    margin-top: 10px;
                    padding: 5px;
                    background: #f9f9f9;
                    border: 1px solid #eee;
                }
            </style>
        `);
        
        // Toggle debug panel
        $('#td-debug-toggle').on('click', function() {
            const $content = $('#td-debug-content');
            if ($content.is(':visible')) {
                $content.hide();
                $(this).text('Show');
            } else {
                $content.show();
                $(this).text('Hide');
            }
        });
        
        // Enhanced measurement unit handling
        if (window.tdPolygonjs) {
            // Get units from multiple places to ensure we find them
            let unitText = $(".measurement-unit").first().text() || "";
            let unitData = $(".measurement-unit").first().data('unit') || "";
            
            window.tdPolygonjs.measurementUnit = unitText || unitData || "Not found";
            $('#test-unit-width').text(window.tdPolygonjs.measurementUnit);
            
            // Add debug info about all measurement units found
            if (debug) {
                setTimeout(function() {
                    const unitElements = document.querySelectorAll('.measurement-unit');
                    console.log('Measurement units found:', unitElements.length);
                    
                    $('#td-debug-unit-panel').append('<div class="unit-debug-section"><h5>Unit Elements Found: ' + unitElements.length + '</h5></div>');
                    
                    unitElements.forEach((el, index) => {
                        console.log('Unit #' + index + ' text:', el.textContent);
                        console.log('Unit #' + index + ' data-unit:', el.getAttribute('data-unit'));
                        console.log('Unit #' + index + ' visibility:', window.getComputedStyle(el).display);
                        console.log('Unit #' + index + ' dimensions:', JSON.stringify(el.getBoundingClientRect()));
                        
                        $('#td-debug-unit-panel .unit-debug-section').append(`
                            <div class="unit-element-debug">
                                <div>Element #${index} for: ${el.parentNode.parentNode.querySelector('label')?.textContent || 'Unknown'}</div>
                                <div>Text content: "${el.textContent}"</div>
                                <div>Data-unit: "${el.getAttribute('data-unit')}"</div>
                                <div>Display: ${window.getComputedStyle(el).display}</div>
                                <div>Visibility: ${window.getComputedStyle(el).visibility}</div>
                            </div>
                        `);
                    });
                    
                    // Attempt to force display all units that might be hidden
                    unitElements.forEach(el => {
                        try {
                            // Create a styled overlay element to force unit display
                            const existingOverlay = el.querySelector('.force-unit-display');
                            if (!existingOverlay && el.getAttribute('data-unit')) {
                                const overlay = document.createElement('div');
                                overlay.className = 'force-unit-display';
                                overlay.textContent = el.getAttribute('data-unit');
                                overlay.style.cssText = 'position:absolute; top:0; left:0; right:0; bottom:0; background:#fff; color:#333; display:flex; align-items:center; justify-content:center; font-weight:bold; border:1px solid #999; border-radius:3px; z-index:9999;';
                                el.style.position = 'relative';
                                el.appendChild(overlay);
                            }
                        } catch(e) {
                            console.error('Error forcing unit display:', e);
                        }
                    });
                }, 1000);
            }
        }
    }
})(jQuery);