# Unified Parameter Synchronization System

## Overview

The Unified Parameter Synchronization system solves the core issue of parameter inconsistency between frontend and backend by creating a single source of truth for 3D model parameter values.

## How It Works

### 1. Frontend Parameter Capture
- **Script**: `assets/js/unified-parameter-capture.js`
- **Purpose**: Captures exact parameter values as they are applied to the 3D scene
- **Data Captured**:
  - UI control values
  - Actual PolygonJS scene parameter values
  - Color selections with RGB data
  - Scene state information

### 2. Cart Integration
- **Modified**: `includes/class-frontend-cart.php`
- **Purpose**: Stores unified sync key with cart items
- **Process**:
  1. When product is added to cart, a unique session key is generated
  2. Parameter data is stored with this key in the unified sync system
  3. Session key is saved with the cart item

### 3. Order Storage
- **Modified**: `includes/class-frontend-cart.php` (order creation)
- **Purpose**: Preserves sync key in order metadata
- **Result**: Backend can retrieve exact frontend state using the sync key

### 4. Backend Viewer Integration
- **Modified**: `admin/admin-model-viewer.php`
- **Purpose**: Loads exact frontend state in backend 3D viewer
- **Process**:
  1. Retrieves sync key from order metadata
  2. Loads frontend state using the sync key
  3. Injects script to apply exact parameter values to backend viewer

## Key Classes

### TD_Unified_Parameter_Sync
- **File**: `includes/class-unified-parameter-sync.php`
- **Methods**:
  - `store_frontend_state()`: Store complete parameter state
  - `get_frontend_state()`: Retrieve stored state
  - `generate_backend_viewer_script()`: Create script for backend viewer

### Frontend Assets
- **File**: `includes/class-assets-manager.php`
- **Addition**: Enqueues unified capture script with proper dependencies
- **Variables**: Provides AJAX URL, product ID, nonce for capture script

## Data Flow

```
Frontend 3D Scene → Parameter Capture → Unified Sync Storage
                                              ↓
Cart → Order → Backend Viewer ← Unified Sync Retrieval
```

## Benefits

1. **Exact Synchronization**: Backend shows exactly what customer saw
2. **Single Source of Truth**: One system handles all parameter synchronization
3. **Robust Capture**: Captures both UI values and actual scene values
4. **Backward Compatibility**: Works alongside existing parameter systems
5. **Debug Friendly**: Extensive logging for troubleshooting

## Testing

To test the system:

1. **Frontend**: Add `?debug=1` to product URL to see capture logs
2. **Backend**: Check browser console in admin model viewer for sync status
3. **Verification**: Compare frontend parameters with backend display

## Configuration

No additional configuration needed. The system:
- Auto-detects PolygonJS enabled products
- Generates unique session keys automatically
- Cleans up old data (7 days) automatically
- Works with existing color and parameter managers

## Troubleshooting

### No Sync Data Available
- Check if unified capture script is loading
- Verify AJAX calls are successful
- Ensure cart addition triggers capture

### Parameters Not Matching
- Enable debug mode to see captured vs applied values
- Check browser console for script errors
- Verify sync key is stored in order metadata

### Script Errors
- Check file permissions on new JavaScript files
- Verify WordPress localization is working
- Ensure dependencies are loaded in correct order