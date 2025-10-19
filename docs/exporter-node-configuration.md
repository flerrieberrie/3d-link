# PolygonJS Exporter Node Configuration

This document explains how to configure the exporter node path for your PolygonJS scenes in the 3D Link plugin.

## Overview

The 3D Link plugin uses a PolygonJS exporter node to generate GLTF/GLB files when customers save or download 3D models. By default, the plugin attempts to automatically detect the exporter node in your scene based on common paths, but in some cases, you may need to specify the exact path to the exporter node.

## Configuration Options

You can configure the exporter node path at two levels:

1. **Global Default**: Set in 3D Link Settings page (applies to all products)
2. **Product-Specific**: Set individually for each product in the product edit page

## Global Default Configuration

1. Navigate to **3D Link → Settings** in the WordPress admin menu
2. Under **Measurement Unit Settings**, you'll find the **Default PolygonJS Exporter Node Path** field
3. Enter the path to the exporter node (e.g., `/geo1/exporterGLTF1`)
4. Click **Save Settings**

This setting will be used as a fallback for all products that don't have a product-specific exporter node path configured.

## Product-Specific Configuration

1. Edit a product in WooCommerce
2. Scroll to the **PolygonJS Integration** section
3. Find the **Exporter Node Path** field
4. Enter the path to the exporter node for this specific product (e.g., `/geo2/exporterGLTF1` or `/doos/exporterGLTF1`)
5. Update the product

The product-specific setting overrides the global default.

## How to Find Your Exporter Node Path

To find the correct exporter node path for your scene:

1. Open your scene in PolygonJS editor
2. Find the exporter node in your node network (usually named exporterGLTF1)
3. Note the full path to the node, which typically follows this pattern:
   - `/geo1/exporterGLTF1` (most common)
   - `/geo2/exporterGLTF1` (for scenes in geo2)
   - `/doos/exporterGLTF1` (for box/container scenes)

The path should always start with a forward slash (/) and include the container node name and the exporter node name, separated by a forward slash.

## Common Exporter Node Paths

Here are some common exporter node paths based on scene name:

| Scene Name       | Common Exporter Path      |
|------------------|---------------------------|
| telefoonhoes     | `/geo2/exporterGLTF1`     |
| sleutelhanger    | `/geo1/exporterGLTF1`     |
| doosje           | `/doos/exporterGLTF1`     |
| sleutelhoes      | `/doos/exporterGLTF1`     |
| sandblock        | `/geo1/exporterGLTF1`     |

## Fallback Mechanism

If no exporter node path is configured, the plugin will:

1. First check the product-specific setting
2. Then check the global default setting
3. Next, try to detect based on the scene name
4. Finally, fall back to `/geo1/exporterGLTF1` as a last resort

## Troubleshooting

If the download functionality isn't working correctly, check the following:

1. Verify that the exporter node path is correct
2. Check the browser console for any JavaScript errors
3. Try using one of the common paths from the table above
4. Ensure your PolygonJS scene has an exporter node

If you need to debug:

1. Add `?debug=1` to your product page URL
2. Open the browser console
3. Look for log messages with the prefix `🔗 [Bridge]` to see if the exporter node path is being correctly detected

## Need Help?

If you're still having trouble configuring the exporter node path, contact support or check the main 3D Link documentation for more information.