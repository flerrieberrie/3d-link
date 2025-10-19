# TD Link v2.3.0

A WordPress plugin that integrates PolygonJS 3D scenes with WooCommerce for creating customizable 3D product displays with dynamic pricing.

## Key Features

- **Direct integration with PolygonJS 3D scenes**
- **HTML snippet-based parameter management** - For maximum modularity
- **Dynamic Parameter-Based Pricing** - Automatically calculate prices based on customer selections
- **Conditional Logic for Parameters** - Show/hide parameters based on other parameter values
- **Bricks Builder Integration** - Dedicated element for embedding 3D viewers in Bricks layouts
- **Enhanced Parameter Organization** - Parameters are grouped by type (dimensions, appearance, text) for better organization
- **Streamlined User Interface** - Clean, intuitive controls for product customization
- **Comprehensive Stock Management** - Track which colors are in/out of stock with visual indicators
- **Robust Debugging Tools** - Advanced debugging capabilities for troubleshooting
- **Improved Communication Bridge** - Enhanced messaging between WordPress and PolygonJS

## Dashboard & Admin Features
- Direct integration with PolygonJS 3D scenes
- HTML snippet-based parameter management for modularity
- **Centralized Dashboard** - Overview of 3D products, global colors, and quick access to all plugin features
- **Global Colors Manager** - Create and manage colors that can be used across multiple products
  - Set stock status (in-stock/out-of-stock) for each color
  - Visual color preview with RGB values
  - Automatic conversion between hex and RGB formats for PolygonJS compatibility
- **Customer Models Browser** - View 3D models from:
  - Completed orders with customizations
  - Active shopping carts with pending customizations
- **File Manager** - Browse, upload, and manage files for your PolygonJS scenes
  - Toggle between WordPress and development environments
  - Direct preview of 3D and image files

## Product Integration

- **PolygonJS Integration** - Enable 3D functionality for specific products
- **Custom Path & Scene Configuration** - Set specific PolygonJS dist paths and scene names
- **Parameter Management** - Add, edit, and organize parameters through an intuitive interface
  - Multiple control types: sliders, numbers, text, checkboxes, colors, dropdowns
  - Quick-add RGB color groups with a single click
  - Collapsible parameter interface for better organization

## Frontend Features

- **Customizable Product Interface** - Group parameters by categories (dimensions, appearance, text)
- **Enhanced Color Selection** - Color options displayed as attractive, labeled swatches
- **Out-of-Stock Indicators** - Clear visual indication when colors are out of stock
- **Dynamic Price Display** - Real-time price updates as customers make selections
- **Price Breakdown** - Transparent display of base price and all adjustments
- **Conditional Parameters** - Parameters appear/disappear based on user choices
- **Responsive Design** - Works on mobile, tablet, and desktop devices
- **Cart & Order Integration** - Customizations and calculated prices are saved to cart and order items

## Color Implementation for Backend Viewing
The plugin provides a solution for accurately displaying customer-selected colors in the admin backend viewer by saving color data from the frontend configuration.

### How Color Values are Stored at Checkout
1. **Frontend Color Selection**: When a customer selects a color in the product customizer on the frontend, the RGB values are captured in real-time.
2. **Color Data Storage**: The color data is stored using the `TD_Color_Sync` class with the following process:
   - Each selected color is stored as RGB values in the 0-1 range (which is what PolygonJS expects)
   - Colors are stored with a product-specific ID and a color key
   - The RGB values, color name, and timestamp are saved in the WordPress options table
   - This happens via AJAX requests triggered when a color is selected
3. **Checkout Association**: When a customer proceeds to checkout, the color selections are:
   - Included in the order metadata
   - Associated with the specific product configuration
   - Retrievable through the stored color keys
4. **Admin Backend Retrieval**: When viewing customer models in the admin:
   - The system retrieves the exact RGB values that were originally selected
   - These values are passed directly to the 3D scene
   - The parameters are applied in the same format as they were in the frontend

### Implementation Details
- The `class-color-sync.php` file handles the storage and retrieval of color data
- Color data is stored in the WordPress options table under the `td_color_sync_data` key
- Each color entry contains the RGB values, color name, and timestamp
- The admin-models-viewer.js script applies these exact RGB values to the backend 3D scene

## What's New in Version 2.3.0

### Dynamic Pricing System
- **Parameter-based pricing**: Set different pricing rules for each parameter
- **Multiple pricing types**: Fixed amounts, percentages, per-unit, multipliers, and option-based
- **Live price calculation**: Customers see price changes instantly
- **Transparent pricing**: Clear breakdown shows how the final price is calculated

### Conditional Logic
- **Smart parameter display**: Show/hide parameters based on other selections
- **Multiple condition types**: Equals, not equals, checked, unchecked
- **Improved user experience**: Guide customers through options step-by-step

### Enhanced Admin Interface
- **Pricing configuration**: Easy-to-use interface for setting up pricing rules
- **Conditional logic builder**: Visual interface for creating parameter dependencies
- **Better organization**: Pricing and logic settings integrated into parameter management

