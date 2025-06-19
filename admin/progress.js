jQuery(document).ready(function($) {
    let progressInterval;
    let isGenerating = false;
    
    // Create progress bar HTML
    const progressHTML = `
        <div id="llms-progress-container" style="display: none; margin: 20px 0;">
            <div class="notice notice-info">
                <p><strong id="llms-progress-message">Processing...</strong></p>
                <div style="background: #f0f0f0; border-radius: 3px; height: 20px; margin: 10px 0;">
                    <div id="llms-progress-bar" style="background: #0073aa; height: 100%; border-radius: 3px; width: 0%; transition: width 0.3s ease;"></div>
                </div>
                <p id="llms-progress-details" style="color: #666; font-size: 12px;"></p>
            </div>
        </div>
    `;
    
    // Add progress bar after the cache management card
    $('.card:contains("Cache Management")').after(progressHTML);
    
    // Function to check progress
    function checkProgress() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'llms_get_progress',
                nonce: llmsProgress.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    
                    if (data.status === 'in_progress') {
                        $('#llms-progress-container').show();
                        $('#llms-progress-message').text(data.message || 'Processing...');
                        $('#llms-progress-bar').css('width', data.percentage + '%');
                        $('#llms-progress-details').text(
                            data.current + ' / ' + data.total + ' (' + data.percentage + '%)'
                        );
                    } else {
                        // Operation completed or idle
                        if (isGenerating) {
                            $('#llms-progress-message').text(data.message || 'Operation completed!');
                            $('#llms-progress-bar').css('width', '100%');
                            
                            // Hide after 3 seconds
                            setTimeout(function() {
                                $('#llms-progress-container').fadeOut();
                                isGenerating = false;
                            }, 3000);
                        }
                        
                        // Stop checking
                        if (progressInterval) {
                            clearInterval(progressInterval);
                        }
                    }
                }
            }
        });
    }
    
    // Monitor form submissions that trigger generation
    $('#llms-settings-form').on('submit', function() {
        isGenerating = true;
        $('#llms-progress-container').show();
        $('#llms-progress-message').text('Initializing...');
        $('#llms-progress-bar').css('width', '0%');
        
        // Start checking progress every 1 second
        progressInterval = setInterval(checkProgress, 1000);
    });
    
    // Monitor cache clear button
    $('form[action*="clear_caches"]').on('submit', function() {
        isGenerating = true;
        $('#llms-progress-container').show();
        $('#llms-progress-message').text('Clearing caches and regenerating...');
        $('#llms-progress-bar').css('width', '0%');
        
        // Start checking progress
        progressInterval = setInterval(checkProgress, 1000);
    });
});