/**
 * TD Link Measurement Units Handler
 * 
 * Manages the display of measurement units throughout the customizer interface.
 * Ensures units are properly displayed and respects hide_unit settings for specific parameters.
 */

(function() {
    'use strict';
    
    // Configuration
    const RETRY_INTERVALS = [0, 500, 1500]; // Milliseconds to retry unit display updates
    
    /**
     * Initialize measurement unit handling
     */
    function init() {
        // Run unit display handler at multiple intervals to catch dynamic content
        RETRY_INTERVALS.forEach(function(delay) {
            if (delay === 0) {
                document.addEventListener("DOMContentLoaded", handleMeasurementUnits);
            } else {
                document.addEventListener("DOMContentLoaded", function() {
                    setTimeout(handleMeasurementUnits, delay);
                });
            }
        });
    }
    
    /**
     * Main function to handle measurement unit display
     */
    function handleMeasurementUnits() {
        // Clean up any legacy forced unit displays
        cleanupLegacyUnits();
        
        // Get current measurement unit from global configuration
        const currentUnit = getCurrentUnit();
        
        // Get list of parameters that should have hidden units
        const hiddenUnitParameters = getHiddenUnitParameters();
        
        // Process all measurement unit elements
        const unitElements = document.querySelectorAll(".measurement-unit");
        unitElements.forEach(function(element) {
            processMeasurementUnit(element, currentUnit, hiddenUnitParameters);
        });
        
        // Final cleanup for any remaining hidden unit parameters
        enforceHiddenUnits(hiddenUnitParameters, currentUnit);
    }
    
    /**
     * Get the current measurement unit from global configuration
     */
    function getCurrentUnit() {
        if (window.tdPolygonjs && window.tdPolygonjs.measurementUnit) {
            return window.tdPolygonjs.measurementUnit;
        }
        return "cm"; // Default unit
    }
    
    /**
     * Get list of parameters that should have hidden units
     */
    function getHiddenUnitParameters() {
        if (window.tdPolygonjs && window.tdPolygonjs.parametersWithHiddenUnits) {
            return window.tdPolygonjs.parametersWithHiddenUnits;
        }
        return [];
    }
    
    /**
     * Clean up legacy forced unit display elements
     */
    function cleanupLegacyUnits() {
        const legacyElements = document.querySelectorAll('.forced-unit-display');
        legacyElements.forEach(function(element) {
            element.remove();
        });
    }
    
    /**
     * Process a single measurement unit element
     */
    function processMeasurementUnit(element, currentUnit, hiddenUnitParameters) {
        // Find the parent control container
        const controlContainer = element.closest('.slider-control, .number-control');
        if (!controlContainer) return;
        
        // Determine if this unit should be hidden
        const shouldHide = shouldHideUnit(element, controlContainer, hiddenUnitParameters);
        
        if (shouldHide) {
            hideUnit(element, controlContainer, currentUnit);
        } else {
            showUnit(element, currentUnit);
        }
    }
    
    /**
     * Determine if a unit should be hidden
     */
    function shouldHideUnit(element, controlContainer, hiddenUnitParameters) {
        // Check data attribute
        const hideUnitAttr = element.getAttribute('data-hide-unit');
        if (hideUnitAttr === 'true') return true;
        
        // Check global list
        const nodeId = controlContainer.getAttribute('data-node-id');
        return hiddenUnitParameters.includes(nodeId);
    }
    
    /**
     * Hide a measurement unit
     */
    function hideUnit(element, controlContainer, currentUnit) {
        // Hide the unit element while maintaining layout
        element.style.cssText = 'visibility:hidden !important; opacity:0 !important; width:0 !important; ' +
                               'padding:0 !important; margin:0 !important; border:none !important;';
        element.setAttribute('data-hide-unit', 'true');
        
        // Mark the control container
        if (controlContainer) {
            controlContainer.setAttribute('data-has-hidden-unit', 'true');
        }
        
        // Remove units from range indicators
        const rangeElements = controlContainer.querySelectorAll('.slider-min-value, .slider-max-value, .number-range-info');
        rangeElements.forEach(function(rangeElement) {
            rangeElement.textContent = rangeElement.textContent.replace(' ' + currentUnit, '');
        });
    }
    
    /**
     * Show a measurement unit
     */
    function showUnit(element, currentUnit) {
        // Only update if element is empty
        if (!element.textContent || element.textContent.trim() === "") {
            element.textContent = currentUnit;
            element.setAttribute("data-unit", currentUnit);
        }
    }
    
    /**
     * Enforce hidden units for all parameters in the hidden list
     */
    function enforceHiddenUnits(hiddenUnitParameters, currentUnit) {
        if (!hiddenUnitParameters || hiddenUnitParameters.length === 0) return;
        
        hiddenUnitParameters.forEach(function(nodeId) {
            // Find all controls for this node
            const controls = document.querySelectorAll(`[data-node-id="${nodeId}"]`);
            
            controls.forEach(function(control) {
                // Hide all unit-related elements
                const unitElements = control.querySelectorAll('.measurement-unit, .forced-unit-display');
                unitElements.forEach(function(unit) {
                    unit.style.cssText = 'display:none !important; visibility:hidden !important;';
                });
                
                // Clean unit text from range indicators
                const rangeElements = control.querySelectorAll('.slider-min-value, .slider-max-value, .number-range-info');
                rangeElements.forEach(function(element) {
                    // Remove common unit suffixes
                    const units = ['cm', 'mm', 'm', 'in', '"', 'px'];
                    units.forEach(function(unitText) {
                        element.textContent = element.textContent.replace(' ' + unitText, '');
                    });
                });
            });
        });
    }
    
    // Initialize the module
    init();
    
})();