See the [Parameter Pricing Guide](parameter-pricing-guide.md) for detailed usage instructions.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/td-link` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Make sure WooCommerce is installed and activated
4. Configure the plugin through the "3D Link" admin menu

## Usage

### Setting Up Global Colors

1. Navigate to **3D Link → Colors** in the admin menu
2. Use the form on the right to add new colors:
   - Enter a name (e.g., "Cherry Red")
   - Select a hex color
   - The RGB values (0-1 for PolygonJS) will automatically be calculated
   - Click "Save Color"
3. Manage existing colors:
   - Toggle in-stock status with the switch
   - Edit colors with the pencil icon
   - Delete colors with the trash icon

### Creating a 3D Product

1. **Prepare your PolygonJS scene**:
   - Create as many scenes as you want in a PolygonJS project
   - Create parameters that you want customers to be able to adjust
   - Make sure to update the `main.js` file in your PolygonJS project (template provided in the plugin) with the javascript snippets of these adjustable parameters
     - **IMPORTANT**: See [main.js Setup Guide](../docs/main-js-setup.md) for critical configuration requirements
   - Build your scene in PolygonJS
   - Copy the exported/build project to your WordPress site, typically in the `3d/dist` folder

2. **Configure the product**:
   - Go to the product edit page
   - Enable PolygonJS Integration in the "PolygonJS Integration" section
   - Set the PolygonJS Dist Path (default: `3d/dist`)
   - Specify a Scene Name (optional, defaults to product slug)

3. **Set up parameters**:
   - In the "PolygonJS Parameters" section, click "Add Parameter" or "Add Color"
   - For regular parameters:
     - Paste the HTML snippet from PolygonJS
     - Set a display name
     - Choose control type (slider, number, text, checkbox, dropdown)
     - Configure control settings
   - For RGB colors:
     - Click "Add Color" for quick setup of a color parameter
     - Select which global colors will be available for this parameter
   
4. **Configure pricing (optional)**:
   - Enable pricing for any parameter by checking "Enable Pricing"
   - Choose pricing type:
     - Fixed amount: Adds a set price
     - Percentage: Adds a percentage of base price
     - Per unit: Multiplies by parameter value
     - Option-based: Different prices for different options
   - See [Parameter Pricing Guide](parameter-pricing-guide.md) for detailed instructions
   
5. **Set up conditional logic (optional)**:
   - Show/hide parameters based on other selections
   - Example: Show "Custom Text" field only when "Add Text" is checked
   - Configure under "Conditional Logic" in parameter settings
   
6. **Save the product**

### Browsing Customer Models

1. Navigate to **3D Link → Customer Models**
2. Select a tab:
   - **Orders with 3D Models**: View completed orders with 3D customizations
   - **Active Shopping Carts**: See current carts with 3D customizations
3. Click "View 3D Models" to explore the customized models
   - The 3D viewer will load with the customer's specific customizations
   - Parameter values are displayed below the viewer

### Using the File Manager

1. Navigate to **3D Link → File Manager**
2. Use the interface to:
   - Browse directories
   - Upload files
   - Create new directories
   - Rename or delete files
3. Switch between WordPress and development environments using the mode switcher

### Using the Bricks Element

If you're using Bricks Builder, you can add the PolygonJS Viewer element:

1. Edit a page with Bricks Builder
2. Search for "PolygonJS" in the elements panel
3. Add the "PolygonJS 3D Viewer" element
4. Configure:
   - Product source
   - Custom parameters (optional)
   - Aspect ratio and other display options
For optimal use, this element should stick with scroll of the user, so the viewport is always visible when editing parameters. you can do this by editing the CSS under:
Stule
CSS
Custom CSS:
.sticky-block {
    position: sticky;
    top: 20px; /* Adjust this to set how far from the top the block sticks */
}

## Debugging

If you encounter issues:

1. Add `?debug=1` to your product URL
2. Open the browser console to see detailed logs
3. Check for errors in parameter communication
4. Verify that your PolygonJS scene correctly implements the bridge

## Technical Notes

### Parameter Integration Process

When integrating parameters between PolygonJS and WooCommerce:

#### 1. HTML Snippets (for WordPress Admin)
```html
<label for='geo1-line1-pointsCount'>geo1-line1-pointsCount</label>
<input type='number' id='geo1-line1-pointsCount' name='geo1-line1-pointsCount' min=2 max=100 step=1 value=7></input>
```

- Add these in the WordPress admin "HTML Snippet" field
- The plugin extracts node IDs and properties automatically

#### 2. JavaScript Integration
The `polygonjs-bridge.js` script automatically connects WordPress UI with your PolygonJS scene:

- No manual JavaScript needed in most cases
- The bridge automatically maps controls to PolygonJS parameters
- For complex scenarios, use the `TDPolygonjs` API:
  ```javascript
  window.TDPolygonjs.updateNodeParameter(nodeId, paramName, value);
  ```

### RGB Color Groups

RGB color groups synchronize three PolygonJS parameters (R, G, B) into a single color control:

1. Create using the "Add Color" button in parameter management
2. Select which global colors will be available
3. The system creates three connected parameters (only the main one is visible)
4. Values are automatically synchronized across all three components

### Cleaning Up Previous Solutions
Previous implementations attempted to map colors using complex node path transformations and manual color references. The current approach is much simpler and more reliable as it:
1. Saves the exact RGB values when they're applied on the frontend
2. Retrieves and uses those same values in the backend
3. Eliminates the need for complex path mapping or color name matching

The test bridge scripts and other temporary fix implementations have been removed as they are no longer needed with this direct approach.

## Credits

Developed by Florian D'heer - For advanced 3D product visualization and customization.