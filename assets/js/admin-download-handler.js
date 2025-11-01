/**
 * TD Link - Admin Download Handler (Simplified)
 * 
 * Uses the existing download functionality in the iframes
 */

(function($) {
    'use strict';

    // Initialize when the document is ready
    $(document).ready(function() {
        initDownloadButtons();
    });

    /**
     * Initialize download buttons
     */
    function initDownloadButtons() {
        $('.td-download-model-btn').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const scene = $button.data('scene');
            const index = $button.data('index');
            const context = $button.data('context');
            
            // Disable button and show loading indicator
            $button.prop('disabled', true);
            $button.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' + tdGltfDownload.downloadingText);
            
            // Find the iframe for this model
            const iframePrefix = context === 'cart' ? 'td-cart-iframe-' : 'td-iframe-';
            const iframeId = iframePrefix + index;
            const iframe = document.getElementById(iframeId);
            
            if (!iframe) {
                handleError($button, 'Could not find iframe for model');
                return;
            }
            
            // Try to trigger download in the iframe
            try {
                triggerDownloadInIframe(iframe, $button, scene);
            } catch (error) {
                console.error("Error triggering download:", error);
                handleError($button, 'Error triggering download: ' + error.message);
            }
        });
        
        // Initialize "Download All Models" buttons for orders
        $('.td-download-all-order-models-btn').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const orderId = $button.data('order-id');
            
            // Disable button and show loading indicator
            $button.prop('disabled', true);
            $button.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' + tdGltfDownload.downloadingText);
            
            // Make AJAX request to download all models from this order
            $.ajax({
                url: tdGltfDownload.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'td_download_order_models',
                    nonce: tdGltfDownload.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger download
                        triggerDownload(response.data.download_url, 'order_' + orderId + '_models.zip');
                        
                        // Reset button after a short delay
                        setTimeout(function() {
                            resetButton($button);
                        }, 2000);
                    } else {
                        handleError($button, response.data.message || tdGltfDownload.errorText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error, xhr.responseText);
                    handleError($button, tdGltfDownload.errorText + ' (Status: ' + status + ')');
                }
            });
        });
        
        // Initialize "Download All Models" buttons for carts
        $('.td-download-all-cart-models-btn').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const cartKey = $button.data('cart-key');
            
            // Disable button and show loading indicator
            $button.prop('disabled', true);
            $button.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' + tdGltfDownload.downloadingText);
            
            // Make AJAX request to download all models from this cart
            $.ajax({
                url: tdGltfDownload.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'td_download_cart_models',
                    nonce: tdGltfDownload.nonce,
                    cart_key: cartKey
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger download
                        triggerDownload(response.data.download_url, 'cart_models.zip');
                        
                        // Reset button after a short delay
                        setTimeout(function() {
                            resetButton($button);
                        }, 2000);
                    } else {
                        handleError($button, response.data.message || tdGltfDownload.errorText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error, xhr.responseText);
                    handleError($button, tdGltfDownload.errorText + ' (Status: ' + status + ')');
                }
            });
        });
    }
    
    /**
     * Collect current parameters from the scene to include in the download
     */
    function collectSceneParameters(iframe) {
        try {
            // Check if we have access to the scene
            if (!iframe.contentWindow || !iframe.contentWindow.polyScene) {
                return null;
            }
            
            const scene = iframe.contentWindow.polyScene;
            const parameters = {};
            
            // Get TDPolygonjs data if available
            if (iframe.contentWindow.tdPolygonjs && iframe.contentWindow.tdPolygonjs.parameters) {
                const sceneParams = iframe.contentWindow.tdPolygonjs.parameters;
                
                // Loop through parameters to get current values
                for (const nodeId in sceneParams) {
                    const param = sceneParams[nodeId];
                    
                    try {
                        // Convert to node path
                        const nodePath = "/" + nodeId.replace(/-/g, "/");
                        const node = scene.node(nodePath);
                        
                        if (node && node.p) {
                            // Get parameter name from display name
                            let paramKey = nodeId.toLowerCase().replace(/[^a-z0-9]/g, '_');
                            
                            // Try to get current value
                            let paramValue = null;
                            
                            // Handle different control types
                            switch (param.control_type) {
                                case 'color':
                                    // For RGB colors, get the current r,g,b values
                                    if (node.p.colorr && node.p.colorg && node.p.colorb) {
                                        paramValue = [
                                            node.p.colorr.value,
                                            node.p.colorg.value,
                                            node.p.colorb.value
                                        ];
                                    }
                                    break;
                                    
                                case 'number':
                                case 'slider':
                                    // For number controls, we need to find the right parameter
                                    // Often something like "value" or the last part of the node ID
                                    const parts = nodeId.split('-');
                                    const lastPart = parts[parts.length - 1];
                                    
                                    if (node.p[lastPart]) {
                                        paramValue = node.p[lastPart].value;
                                    } else if (node.p.value) {
                                        paramValue = node.p.value.value;
                                    }
                                    break;
                                    
                                case 'text':
                                    // For text controls, typically the "text" parameter
                                    if (node.p.text) {
                                        paramValue = node.p.text.value;
                                    }
                                    break;
                                    
                                default:
                                    // For other types, try a few common parameter names
                                    const possibleParams = ['value', 'input', 'text', 'checked'];
                                    for (const p of possibleParams) {
                                        if (node.p[p]) {
                                            paramValue = node.p[p].value;
                                            break;
                                        }
                                    }
                            }
                            
                            // If we found a value, add it to our parameters
                            if (paramValue !== null) {
                                parameters[paramKey] = {
                                    'node_id': nodeId,
                                    'display_name': param.display_name,
                                    'control_type': param.control_type,
                                    'value': paramValue
                                };
                            }
                        }
                    } catch (e) {
                        console.warn("Error collecting parameter value:", e);
                        // Continue with other parameters
                    }
                }
            }
            
            return Object.keys(parameters).length > 0 ? parameters : null;
            
        } catch (e) {
            console.error("Error collecting scene parameters:", e);
            return null;
        }
    }
    
    /**
     * Trigger download in the iframe
     */
    function triggerDownloadInIframe(iframe, $button, sceneName) {
        console.log("Attempting to trigger download in iframe for scene:", sceneName);
        
        // Try to collect current parameters
        const sceneParameters = collectSceneParameters(iframe);
        console.log("Collected parameters:", sceneParameters);
        
        // If we have current parameters, make an AJAX request to download the model with these parameters
        if (sceneParameters) {
            const data = {
                action: 'td_download_model',
                nonce: tdGltfDownload.nonce,
                context: $button.data('context') || 'model',
                item_id: $button.data('item-id') || '',
                item_key: $button.data('item-key') || '',
                scene: sceneName,
                product_id: $button.data('product-id') || 0,
                parameters: JSON.stringify(sceneParameters)
            };
            
            $.ajax({
                url: tdGltfDownload.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        // Trigger download
                        triggerDownload(response.data.download_url, sceneName + '.glb');
                        
                        // Reset button after a short delay
                        setTimeout(function() {
                            resetButton($button);
                        }, 2000);
                    } else {
                        handleError($button, response.data.message || tdGltfDownload.errorText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error, xhr.responseText);
                    handleError($button, tdGltfDownload.errorText + ' (Status: ' + status + ')');
                    
                    // Fall back to direct download methods
                    tryDirectDownloadMethods(iframe, $button, sceneName);
                }
            });
            
            return;
        }
        
        // If we couldn't collect parameters or the AJAX approach failed, 
        // fall back to direct download methods
        tryDirectDownloadMethods(iframe, $button, sceneName);
    }
    
    /**
     * Try various direct download methods as a fallback
     */
    function tryDirectDownloadMethods(iframe, $button, sceneName) {
        // Different approaches to try
        const approaches = [
            // Approach 1: Click the download button
            function() {
                const downloadBtn = iframe.contentDocument.getElementById('download-button');
                if (downloadBtn) {
                    console.log("Found download button in iframe, clicking it");
                    downloadBtn.click();
                    return true;
                }
                return false;
            },
            
            // Approach 2: Look for any button with download text
            function() {
                const buttons = iframe.contentDocument.querySelectorAll('button');
                for (const btn of buttons) {
                    if (btn.textContent && btn.textContent.toLowerCase().includes('download')) {
                        console.log("Found button with download text:", btn.textContent);
                        btn.click();
                        return true;
                    }
                }
                return false;
            },
            
            // Approach 3: Access the polyScene and find the exporterGLTF node
            function() {
                if (iframe.contentWindow.polyScene || 
                    (iframe.contentWindow.polygonjs && iframe.contentWindow.polygonjs.scene)) {
                    
                    const scene = iframe.contentWindow.polyScene || 
                                 iframe.contentWindow.polygonjs.scene;
                    
                    // First, check if we have a preconfigured path in TDPolygonjs
                    let exporterNodePath = null;
                    
                    if (iframe.contentWindow.TDPolygonjs && 
                        typeof iframe.contentWindow.TDPolygonjs.getExporterNodePath === 'function') {
                        exporterNodePath = iframe.contentWindow.TDPolygonjs.getExporterNodePath();
                        console.log("Found configured exporter path:", exporterNodePath);
                    }
                    
                    // Try the configured path first
                    if (exporterNodePath) {
                        try {
                            const exporter = scene.node(exporterNodePath);
                            if (exporter && exporter.p && exporter.p.download) {
                                console.log("Using configured exporter node at:", exporterNodePath);
                                exporter.p.download.pressButton();
                                return true;
                            }
                        } catch (e) {
                            console.warn("Failed to use configured exporter path:", e);
                            // Fall through to try common paths
                        }
                    }
                    
                    // Try common exporter paths as fallback
                    const exporterPaths = [
                        '/geo1/exporterGLTF1',
                        '/geo2/exporterGLTF1',
                        '/doos/exporterGLTF1',
                        '/geo1/export_GLTF',
                        '/geo2/export_GLTF'
                    ];
                    
                    for (const path of exporterPaths) {
                        try {
                            const exporter = scene.node(path);
                            if (exporter && exporter.p && exporter.p.download) {
                                console.log("Found exporter node at:", path);
                                exporter.p.download.pressButton();
                                return true;
                            }
                        } catch (e) {
                            // Continue to next path
                        }
                    }
                }
                return false;
            },
            
            // Approach 4: Inject a script to trigger download
            function() {
                const script = iframe.contentDocument.createElement('script');
                script.textContent = `
                    (function() {
                        // Try to find download button
                        const downloadBtn = document.getElementById('download-button');
                        if (downloadBtn) {
                            downloadBtn.click();
                            return;
                        }
                        
                        // Try to find polyScene
                        const scene = window.polyScene || (window.polygonjs ? window.polygonjs.scene : null);
                        if (scene) {
                            // Try to get configured exporter path
                            let exporterNodePath = null;
                            if (window.TDPolygonjs && typeof window.TDPolygonjs.getExporterNodePath === 'function') {
                                exporterNodePath = window.TDPolygonjs.getExporterNodePath();
                                console.log("Using configured exporter path:", exporterNodePath);
                                
                                try {
                                    const exporter = scene.node(exporterNodePath);
                                    if (exporter && exporter.p && exporter.p.download) {
                                        exporter.p.download.pressButton();
                                        return;
                                    }
                                } catch (e) {
                                    console.warn("Failed to use configured exporter path:", e);
                                    // Fall through to try common paths
                                }
                            }
                            
                            // Try common exporter paths
                            const exporterPaths = [
                                '/geo1/exporterGLTF1',
                                '/geo2/exporterGLTF1',
                                '/doos/exporterGLTF1',
                                '/geo1/export_GLTF',
                                '/geo2/export_GLTF'
                            ];
                            
                            for (const path of exporterPaths) {
                                try {
                                    const exporter = scene.node(path);
                                    if (exporter && exporter.p && exporter.p.download) {
                                        exporter.p.download.pressButton();
                                        return;
                                    }
                                } catch (e) {
                                    // Continue to next path
                                }
                            }
                        }
                    })();
                `;
                iframe.contentDocument.head.appendChild(script);
                return true; // We don't know if it worked, but we tried
            }
        ];
        
        // Try each approach
        let success = false;
        for (const approach of approaches) {
            try {
                success = approach();
                if (success) break;
            } catch (e) {
                console.log("Approach failed:", e);
            }
        }
        
        if (success) {
            console.log("Successfully triggered download in iframe");
            
            // Reset button after a delay (file should be downloading by now)
            setTimeout(function() {
                resetButton($button);
            }, 2000);
        } else {
            console.log("All approaches failed to trigger download");
            handleError($button, "Could not find download functionality in the 3D viewer");
        }
    }
    
    /**
     * Trigger a file download
     * 
     * @param {string} url URL of the file to download
     * @param {string} filename The suggested filename
     */
    function triggerDownload(url, filename) {
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        
        // Clean up
        setTimeout(function() {
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }, 500);
    }
    
    /**
     * Reset a button to its original state
     * 
     * @param {jQuery} $button The button jQuery element
     */
    function resetButton($button) {
        $button.prop('disabled', false);
        
        // Determine if this is an "All Models" button or single model button
        if ($button.hasClass('td-download-all-order-models-btn')) {
            $button.html('<span class="dashicons dashicons-download"></span> ' + 'Download All Models (ZIP)');
        } else if ($button.hasClass('td-download-all-cart-models-btn')) {
            $button.html('<span class="dashicons dashicons-download"></span> ' + 'Download All Models (ZIP)');
        } else {
            $button.html('<span class="dashicons dashicons-download"></span> ' + 'Download GLTF');
        }
    }
    
    /**
     * Handle error when downloading a model
     * 
     * @param {jQuery} $button The button jQuery element
     * @param {string} errorMessage The error message to display
     */
    function handleError($button, errorMessage) {
        $button.prop('disabled', false);
        $button.html('<span class="dashicons dashicons-warning"></span> ' + tdGltfDownload.errorText);
        
        // Show error notification
        if (typeof errorMessage === 'string') {
            alert(errorMessage);
        }
        
        // Reset button after a delay
        setTimeout(function() {
            resetButton($button);
        }, 3000);
    }

})(jQuery);