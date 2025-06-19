jQuery(document).ready(function($) {
    // Form validation
    $('#llms-settings-form').on('submit', function(e) {
        let isValid = true;
        let errors = [];
        
        // Check if at least one post type is selected
        const postTypesChecked = $('input[name="llms_generator_settings[post_types][]"]:checked').length;
        if (postTypesChecked === 0) {
            isValid = false;
            errors.push(llmsValidation.messages.selectPostType);
        }
        
        // Validate max posts
        const maxPosts = $('input[name="llms_generator_settings[max_posts]"]').val();
        if (maxPosts && (parseInt(maxPosts) < 1 || parseInt(maxPosts) > 100000)) {
            isValid = false;
            errors.push(llmsValidation.messages.invalidMaxPosts);
        }
        
        // Validate max words
        const maxWords = $('input[name="llms_generator_settings[max_words]"]').val();
        if (maxWords && (parseInt(maxWords) < 1 || parseInt(maxWords) > 100000)) {
            isValid = false;
            errors.push(llmsValidation.messages.invalidMaxWords);
        }
        
        // Validate custom field keys (if provided)
        const customFieldKeys = $('input[name="llms_generator_settings[custom_field_keys]"]').val();
        if (customFieldKeys) {
            // Check for valid format (alphanumeric, underscore, comma, space)
            const validPattern = /^[a-zA-Z0-9_,\s-]+$/;
            if (!validPattern.test(customFieldKeys)) {
                isValid = false;
                errors.push(llmsValidation.messages.invalidCustomFields);
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            
            // Remove existing error notices
            $('.llms-validation-error').remove();
            
            // Display errors
            let errorHtml = '<div class="notice notice-error llms-validation-error"><p><strong>' + 
                            llmsValidation.messages.validationFailed + '</strong></p><ul>';
            
            errors.forEach(function(error) {
                errorHtml += '<li>' + error + '</li>';
            });
            
            errorHtml += '</ul></div>';
            
            // Insert error notice after heading
            $('.wrap h1').after(errorHtml);
            
            // Scroll to top
            $('html, body').animate({scrollTop: 0}, 300);
            
            return false;
        }
        
        return true;
    });
    
    // Real-time validation for number inputs
    $('input[type="number"]').on('input', function() {
        const value = parseInt($(this).val());
        const min = parseInt($(this).attr('min'));
        const max = parseInt($(this).attr('max'));
        
        if (value < min || value > max) {
            $(this).addClass('error');
            if (!$(this).next('.field-error').length) {
                $(this).after('<span class="field-error" style="color: red; font-size: 12px;">' + 
                             llmsValidation.messages.numberOutOfRange + '</span>');
            }
        } else {
            $(this).removeClass('error');
            $(this).next('.field-error').remove();
        }
    });
    
    // Highlight required checkboxes if none selected
    function checkPostTypes() {
        const container = $('#llms-post-types-sortable');
        const checked = container.find('input[type="checkbox"]:checked').length;
        
        if (checked === 0) {
            container.addClass('error-border');
            if (!container.prev('.field-error').length) {
                container.before('<p class="field-error" style="color: red;">' + 
                                llmsValidation.messages.selectPostType + '</p>');
            }
        } else {
            container.removeClass('error-border');
            container.prev('.field-error').remove();
        }
    }
    
    // Check on page load and on change
    checkPostTypes();
    $('#llms-post-types-sortable').on('change', 'input[type="checkbox"]', checkPostTypes);
});