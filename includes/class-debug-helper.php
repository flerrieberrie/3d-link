<?php
/**
 * Add this class to a new file called 'class-debug-helper.php' in your includes directory
 */

defined('ABSPATH') || exit;

class TD_Debug_Helper {
    /**
     * Initialize the helper
     */
    public static function init() {
        add_action('wp_footer', [self::class, 'add_debug_script'], 99);
        add_filter('render_color_swatch_html', [self::class, 'debug_color_swatch'], 10, 3);
    }
    
    /**
     * Add debug script to footer
     */
    public static function add_debug_script() {
        // Only run on product pages
        if (!is_product()) return;
        
        ?>
        <script>
            console.log('TD Link Debug Helper loaded');
            
            // Debug information about color options
            function debugColorOptions() {
                if (typeof window.tdPolygonjs !== 'undefined' && window.tdPolygonjs.colorOptions) {
                    console.log('Available color options:', window.tdPolygonjs.colorOptions);
                } else {
                    console.log('No color options found in tdPolygonjs object');
                }
                
                // Check for out-of-stock colors in the DOM
                const outOfStockColors = document.querySelectorAll('.color-swatch.out-of-stock');
                console.log(`Found ${outOfStockColors.length} out-of-stock color swatches in DOM`);
                outOfStockColors.forEach(color => {
                    console.log('Out of stock color:', color.querySelector('.swatch-name')?.textContent);
                });
                
                // Add highlight border to visually identify out-of-stock colors
                outOfStockColors.forEach(swatch => {
                    swatch.style.border = '2px solid red';
                    const name = swatch.querySelector('.swatch-name')?.textContent || 'unknown';
                    console.log(`Highlighted out-of-stock color: ${name}`);
                });
            }
            
            // Run after a short delay to ensure the DOM is ready
            setTimeout(debugColorOptions, 1000);
        </script>
        <?php
    }
    
    /**
     * Debug filter for color swatch HTML
     * You'll need to modify your render_color_options method to use this filter
     */
    public static function debug_color_swatch($html, $color_name, $is_in_stock) {
        // Log the color status
        error_log("TD Link Color: {$color_name} - In Stock: " . ($is_in_stock ? 'Yes' : 'No'));
        
        // If out of stock, force add required attributes
        if (!$is_in_stock) {
            // Ensure out-of-stock class is added
            if (strpos($html, 'out-of-stock') === false) {
                $html = str_replace('class="color-swatch"', 'class="color-swatch out-of-stock"', $html);
            }
            
            // Ensure out-of-stock label exists
            if (strpos($html, 'out-of-stock-label') === false) {
                $html = str_replace('</label>', '<span class="out-of-stock-label">Out of Stock</span></label>', $html);
            }
        }
        
        return $html;
    }
}

// Initialize the helper
TD_Debug_Helper::init();