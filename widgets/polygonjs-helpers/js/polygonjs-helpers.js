/**
 * TD Link Polygonjs Helpers - JavaScript
 * Handles the helper controls and communication with the 3D viewer
 */
(function($) {
    $(document).ready(function() {
        initHelperElements();
    });

    /**
     * Initialize all helper elements
     */
    function initHelperElements() {
        // Find all helper containers
        const helperContainers = document.querySelectorAll('.td-helpers-container');
        
        if (helperContainers.length === 0) {
            console.log('No helper elements found on page');
            return;
        }
        
        console.log('Found ' + helperContainers.length + ' helper elements');
        
        // Initialize each container
        helperContainers.forEach(container => {
            initContainer(container);
        });
    }
    
    /**
     * Initialize a single helper container
     */
    function initContainer(container) {
        const toggleBtn = container.querySelector('.td-helpers-toggle');
        const controlsContainer = container.querySelector('.td-helpers-controls');
        
        // Skip if no toggle button (not collapsible)
        if (!toggleBtn || !controlsContainer) return;
        
        const toggleIcon = toggleBtn.querySelector('.dashicons');
        
        // Start with current state
        let isExpanded = !controlsContainer.classList.contains('collapsed');
        
        // Toggle controls visibility
        toggleBtn.addEventListener('click', function() {
            isExpanded = !isExpanded;
            
            if (isExpanded) {
                controlsContainer.classList.remove('collapsed');
                toggleIcon.classList.remove('dashicons-arrow-down-alt2');
                toggleIcon.classList.add('dashicons-arrow-up-alt2');
            } else {
                controlsContainer.classList.add('collapsed');
                toggleIcon.classList.remove('dashicons-arrow-up-alt2');
                toggleIcon.classList.add('dashicons-arrow-down-alt2');
            }
        });
        
        // Connect to target viewer
        connectToViewer(container);
    }
    
    /**
     * Connect helper controls to a target viewer
     */
    function connectToViewer(container) {
        // Get product ID and target viewer ID
        const productId = container.dataset.productId;
        const targetViewerId = container.dataset.targetViewer;
        
        // If we have a specific target viewer ID, use that
        let targetViewer = null;
        if (targetViewerId) {
            targetViewer = document.getElementById(targetViewerId);
        } 
        // Otherwise, find the first viewer with matching product ID
        else if (productId) {
            const viewerContainers = document.querySelectorAll('.td-viewer-container');
            viewerContainers.forEach(viewer => {
                if (viewer.dataset.productId === productId) {
                    targetViewer = viewer;
                    return;
                }
            });
        }
        
        if (!targetViewer) {
            console.log('No target viewer found for helpers');
            return;
        }
        
        // Find the iframe inside the viewer
        const iframe = targetViewer.querySelector('iframe');
        if (!iframe) {
            console.log('No iframe found in target viewer');
            return;
        }
        
        console.log('Connected helpers to viewer iframe');
        
        // Find all controls
        const helperControls = container.querySelectorAll('.polygonjs-control');
        console.log('Found ' + helperControls.length + ' helper controls');
        
        // Connect each control
        helperControls.forEach(control => {
            const controlType = control.type || control.tagName.toLowerCase();
            const nodeId = control.closest('[data-node-id]')?.dataset.nodeId;
            
            if (!nodeId) {
                console.log('No node ID found for control');
                return;
            }
            
            console.log('Connecting control for node ID: ' + nodeId);
            
            // Add event listener based on control type
            switch (controlType) {
                case 'range':
                case 'number':
                case 'text':
                case 'select':
                    control.addEventListener('input', function() {
                        console.log('Sending value to iframe: ' + control.value + ' for node: ' + nodeId);
                        sendValueToIframe(iframe, nodeId, control.value);
                    });
                    break;
                    
                case 'checkbox':
                    control.addEventListener('change', function() {
                        console.log('Sending checkbox value to iframe: ' + (control.checked ? 1 : 0) + ' for node: ' + nodeId);
                        sendValueToIframe(iframe, nodeId, control.checked ? 1 : 0);
                    });
                    break;
            }
            
            // Trigger initial update after iframe is loaded
            iframe.addEventListener('load', function onceLoaded() {
                // Remove this event to avoid multiple triggers
                iframe.removeEventListener('load', onceLoaded);
                
                console.log('Iframe loaded, sending initial values');
                
                // Wait for bridge to be ready
                setTimeout(() => {
                    // Trigger control update
                    if (controlType === 'checkbox') {
                        sendValueToIframe(iframe, nodeId, control.checked ? 1 : 0);
                    } else {
                        sendValueToIframe(iframe, nodeId, control.value);
                    }
                }, 1000);
            });
        });
        
        // Listen for messages from iframe
        window.addEventListener('message', function(event) {
            // Check if message is from our iframe
            if (event.source !== iframe.contentWindow) return;
            
            // Handle modelReady event
            if (event.data && event.data.type === 'modelReady') {
                console.log('Model is ready, sending all helper values');
                
                // Trigger all controls to send their values
                setTimeout(() => {
                    helperControls.forEach(control => {
                        const nodeId = control.closest('[data-node-id]')?.dataset.nodeId;
                        if (!nodeId) return;
                        
                        if (control.type === 'checkbox') {
                            sendValueToIframe(iframe, nodeId, control.checked ? 1 : 0);
                        } else {
                            sendValueToIframe(iframe, nodeId, control.value);
                        }
                    });
                }, 500);
            }
        });
    }
    
    /**
     * Send parameter value to the iframe
     */
    function sendValueToIframe(iframe, nodeId, value) {
        // Convert to appropriate type
        if (!isNaN(value) && value !== '') {
            value = parseFloat(value);
        }
        
        // Send message to iframe
        iframe.contentWindow.postMessage({
            type: 'updateParam',
            nodeId: nodeId,
            value: value
        }, '*');
    }
})(jQuery);