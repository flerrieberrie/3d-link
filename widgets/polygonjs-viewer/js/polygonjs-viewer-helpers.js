/**
 * TD Link Polygonjs Viewer Helpers - JavaScript
 * Handles the helper controls within the Bricks element
 */
(function($) {
    $(document).ready(function() {
        initHelperControls();
    });

    /**
     * Initialize helper controls
     */
    function initHelperControls() {
        // Find all helper containers
        const helperContainers = document.querySelectorAll('.td-helpers-inside-bricks');
        
        if (helperContainers.length === 0) {
            console.log('No helper controls found on page');
            return;
        }
        
        console.log('Found ' + helperContainers.length + ' helper containers');
        
        helperContainers.forEach(container => {
            const toggleBtn = container.querySelector('.td-helpers-toggle');
            const controlsContainer = container.querySelector('.td-helpers-controls');
            const toggleIcon = toggleBtn.querySelector('.dashicons');
            
            // Start expanded
            let isExpanded = true;
            
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
        });
        
        // Connect helper controls to the Polygonjs iframe
        connectHelperControls();
    }
    
    /**
     * Connect helper controls to the Polygonjs iframe
     */
    function connectHelperControls() {
        console.log('Connecting helper controls to Polygonjs iframe');
        
        // Find all iframe-container pairs
        const viewerContainers = document.querySelectorAll('.td-viewer-container');
        
        viewerContainers.forEach(viewerContainer => {
            const iframe = viewerContainer.querySelector('iframe');
            const helperContainer = viewerContainer.querySelector('.td-helpers-inside-bricks');
            
            if (!iframe) {
                console.log('No iframe found in viewer container');
                return;
            }
            
            if (!helperContainer) {
                console.log('No helper container found in viewer container');
                return;
            }
            
            console.log('Found helper container and iframe');
            
            // Get all helper controls
            const helperControls = helperContainer.querySelectorAll('.polygonjs-control');
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