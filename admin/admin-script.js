jQuery(document).ready(function($) {
    const $sortable = $("#llms-post-types-sortable");
    const $form = $("#llms-settings-form");

    // Initialize sortable
    $sortable.sortable({
        items: '.sortable-item',
        axis: 'y',
        cursor: 'move',
        handle: 'label',
        update: function(event, ui) {
            updateActiveStates();
        }
    });

    // Handle checkbox changes
    $sortable.on('change', 'input[type="checkbox"]', function() {
        $(this).closest('.sortable-item').toggleClass('active', $(this).is(':checked'));
    });

    // Update active states
    function updateActiveStates() {
        $sortable.find('.sortable-item').each(function() {
            const $item = $(this);
            const $checkbox = $item.find('input[type="checkbox"]');
            $item.toggleClass('active', $checkbox.is(':checked'));
        });
    }

    // Ensure proper order on form submission
    $form.on('submit', function() {
        // Move unchecked items to the end
        $sortable.find('.sortable-item:not(.active)').appendTo($sortable);
        return true;
    });
    
    // Add visual feedback when generation button is clicked
    $('form[action*="admin-post.php"]').on('submit', function() {
        const $form = $(this);
        const action = $form.find('input[name="action"]').val();
        
        if (action === 'generate_llms_file') {
            const $button = $form.find('button[type="submit"]');
            const originalText = $button.html();
            
            // Disable button and show loading state
            $button.prop('disabled', true);
            $button.html('Generating... Please wait');
            
            // Add loading class for styling
            $button.addClass('llms-button-loading');
        }
    });
});