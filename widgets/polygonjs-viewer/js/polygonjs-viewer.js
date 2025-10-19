/**
 * TD Link Polygonjs Viewer - Bricks Element JavaScript
 * Enhanced with responsive dimensions and CSS variables support
 */
(function($) {
    $(document).ready(function() {
        initPolygonViewer();
        setupResponsiveHandling();
    });

    /**
     * Initialize the Polygonjs viewer element
     */
    function initPolygonViewer() {
        const containers = document.querySelectorAll('.td-viewer-container');
        
        containers.forEach(container => {
            const productId = container.dataset.productId;
            const debug = container.dataset.debug === "true";
            const sceneName = container.dataset.sceneName || '';
            const sizeMode = container.dataset.sizeMode || 'responsive';
            
            // Get node mappings from data attribute
            const nodeMappingsAttr = container.dataset.nodeMappings;
            let nodeMappings = {};
            if (nodeMappingsAttr) {
                try {
                    nodeMappings = JSON.parse(nodeMappingsAttr);
                    if (debug) {
                        console.log('Loaded node mappings:', nodeMappings);
                    }
                } catch (e) {
                    console.error('Error parsing node mappings:', e);
                }
            }

            const iframe = container.querySelector('iframe');

            if (debug) {
                console.log('TD Link Polygonjs Viewer initialized');
                console.log('Product ID:', productId);
                console.log('Scene Name:', sceneName);
                console.log('Size Mode:', sizeMode);
            }
            
            if (!iframe) return;
            
            // Listen for iframe load
            iframe.addEventListener('load', function() {
                if (debug) {
                    console.log('Iframe loaded');
                }
                
                // Add loading class
                container.classList.add('loading');
                
                // Get all the UI controls
                const heightSlider = document.getElementById('height-slider');
                const widthSlider = document.getElementById('width-slider');
                const depthSlider = document.getElementById('depth-slider');
                const shelvesSlider = document.getElementById('shelves-slider');
                const textInput = document.getElementById('custom-text');
                const colorOptions = document.querySelectorAll('.color-option');
                
                if (debug) {
                    console.log('UI Elements found:', {
                        heightSlider: !!heightSlider,
                        widthSlider: !!widthSlider,
                        depthSlider: !!depthSlider,
                        shelvesSlider: !!shelvesSlider,
                        textInput: !!textInput,
                        colorOptions: colorOptions.length
                    });
                }
                
                // Function to send updates to iframe
                function updateModel() {
                    const data = {
                        type: 'updateModel'
                    };
                    
                    // Add values from all sliders
                    if (heightSlider) {
                        data.height = parseFloat(heightSlider.value);

                        // Add mapping info if available
                        if (nodeMappings['height']) {
                            data.height_mapping = nodeMappings['height'];
                        }     
                    }
                    
                    if (widthSlider) {
                        data.width = parseFloat(widthSlider.value);

                        // Add mapping info if available
                        if (nodeMappings['width']) {
                            data.width_mapping = nodeMappings['width'];  
                        }
                    }
                    
                    if (depthSlider) {
                        data.depth = parseFloat(depthSlider.value);

                        // Add mapping info if available  
                        if (nodeMappings['depth']) {
                            data.depth_mapping = nodeMappings['depth'];  
                        }
                    }
                    
                    if (shelvesSlider) {
                        data.shelvesCount = parseInt(shelvesSlider.value);

                        // Add mapping info if available
                        if (nodeMappings['shelves']) {
                            data.shelves_mapping = nodeMappings['shelves'];  
                        }
                    }
                    
                    if (textInput) {
                        data.customText = textInput.value;

                        // Add mapping info if available
                        if (nodeMappings['custom_text']) {
                            data.text_mapping = nodeMappings['custom_text'];
                        }
                    }
                    
                    // Get active color
                    const activeColor = document.querySelector('.color-option.active');
                    if (activeColor) {
                        data.color = activeColor.dataset.color;

                        // Add mapping info if available
                        if (nodeMappings['case_color']) {
                            data.color_mapping = nodeMappings['case_color'];  
                        }
                    }

                    // Add all mappings in a single property for convenience
                    if (Object.keys(nodeMappings).length > 0) {
                        data.node_mappings = nodeMappings;
                    }                    
                    
                    // Add viewer dimensions information when in CSS variables mode
                    if (sizeMode === 'custom') {
                        // Get computed dimensions from the container
                        const styles = window.getComputedStyle(container);
                        data.containerWidth = container.offsetWidth;
                        data.containerHeight = container.offsetHeight;
                    }
                    
                    // Send message to iframe
                    if (debug) {
                        console.log('Sending update to iframe:', data);
                    }
                    iframe.contentWindow.postMessage(data, '*');
                }
                
                // Function to handle slider events
                function handleSliderEvent(event) {
                    const sliderId = event.target.id;
                    const value = event.target.value;
                    if (debug) {
                        console.log(`Slider ${sliderId} changed to ${value}`);
                    }
                    updateModel();
                }
                
                // Connect event listeners to sliders
                if (heightSlider) heightSlider.addEventListener('input', handleSliderEvent);
                if (widthSlider) widthSlider.addEventListener('input', handleSliderEvent);
                if (depthSlider) depthSlider.addEventListener('input', handleSliderEvent);
                if (shelvesSlider) shelvesSlider.addEventListener('input', handleSliderEvent);
                if (textInput) textInput.addEventListener('input', handleSliderEvent);
                
                // Connect color options
                colorOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        if (debug) {
                            console.log('Color option clicked:', this.dataset.color);
                        }
                        updateModel();
                    });
                });
                
                // Schedule initial update after a short delay
                setTimeout(updateModel, 1000);
            });
            
            // Listen for messages from the iframe
            window.addEventListener('message', function(event) {
                // Check that the message is from our iframe
                if (event.source !== iframe.contentWindow) return;
                
                if (event.data && event.data.type === 'modelReady') {
                    if (debug) {
                        console.log('3D Model is ready, sending initial values');
                    }
                    
                    // Remove loading class
                    container.classList.remove('loading');
                    
                    // Trigger an update when the model signals it's ready
                    if (iframe && iframe.contentWindow) {
                        // Find all input elements and trigger their events
                        setTimeout(() => {
                            const heightSlider = document.getElementById('height-slider');
                            const widthSlider = document.getElementById('width-slider');
                            const depthSlider = document.getElementById('depth-slider');
                            const shelvesSlider = document.getElementById('shelves-slider');
                            const textInput = document.getElementById('custom-text');
                            
                            if (heightSlider) heightSlider.dispatchEvent(new Event('input'));
                            if (widthSlider) widthSlider.dispatchEvent(new Event('input'));
                            if (depthSlider) depthSlider.dispatchEvent(new Event('input'));
                            if (shelvesSlider) shelvesSlider.dispatchEvent(new Event('input'));
                            if (textInput) textInput.dispatchEvent(new Event('input'));
                            
                            // Also trigger a click on the active color option
                            const activeColor = document.querySelector('.color-option.active');
                            if (activeColor) activeColor.click();
                        }, 500);
                    }
                }
                
                // Handle scene ready message
                if (event.data && event.data.type === 'sceneReady') {
                    if (debug) {
                        console.log('Scene ready message received:', event.data);
                    }
                    
                    // Store scene data for the viewer
                    container.sceneData = event.data;
                    
                    // Trigger custom event
                    const sceneReadyEvent = new CustomEvent('td_viewer_scene_ready', {
                        detail: event.data,
                        bubbles: true
                    });
                    container.dispatchEvent(sceneReadyEvent);
                }
                
                // Also handle any other messages from the iframe
                if (event.data && event.data.type === 'bridgeReady') {
                    if (debug) {
                        console.log('Polygonjs bridge is ready in the iframe');
                    }
                    
                    // Request current scene status if needed
                    iframe.contentWindow.postMessage({
                        type: 'getSceneStatus',
                        productId: productId
                    }, '*');
                }
            });
        });
    }
    
    /**
     * Setup responsive handling for window resizing
     */
    function setupResponsiveHandling() {
        // Debounce function to limit how often a function is called
        function debounce(func, wait) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        }

        // Handle window resize for all viewer containers
        const handleResize = debounce(function() {
            const containers = document.querySelectorAll('.td-viewer-container');
            
            containers.forEach(container => {
                const debug = container.dataset.debug === "true";
                const sizeMode = container.dataset.sizeMode || 'responsive';
                const iframe = container.querySelector('iframe');
                
                if (!iframe) return;
                
                // Only update the iframe if it's already loaded
                if (iframe.contentWindow && iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
                    // For custom size mode with CSS variables, we need to inform the iframe
                    // about container dimensions changes
                    if (sizeMode === 'custom') {
                        if (debug) {
                            console.log('Resize: Container dimensions updated', {
                                width: container.offsetWidth,
                                height: container.offsetHeight
                            });
                        }
                        
                        // Send a resize message to the iframe
                        iframe.contentWindow.postMessage({
                            type: 'containerResize',
                            width: container.offsetWidth,
                            height: container.offsetHeight
                        }, '*');
                    }
                }
            });
        }, 250); // 250ms debounce
        
        // Listen for window resize events
        window.addEventListener('resize', handleResize);
        
        // Also listen for Bricks builder viewport changes (if in builder)
        if (typeof window.bricksIsFrontend !== 'undefined') {
            document.addEventListener('bricks/viewport/changed', handleResize);
        }
    }
})(jQuery);