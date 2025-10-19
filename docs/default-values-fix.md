# Fix for Default Values Not Being Applied

## Problem Description

The plugin was experiencing an issue where default parameter values set in WordPress were not being properly applied to the PolygonJS 3D model when the product page loaded. Instead, the models would often display with the original PolygonJS parameter values rather than the WordPress-configured defaults.

## Root Cause

The issue was caused by a timing problem between:
1. When the PolygonJS scene was initialized
2. When the WordPress UI controls were ready
3. When the bridge between WordPress and PolygonJS was established

Sometimes the scene would load before all parameter values were properly communicated, resulting in the PolygonJS defaults being shown instead of WordPress defaults.

## Solution Implemented

We implemented a multi-layered approach to ensure default values are always applied:

### 1. Enhanced Frontend Customizer (`frontend-customizer.js`)

- Added a new function `forceApplyAllDefaultValues()` that directly applies values to the PolygonJS scene
- Implemented `applyValueDirectlyToScene()` and `applyRGBDirectlyToScene()` for direct scene manipulation
- Added a scene detection mechanism that watches for `window.polyScene` availability
- Modified initialization to:
  1. Watch for scene availability every 100ms
  2. Initialize bridge manually if needed
  3. Apply defaults directly without waiting for bridge

### 2. Enhanced PolygonJS Bridge (`polygonjs-bridge.js`)

- Added an `applyInitialValues()` method that runs immediately after bridge initialization
- Modified `initBridge()` to automatically call `applyInitialValues()` with a slight delay
- Added console logging for better debugging

### 3. Direct Scene Manipulation Approach

The new approach bypasses the bridge entirely if needed and applies values directly:

```javascript
function applyValueDirectlyToScene(scene, nodeId, value) {
    // Convert node ID to scene path
    const nodePath = convertNodeIdToScenePath(nodeId);
    const node = scene.node(nodePath);
    
    // Try common parameter names
    const possibleParams = ['value', 'input', 'index', 'switch'];
    for (const param of possibleParams) {
        if (node.p[param]) {
            node.p[param].set(value);
            return true;
        }
    }
}
```

## How It Works

1. When the page loads, the customizer starts watching for the PolygonJS scene
2. Once `window.polyScene` is detected, it immediately applies defaults
3. The system applies values in order:
   - Non-color parameters first (dimensions, text, etc.)
   - Color parameters second (as they may depend on other values)
   - Final scene recomputation to ensure all changes are visible

## Debugging Steps

To understand which values aren't being applied:

1. Open the browser console
2. Look for messages starting with `[TD Customizer]`
3. Check for errors like:
   - "No scene available for default value application"
   - "Error applying value directly"
   - "Error applying RGB directly"

4. To debug specific controls:
   ```javascript
   // In console, check what controls exist
   document.querySelectorAll('[data-node-id]').forEach(el => {
       console.log('Control:', el.dataset.nodeId, 'Value:', el.querySelector('input')?.value);
   });
   
   // Check if scene is available
   console.log('Scene available:', !!window.polyScene);
   
   // Try to manually set a value
   if (window.polyScene) {
       window.polyScene.node('/doos/ctrl_doos').p.breedte.set(150);
   }
   ```

## Common Issues and Solutions

1. **Scene not found**: Make sure `window.polyScene` is being set in the main.js file
2. **Node paths incorrect**: The node ID format might not match the scene structure
3. **Parameter names mismatch**: The parameter names in WordPress might not match PolygonJS
4. **Timing issues**: Try increasing the delays in the setTimeout calls

## Solution Success

The enhanced fix successfully resolves the default values issue. The system now:
1. Detects the PolygonJS scene in the iframe
2. Initializes the bridge
3. Applies all WordPress default values correctly
4. Handles all parameter types (dimensions, text, booleans, RGB colors)

## Test Results

The solution has been tested and confirmed working with the following successful operations:
- Scene detection: `[TD Customizer] Found scene in iframe`
- Bridge initialization: `[TD Customizer] Initializing bridge manually...`
- Value application: All parameters successfully applied
- Color handling: RGB values correctly set for all color parameters
- Scene updates: Proper recomputation after value changes

## Known Issues

1. Minor console warning: "Received unknown message type: requestScene"
   - This is a non-critical warning that doesn't affect functionality
   - Can be fixed by adding the message handler to main.js switch statement

## Future Improvements

If additional enhancements are needed:
1. Add visual feedback during default value application
2. Implement error recovery for failed parameter updates
3. Add configuration options for timing delays
4. Create unit tests for the value application system