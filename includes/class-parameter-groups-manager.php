<?php
/**
 * Parameter Groups Manager
 * 
 * Handles parameter groups that can be assigned to product variations
 */

defined('ABSPATH') || exit;

class TD_Parameter_Groups_Manager {
    
    /**
     * Initialize the class
     */
    public function __construct() {
        // V3: Groups are saved automatically with parameters - no additional hooks needed
    }
    
    /**
     * V3: Get parameters organized by groups (simplified)
     * Groups are now determined by the group_name field in each parameter
     */
    public function get_parameters_by_group($product_id) {
        $parameters_manager = new TD_Parameters_Manager();
        $parameters = $parameters_manager->get_parameters($product_id);
        
        $organized = [
            'ungrouped' => []
        ];
        
        // Organize parameters by their group_name
        foreach ($parameters as $index => $parameter) {
            $parameter['index'] = $index;
            $group_name = isset($parameter['group_name']) ? trim($parameter['group_name']) : '';
            
            if (empty($group_name)) {
                $organized['ungrouped'][] = $parameter;
            } else {
                if (!isset($organized[$group_name])) {
                    $organized[$group_name] = [
                        'name' => $group_name,
                        'parameters' => []
                    ];
                }
                $organized[$group_name]['parameters'][] = $parameter;
            }
        }
        
        return $organized;
    }
    
    /**
     * Get parameter groups for a product (simplified for admin display)
     */
    public function get_parameter_groups($product_id) {
        $organized = $this->get_parameters_by_group($product_id);
        $groups = [];
        
        foreach ($organized as $group_key => $group_data) {
            if ($group_key === 'ungrouped') continue;
            
            $groups[$group_key] = [
                'name' => $group_data['name'],
                'count' => count($group_data['parameters'])
            ];
        }
        
        return $groups;
    }
    
    
}