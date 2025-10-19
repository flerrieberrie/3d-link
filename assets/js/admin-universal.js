// Admin JavaScript for Universal Parameter System
jQuery(document).ready(function($) {
    
    // Test parser button
    $('#test-parser-btn').on('click', function() {
        const htmlSnippet = $('#test-html-snippet').val().trim();
        
        if (!htmlSnippet) {
            alert('Please enter an HTML snippet to test.');
            return;
        }
        
        // Show loading state
        $(this).prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: tdUniversalAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'test_universal_parser',
                html_snippet: htmlSnippet,
                nonce: tdUniversalAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayParserResults(response.data);
                } else {
                    displayParserError(response.data);
                }
            },
            error: function() {
                displayParserError('AJAX request failed');
            },
            complete: function() {
                $('#test-parser-btn').prop('disabled', false).text('Test Parser');
            }
        });
    });
    
    // Add examples button
    $('#add-examples-btn').on('click', function() {
        const examples = [
            // Sleutelhoes example
            `<label for='sleutelhoes-CTRL-height'>sleutelhoes-CTRL-height</label>
<input type='number' id='sleutelhoes-CTRL-height' name='sleutelhoes-CTRL-height' min=1 max=2.5 step=0.01 value=1.5></input>`,
            
            // Doos example
            `<label for='doos-ctrl_doos-breedte'>doos-ctrl_doos-breedte</label>
<input type='number' id='doos-ctrl_doos-breedte' name='doos-ctrl_doos-breedte' min=2 max=25.6 step=0.01 value=2></input>`,
            
            // Color example
            `<label for='sleutelhoes-MAT-meshStandard1-colorr'>sleutelhoes-MAT-meshStandard1-colorr</label>
<input type='number' id='sleutelhoes-MAT-meshStandard1-colorr' name='sleutelhoes-MAT-meshStandard1-colorr' min=0 max=1 step=0.01 value=0.75></input>`
        ];
        
        $('#test-html-snippet').val(examples.join('\n\n'));
    });
    
    // Function to display parser results
    function displayParserResults(data) {
        const $results = $('#parser-results');
        const $output = $('#parser-output');
        
        let html = '<div class="parser-result">';
        html += '<h4>✅ Successfully Parsed</h4>';
        html += '<p><strong>Extracted Information:</strong></p>';
        html += '<ul>';
        html += '<li><strong>Node ID:</strong> ' + escapeHtml(data.parsed.node_id) + '</li>';
        html += '<li><strong>Node Path:</strong> ' + escapeHtml(data.parsed.node_path) + '</li>';
        html += '<li><strong>Parameter Name:</strong> ' + escapeHtml(data.parsed.param_name) + '</li>';
        html += '<li><strong>Control Type:</strong> ' + escapeHtml(data.parsed.control_type) + '</li>';
        html += '<li><strong>Default Value:</strong> ' + escapeHtml(data.parsed.value) + '</li>';
        html += '<li><strong>Suggested Display Name:</strong> ' + escapeHtml(data.parsed.suggested_display_name) + '</li>';
        html += '</ul>';
        
        if (data.parsed.min || data.parsed.max || data.parsed.step) {
            html += '<p><strong>Numeric Constraints:</strong></p>';
            html += '<ul>';
            if (data.parsed.min) html += '<li><strong>Min:</strong> ' + escapeHtml(data.parsed.min) + '</li>';
            if (data.parsed.max) html += '<li><strong>Max:</strong> ' + escapeHtml(data.parsed.max) + '</li>';
            if (data.parsed.step) html += '<li><strong>Step:</strong> ' + escapeHtml(data.parsed.step) + '</li>';
            html += '</ul>';
        }
        
        html += '<p><strong>Full Parser Output (JSON):</strong></p>';
        html += '<pre>' + escapeHtml(data.formatted) + '</pre>';
        html += '</div>';
        
        $output.html(html);
        $results.show();
    }
    
    // Function to display parser errors
    function displayParserError(error) {
        const $results = $('#parser-results');
        const $output = $('#parser-output');
        
        let html = '<div class="parser-result" style="border-color: #dc3232; background: #ffeaea;">';
        html += '<h4>❌ Parser Error</h4>';
        html += '<p>' + escapeHtml(error) + '</p>';
        html += '<p><strong>Common Issues:</strong></p>';
        html += '<ul>';
        html += '<li>Missing or malformed <code>id</code> attribute in input element</li>';
        html += '<li>Input element not properly closed</li>';
        html += '<li>Label element missing or not properly linked</li>';
        html += '<li>HTML structure is invalid</li>';
        html += '</ul>';
        html += '<p><strong>Expected Format:</strong></p>';
        html += '<pre>&lt;label for=\'node-path-param\'&gt;Label Text&lt;/label&gt;\n&lt;input type=\'number\' id=\'node-path-param\' min=\'0\' max=\'10\' step=\'0.1\' value=\'5\'&gt;&lt;/input&gt;</pre>';
        html += '</div>';
        
        $output.html(html);
        $results.show();
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    // Add auto-testing when text changes (with debounce)
    let testTimeout;
    $('#test-html-snippet').on('input', function() {
        clearTimeout(testTimeout);
        testTimeout = setTimeout(function() {
            const snippet = $('#test-html-snippet').val().trim();
            if (snippet) {
                $('#test-parser-btn').click();
            }
        }, 1000); // Test automatically 1 second after user stops typing
    });
    
    // Add syntax highlighting class to code blocks
    $('pre').addClass('code-block');
});