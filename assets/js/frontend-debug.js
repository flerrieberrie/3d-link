/**
 * TD Link Debug Connector
 * 
 * Advanced debugging utility for PolygonJS integration
 * Only loaded when ?debug parameter is present in URL
 */
(function() {
    console.log('🐛 TD Link Debug Mode Activated');
    
    // Create debug panel
    const debugPanel = document.createElement('div');
    debugPanel.id = 'td-debug-panel';
    debugPanel.style.cssText = `
        position: fixed;
        bottom: 0;
        right: 0;
        width: 400px;
        height: 300px;
        background: rgba(0, 0, 0, 0.8);
        color: #00ff00;
        font-family: monospace;
        font-size: 12px;
        padding: 10px;
        overflow: auto;
        z-index: 9999;
        border-top-left-radius: 4px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    `;
    
    // Add minimize/maximize button
    const toggleButton = document.createElement('button');
    toggleButton.textContent = '−';
    toggleButton.style.cssText = `
        position: absolute;
        top: 5px;
        right: 5px;
        width: 20px;
        height: 20px;
        background: #333;
        color: #fff;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    `;
    toggleButton.addEventListener('click', function() {
        if (debugPanel.style.height === '20px') {
            debugPanel.style.height = '300px';
            toggleButton.textContent = '−';
        } else {
            debugPanel.style.height = '20px';
            toggleButton.textContent = '+';
        }
    });
    
    debugPanel.appendChild(toggleButton);
    
    // Log container
    const logContainer = document.createElement('div');
    logContainer.style.cssText = `
        width: 100%;
        height: calc(100% - 30px);
        overflow: auto;
    `;
    debugPanel.appendChild(logContainer);
    
    // Control panel
    const controlPanel = document.createElement('div');
    controlPanel.style.cssText = `
        display: flex;
        gap: 5px;
        margin-top: 10px;
    `;
    
    // Clear button
    const clearButton = document.createElement('button');
    clearButton.textContent = 'Clear Log';
    clearButton.style.cssText = `
        background: #333;
        color: #fff;
        border: none;
        border-radius: 3px;
        padding: 2px 5px;
        cursor: pointer;
    `;
    clearButton.addEventListener('click', function() {
        logContainer.innerHTML = '';
    });
    controlPanel.appendChild(clearButton);
    
    // Reload iframes button
    const reloadButton = document.createElement('button');
    reloadButton.textContent = 'Reload iFrames';
    reloadButton.style.cssText = `
        background: #333;
        color: #fff;
        border: none;
        border-radius: 3px;
        padding: 2px 5px;
        cursor: pointer;
    `;
    reloadButton.addEventListener('click', function() {
        const iframes = document.querySelectorAll('iframe');
        iframes.forEach(function(iframe) {
            try {
                const src = iframe.src;
                iframe.src = '';
                setTimeout(function() {
                    iframe.src = src;
                }, 100);
            } catch (error) {
                logMessage('Error reloading iframe: ' + error.message, 'error');
            }
        });
        logMessage('All iframes reloaded', 'system');
    });
    controlPanel.appendChild(reloadButton);
    
    // Test update button
    const testButton = document.createElement('button');
    testButton.textContent = 'Test Update';
    testButton.style.cssText = `
        background: #333;
        color: #fff;
        border: none;
        border-radius: 3px;
        padding: 2px 5px;
        cursor: pointer;
    `;
    testButton.addEventListener('click', function() {
        testParameterUpdate();
    });
    controlPanel.appendChild(testButton);
    
    debugPanel.appendChild(controlPanel);
    
    // Append to body
    document.body.appendChild(debugPanel);
    
    // Intercept and display console logs
    const originalConsoleLog = console.log;
    const originalConsoleError = console.error;
    const originalConsoleWarn = console.warn;
    
    console.log = function(...args) {
        originalConsoleLog.apply(console, args);
        
        // Only display TD Link-related logs
        const message = Array.from(args).join(' ');
        if (message.includes('[Bridge]') || 
            message.includes('[Customizer]') || 
            message.includes('[iFrameConnector]')) {
            logMessage(message, 'log');
        }
    };
    
    console.error = function(...args) {
        originalConsoleError.apply(console, args);
        logMessage(Array.from(args).join(' '), 'error');
    };
    
    console.warn = function(...args) {
        originalConsoleWarn.apply(console, args);
        logMessage(Array.from(args).join(' '), 'warning');
    };
    
    // Log a message to the debug panel
    function logMessage(message, type = 'log') {
        const logEntry = document.createElement('div');
        
        let color = '#00ff00'; // Default for logs
        if (type === 'error') color = '#ff6666';
        if (type === 'warning') color = '#ffff00';
        if (type === 'system') color = '#66ccff';
        
        const timestamp = new Date().toTimeString().split(' ')[0];
        
        logEntry.style.cssText = `
            color: ${color};
            margin-bottom: 3px;
            border-bottom: 1px solid #333;
            padding-bottom: 3px;
            white-space: pre-wrap;
            word-break: break-word;
        `;
        
        logEntry.textContent = `[${timestamp}] ${message}`;
        
        logContainer.appendChild(logEntry);
        logContainer.scrollTop = logContainer.scrollHeight;
    }
    
    // Intercept postMessage calls to iframes
    const originalPostMessage = window.postMessage;
    
    try {
        // Overwrite postMessage on all iframes
        setTimeout(function() {
            const iframes = document.querySelectorAll('iframe');
            iframes.forEach(function(iframe, index) {
                try {
                    const originalIframePostMessage = iframe.contentWindow.postMessage;
                    
                    iframe.contentWindow.postMessage = function(message, targetOrigin, transfer) {
                        logMessage(`iFrame ${index} postMessage: ${JSON.stringify(message)}`, 'system');
                        return originalIframePostMessage.call(this, message, targetOrigin, transfer);
                    };
                } catch (error) {
                    // Ignore cross-origin errors
                }
            });
        }, 2000);
    } catch (error) {
        logMessage('Error intercepting iframe postMessage: ' + error.message, 'error');
    }
    
    // Listen for all messages
    window.addEventListener('message', function(event) {
        logMessage(`Message received: ${JSON.stringify(event.data)}`, 'system');
    });
    
    // Test parameter update
    function testParameterUpdate() {
        // Find common controls
        const controls = [
            document.querySelector('input[type="range"].polygonjs-control'),
            document.querySelector('input[type="number"].polygonjs-control'),
            document.querySelector('input[type="text"].polygonjs-control'),
            document.querySelector('input[type="checkbox"].polygonjs-control'),
            document.querySelector('input[type="radio"].color-radio:checked')
        ].filter(Boolean);
        
        if (controls.length === 0) {
            logMessage('No controls found to test', 'warning');
            return;
        }
        
        // Choose a random control
        const control = controls[Math.floor(Math.random() * controls.length)];
        
        // Trigger an event on the control
        logMessage(`Testing update on control: ${control.id}`, 'system');
        
        if (control.type === 'range' || control.type === 'number') {
            // For number/range: slightly increase or decrease the value
            const min = parseFloat(control.min) || 0;
            const max = parseFloat(control.max) || 100;
            const step = parseFloat(control.step) || 1;
            const currentValue = parseFloat(control.value);
            
            // Increase by one step, or decrease if near max
            let newValue = currentValue + step;
            if (newValue > max) newValue = currentValue - step;
            if (newValue < min) newValue = min;
            
            control.value = newValue;
        } else if (control.type === 'text') {
            // For text: add a "Test" suffix
            control.value = control.value + " Test";
        } else if (control.type === 'checkbox') {
            // For checkbox: toggle the checked state
            control.checked = !control.checked;
        } else if (control.type === 'radio') {
            // For radio: select the next radio in the group
            const name = control.name;
            const radios = Array.from(document.querySelectorAll(`input[name="${name}"]`));
            const currentIndex = radios.indexOf(control);
            const nextIndex = (currentIndex + 1) % radios.length;
            radios[nextIndex].checked = true;
            control = radios[nextIndex];
        }
        
        // Trigger change event
        const event = new Event('change', { bubbles: true });
        control.dispatchEvent(event);
        
        logMessage(`Updated ${control.id} to ${control.value || control.checked}`, 'system');
    }
    
    // Log initial information
    logMessage('TD Link Debug Mode Active', 'system');
    logMessage(`URL: ${window.location.href}`, 'system');
    
    // Enumerate iframes
    setTimeout(function() {
        const iframes = document.querySelectorAll('iframe');
        logMessage(`Found ${iframes.length} iframes:`, 'system');
        
        iframes.forEach(function(iframe, index) {
            const src = iframe.src;
            logMessage(`iFrame ${index}: ${src}`, 'system');
        });
    }, 1000);
})();