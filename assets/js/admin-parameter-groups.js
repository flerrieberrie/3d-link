/**
 * Parameter Groups Management
 * 
 * Architecture:
 * 1. Groups are stored as part of parameter data (no separate storage)
 * 2. No AJAX calls - everything saves with the product
 * 3. Groups are defined by parameters having the same group_name
 * 4. Simple, reliable approach
 */
jQuery(document).ready(function($) {
    console.log('=== Parameter Groups Loading ===');
    
    const ParameterGroups = {
        init: function() {
            console.log('Initializing Parameter Groups...');
            this.migrateLegacyGroups();
            this.bindEvents();
            this.createGroupsUI();
            this.organizeParameters();
        },
        
        // Migrate from old group_id system to new group_name system
        migrateLegacyGroups: function() {
            const $parameters = $('.polygonjs-parameter');
            let migratedCount = 0;
            
            $parameters.each(function() {
                const $param = $(this);
                const $groupIdField = $param.find('input[name*="[group_id]"]');
                const $groupNameField = $param.find('input[name*="[group_name]"]');
                
                if ($groupIdField.length && $groupNameField.length) {
                    const groupId = $groupIdField.val();
                    const groupName = $groupNameField.val();
                    
                    // If we have a group_id but no group_name, convert it
                    if (groupId && !groupName) {
                        // Create a simple readable name
                        const groupNumber = groupId.replace('group_', '').substr(0, 6);
                        const readableName = 'Group ' + groupNumber;
                        $groupNameField.val(readableName);
                        $groupIdField.val(''); // Clear the old ID
                        migratedCount++;
                        console.log(`Migrated group_id "${groupId}" to group_name "${readableName}"`);
                    }
                }
            });
            
            if (migratedCount > 0) {
                console.log(`Migrated ${migratedCount} parameters from legacy group system`);
            }
        },
        
        // Create the groups UI
        createGroupsUI: function() {
            const $container = $('.polygonjs-parameters');
            
            // Remove old groups UI
            $('#parameter-groups-wrapper').remove();
            
            // Create new groups UI
            const groupsHtml = `
                <div id="parameter-groups-wrapper" class="parameter-groups-container">
                    <div class="parameter-groups-header">
                        <h4>Parameter Groups</h4>
                        <div class="parameter-groups-actions">
                            <button type="button" class="button button-secondary add-group-btn">
                                <span class="dashicons dashicons-plus"></span> Add Group
                            </button>
                        </div>
                    </div>
                    <div class="parameter-groups-list"></div>
                    <div class="ungrouped-parameters">
                        <h4>Ungrouped Parameters</h4>
                        <div class="ungrouped-parameters-list"></div>
                    </div>
                </div>
            `;
            
            $container.before(groupsHtml);
            this.organizeParameters();
        },
        
        // Organize parameters into groups based on their group_name field
        organizeParameters: function() {
            console.log('=== Organizing Parameters ===');
            
            const $parameters = $('.polygonjs-parameter');
            const groups = {};
            
            // First pass: collect all parameters and their groups
            $parameters.each(function() {
                const $param = $(this);
                const $groupField = $param.find('input[name*="[group_name]"]');
                const groupName = $groupField.length ? $groupField.val().trim() : '';
                const paramName = $param.find('.display-name-field').val() || 'Unnamed';
                
                console.log(`Parameter "${paramName}": group_name = "${groupName}"`);
                
                if (groupName) {
                    if (!groups[groupName]) {
                        groups[groupName] = [];
                    }
                    groups[groupName].push($param);
                } else {
                    // Will be handled as ungrouped
                }
            });
            
            // Render group containers
            this.renderGroups(groups);
            
            // Organize parameters into their containers
            $parameters.each(function() {
                const $param = $(this);
                const $groupField = $param.find('input[name*="[group_name]"]');
                const groupName = $groupField.length ? $groupField.val().trim() : '';
                
                if (groupName && groups[groupName]) {
                    // Move to group
                    $(`.parameter-group[data-group-name="${groupName}"] .parameter-group-content`).append($param);
                } else {
                    // Move to ungrouped
                    $('.ungrouped-parameters-list').append($param);
                }
            });
            
            this.initializeSortable();
            console.log('=== Organization Complete ===');
        },
        
        // Render group containers
        renderGroups: function(groups) {
            const $list = $('.parameter-groups-list');
            $list.empty();
            
            Object.keys(groups).forEach(groupName => {
                const groupHtml = `
                    <div class="parameter-group" data-group-name="${groupName}">
                        <div class="parameter-group-header">
                            <span class="group-handle" title="Drag to reorder">☰</span>
                            <input type="text" class="group-name" value="${groupName}" placeholder="Enter group name">
                            <div class="group-actions">
                                <button type="button" class="button-link remove-group" title="Remove group">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                        <div class="parameter-group-content"></div>
                    </div>
                `;
                $list.append(groupHtml);
            });
        },
        
        // Initialize drag & drop
        initializeSortable: function() {
            $('.parameter-group-content, .ungrouped-parameters-list').sortable({
                items: '.polygonjs-parameter',
                handle: '.parameter-header',
                connectWith: '.parameter-group-content, .ungrouped-parameters-list',
                placeholder: 'parameter-placeholder',
                opacity: 0.7,
                
                receive: function(event, ui) {
                    const $container = $(this);
                    const $param = ui.item;
                    let groupName = '';
                    
                    if ($container.hasClass('parameter-group-content')) {
                        groupName = $container.closest('.parameter-group').data('group-name');
                    }
                    
                    // Update the parameter's group_name field
                    const $groupField = $param.find('input[name*="[group_name]"]');
                    if ($groupField.length > 0) {
                        $groupField.val(groupName);
                        console.log(`Moved parameter to group: "${groupName}"`);
                    }
                }
            });
        },
        
        // Bind events
        bindEvents: function() {
            // Add new group
            $(document).on('click', '.add-group-btn', () => {
                const groupName = prompt('Enter group name:');
                if (groupName && groupName.trim()) {
                    this.createNewGroup(groupName.trim());
                }
            });
            
            // Remove group
            $(document).on('click', '.remove-group', (e) => {
                if (confirm('Are you sure you want to delete this group? Parameters will become ungrouped.')) {
                    const $group = $(e.target).closest('.parameter-group');
                    const groupName = $group.data('group-name');
                    
                    // Move parameters to ungrouped
                    $group.find('.polygonjs-parameter').each(function() {
                        const $param = $(this);
                        $param.find('input[name*="[group_name]"]').val('');
                        $('.ungrouped-parameters-list').append($param);
                    });
                    
                    // Remove group container
                    $group.remove();
                }
            });
            
            // Update group name
            $(document).on('input', '.group-name', (e) => {
                const $input = $(e.target);
                const $group = $input.closest('.parameter-group');
                const oldGroupName = $group.data('group-name');
                const newGroupName = $input.val().trim();
                
                if (newGroupName && newGroupName !== oldGroupName) {
                    // Update data attribute
                    $group.attr('data-group-name', newGroupName);
                    
                    // Update all parameters in this group
                    $group.find('.polygonjs-parameter').each(function() {
                        $(this).find('input[name*="[group_name]"]').val(newGroupName);
                    });
                    
                    console.log(`Renamed group from "${oldGroupName}" to "${newGroupName}"`);
                }
            });
            
            // Re-organize when new parameters are added
            $(document).on('parameter-added', () => {
                console.log('Parameter added, re-organizing...');
                setTimeout(() => this.organizeParameters(), 100);
            });
        },
        
        // Create a new empty group
        createNewGroup: function(groupName) {
            const groupHtml = `
                <div class="parameter-group" data-group-name="${groupName}">
                    <div class="parameter-group-header">
                        <span class="group-handle" title="Drag to reorder">☰</span>
                        <input type="text" class="group-name" value="${groupName}" placeholder="Enter group name">
                        <div class="group-actions">
                            <button type="button" class="button-link remove-group" title="Remove group">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <div class="parameter-group-content"></div>
                </div>
            `;
            
            $('.parameter-groups-list').append(groupHtml);
            this.initializeSortable();
            
            console.log(`Created new group: "${groupName}"`);
        }
    };
    
    // Initialize on page load
    ParameterGroups.init();
    
    // Add CSS styles
    $('head').append(`
        <style>
            .parameter-groups-container {
                margin: 20px 0;
                padding: 15px;
                background: #fff;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            
            .parameter-groups-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
            }
            
            .parameter-groups-header h4 {
                margin: 0;
            }
            
            .parameter-groups-actions {
                display: flex;
                gap: 10px;
            }
            
            .parameter-group {
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fafafa;
            }
            
            .parameter-group-header {
                display: flex;
                align-items: center;
                padding: 10px;
                background: #f5f5f5;
                border-bottom: 1px solid #ddd;
            }
            
            .group-handle {
                cursor: move;
                margin-right: 10px;
                color: #666;
                font-size: 18px;
            }
            
            .group-name {
                flex: 1;
                margin-right: 10px;
                padding: 5px 10px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 14px;
            }
            
            .group-actions {
                display: flex;
                gap: 8px;
                align-items: center;
            }
            
            .remove-group {
                padding: 0 !important;
                background: none !important;
                border: none !important;
                color: #a00;
            }
            
            .remove-group:hover {
                color: #dc3232 !important;
            }
            
            .parameter-group-content {
                min-height: 50px;
                padding: 10px;
                background: #fff;
            }
            
            .parameter-group-content:empty::after {
                content: "Drop parameters here";
                display: block;
                text-align: center;
                color: #999;
                padding: 20px;
                border: 2px dashed #ddd;
                border-radius: 4px;
            }
            
            .ungrouped-parameters {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px solid #ddd;
            }
            
            .ungrouped-parameters h4 {
                margin-top: 0;
                color: #666;
            }
            
            .ungrouped-parameters-list {
                min-height: 50px;
            }
            
            .parameter-placeholder {
                border: 2px dashed #0073aa;
                background: #f0f8ff;
                margin: 10px 0;
                visibility: visible !important;
            }
        </style>
    `);
});