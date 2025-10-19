# PolygonJS Integration - main.js Setup Guide

This guide explains how to properly set up the `main.js` file for integrating PolygonJS scenes with the TD Link/3D Link WordPress plugin.

## Overview

The `main.js` file is a critical component that bridges your PolygonJS 3D scenes with WordPress. It must be placed in your PolygonJS project's `src/` directory BEFORE building or exporting your project.

## Required File Location

```
C:\alles3d\src\main.js
```

## Important Configuration Requirements

### JavaScript Snippets

When adding JavaScript control snippets from PolygonJS to the main.js file, you MUST include a question mark (`?`) in the element selectors to make them work properly on the website. This is because the elements may not exist when the page first loads.

#### Example:

Instead of:
```javascript
// This won't work reliably
document.getElementById('geo1-line1-pointsCount').addEventListener('input', function(event){
    scene.node('/geo1/line1').p.pointsCount.set(parseInt(event.target.value));
});
```

Use:
```javascript
// This works correctly with optional chaining
document.getElementById('geo1-line1-pointsCount')?.addEventListener('input', function(event){
    scene.node('/geo1/line1').p.pointsCount.set(parseInt(event.target.value));
});
```

Note the `?` after the `getElementById()` call.

## Complete Setup Process

1. **Create the main.js file** in your PolygonJS project's `src/` directory
2. **Copy the template** from the plugin files (if available)
3. **Add your scene controls** with the proper question mark syntax
4. **Build your PolygonJS project**
5. **Deploy** the built files to your WordPress installation

## Control Setup Pattern

For each scene, follow this pattern for setting up controls:

```javascript
const setupMySceneControls = (scene) => {
    console.log("[Controls] Setting up controls for MyScene...");
    
    // Use optional chaining for all event listeners
    document.getElementById('control-id')?.addEventListener('input', function(event){
        scene.node('/path/to/node').p.parameter.set(parseFloat(event.target.value));
    });
    
    console.log("[Controls] MyScene controls setup complete");
};
```

## Why the Question Mark is Essential

The question mark (`?`) is JavaScript's optional chaining operator. It prevents errors when trying to access properties or methods on null or undefined values. This is crucial because:

1. DOM elements might not be loaded when the script runs
2. Custom HTML parameters added via WordPress might load asynchronously
3. Different scenes might have different available controls

Without the question mark, missing elements will cause JavaScript errors and break the entire control system.

## Common Mistakes to Avoid

❌ **Don't forget the question mark:**
```javascript
document.getElementById('control-id').addEventListener(...) // This can crash!
```

✅ **Always use optional chaining:**
```javascript
document.getElementById('control-id')?.addEventListener(...) // This is safe!
```

## Integration with WordPress

The main.js file works in conjunction with:
- HTML snippets added in WordPress admin
- The polygonjs-bridge.js script
- The TD Polygonjs API

Make sure all these components are properly configured for seamless integration.

## Debugging Tips

If controls aren't working:
1. Check browser console for JavaScript errors
2. Verify the question mark is present in all event listeners
3. Ensure element IDs match between HTML snippets and JavaScript
4. Add `?debug=1` to your URL for enhanced logging

## Support

For additional help with main.js setup or PolygonJS integration, refer to the main TD Link documentation or contact support.