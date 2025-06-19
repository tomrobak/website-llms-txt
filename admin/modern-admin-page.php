<?php
if (!defined('ABSPATH')) {
    exit;
}

$latest_post = apply_filters('get_llms_content', '');

// Display admin notices
$notices = array();

// Check for cache cleared
if (isset($_GET['cache_cleared']) && $_GET['cache_cleared'] === 'true' && 
    isset($_GET['_wpnonce'])) {
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
    if (wp_verify_nonce($nonce, 'llms_cache_cleared')) {
        $notices[] = array(
            'type' => 'success',
            'message' => sprintf(
                __('✅ Caches cleared successfully! Your LLMS.txt file has been regenerated. <a href="%s" target="_blank">View file →</a>', 'wp-llms-txt'),
                esc_url(home_url('/llms.txt'))
            ),
            'dismissible' => true
        );
    }
}

// Check for settings updated
if (isset($_GET['settings-updated']) && 
    isset($_GET['_wpnonce'])) {
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
    if (wp_verify_nonce($nonce, 'llms_options_update')) {
        $notices[] = array(
            'type' => 'success',
            'message' => __('✅ Settings saved successfully! The LLMS.txt file will be regenerated with your new settings.', 'wp-llms-txt'),
            'dismissible' => true
        );
    }
}

// Check for error log cleared
if (isset($_GET['error_log_cleared']) && $_GET['error_log_cleared'] === 'true' &&
    isset($_GET['_wpnonce'])) {
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
    if (wp_verify_nonce($nonce, 'llms_error_log_cleared')) {
        $notices[] = array(
            'type' => 'success',
            'message' => __('✅ Error log cleared successfully!', 'wp-llms-txt'),
            'dismissible' => true
        );
    }
}

// Check for import success
if (isset($_GET['import_success']) && $_GET['import_success'] === 'true' &&
    isset($_GET['_wpnonce'])) {
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
    if (wp_verify_nonce($nonce, 'llms_import_success')) {
        $notices[] = array(
            'type' => 'success',
            'message' => __('✅ Settings imported successfully! The LLMS.txt file will be regenerated with imported settings.', 'wp-llms-txt'),
            'dismissible' => true
        );
    }
}

// Check for errors
if (isset($_GET['error'])) {
    $error_code = sanitize_text_field($_GET['error']);
    $error_messages = array(
        'no_post_types' => __('⚠️ Please select at least one post type to include in your LLMS.txt file.', 'wp-llms-txt'),
        'generation_failed' => __('❌ Failed to generate LLMS.txt file. Please check file permissions and try again.', 'wp-llms-txt'),
        'permission_denied' => __('❌ Permission denied. Please check your user capabilities.', 'wp-llms-txt'),
        'import_file_error' => __('❌ Failed to upload import file. Please try again.', 'wp-llms-txt'),
        'import_invalid_file' => __('❌ Invalid file type. Please upload a JSON file.', 'wp-llms-txt'),
        'import_file_too_large' => __('❌ File too large. Maximum allowed size is 1MB.', 'wp-llms-txt'),
        'import_invalid_json' => __('❌ Invalid JSON format. Please check your export file.', 'wp-llms-txt'),
        'import_invalid_format' => __('❌ Invalid settings format. Please use a file exported from this plugin.', 'wp-llms-txt')
    );
    
    if (isset($error_messages[$error_code])) {
        $notices[] = array(
            'type' => 'error',
            'message' => $error_messages[$error_code],
            'dismissible' => true
        );
    }
}

// File status data
$upload_dir = wp_upload_dir();
$file_path = $upload_dir['basedir'] . '/llms.txt';
$file_exists = file_exists($file_path);
$file_size = $file_exists ? filesize($file_path) : 0;
$file_modified = $file_exists ? filemtime($file_path) : 0;

// Get settings
$settings = get_option('llms_generator_settings', array(
    'post_types' => array('page', 'documentation', 'post'),
    'max_posts' => 100,
    'include_meta' => true,
    'include_excerpts' => true,
    'include_taxonomies' => true,
    'update_frequency' => 'immediate'
));

// Enqueue modern styles
wp_enqueue_style('llms-modern-admin', plugins_url('admin/modern-admin-styles.css', dirname(__FILE__)), array(), LLMS_VERSION);

// Display all notices
foreach ($notices as $notice) {
    $alert_class = $notice['type'] === 'error' ? 'error' : 
                  ($notice['type'] === 'success' ? 'success' : 'info');
    printf(
        '<div class="llms-alert %s"><p>%s</p></div>',
        esc_attr($alert_class),
        wp_kses_post($notice['message'])
    );
}
?>

<div class="llms-container">
    <div class="llms-header">
        <h1>🤖 WP LLMs.txt</h1>
        <p>Make your WordPress site AI-friendly with automated content discovery</p>
    </div>

    <!-- Status Overview -->
    <div class="llms-card">
        <div class="llms-card-header">
            <h2 class="llms-card-title">📊 File Status</h2>
            <p class="llms-card-description">Current status of your LLMS.txt file</p>
        </div>
        <div class="llms-card-content">
            <?php if ($latest_post && $file_exists): ?>
                <div class="llms-status success">
                    <span>✅</span>
                    <span><?php esc_html_e('LLMS.txt is active and working!', 'wp-llms-txt'); ?></span>
                </div>
                
                <div class="llms-grid cols-3" style="margin-top: 1.5rem;">
                    <div>
                        <div class="llms-text-sm llms-text-muted">File Size</div>
                        <div class="llms-font-semibold"><?php echo esc_html(size_format($file_size)); ?></div>
                    </div>
                    <div>
                        <div class="llms-text-sm llms-text-muted">Last Updated</div>
                        <div class="llms-font-semibold"><?php echo esc_html(human_time_diff($file_modified, current_time('timestamp')) . ' ' . __('ago', 'wp-llms-txt')); ?></div>
                    </div>
                    <div>
                        <div class="llms-text-sm llms-text-muted">Actions</div>
                        <div class="llms-flex gap-2">
                            <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank" class="llms-button small secondary">
                                <?php esc_html_e('View File', 'wp-llms-txt'); ?> ↗
                            </a>
                            <?php if (class_exists('RankMath') || (defined('WPSEO_VERSION') && class_exists('WPSEO_Sitemaps'))): ?>
                                <a href="<?php echo esc_url(home_url('/llms-sitemap.xml')); ?>" target="_blank" class="llms-button small secondary">
                                    <?php esc_html_e('Sitemap', 'wp-llms-txt'); ?> ↗
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="llms-status error">
                    <span>❌</span>
                    <span><?php esc_html_e('LLMS.txt file not found', 'wp-llms-txt'); ?></span>
                </div>
                <p class="llms-text-sm llms-text-muted llms-mt-1">
                    <?php esc_html_e('Click "Clear Caches" in the management section to generate the file.', 'wp-llms-txt'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="llms-tabs">
        <ul class="llms-tab-list">
            <li><button class="llms-tab-button active" data-tab="content">📝 Content Settings</button></li>
            <li><button class="llms-tab-button" data-tab="management">🛠️ Management</button></li>
            <li><button class="llms-tab-button" data-tab="import-export">📦 Import/Export</button></li>
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <li><button class="llms-tab-button" data-tab="debug">🐛 Debug</button></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Content Settings Tab -->
    <div id="content-tab" class="llms-tab-panel active">
        <div class="llms-card">
            <div class="llms-card-header">
                <h2 class="llms-card-title">📝 Content Configuration</h2>
                <p class="llms-card-description">Configure which content types and settings to include in your LLMS.txt file</p>
            </div>
            <div class="llms-card-content">
                <form method="post" action="options.php" id="llms-settings-form">
                    <?php settings_fields('llms_generator_settings'); ?>
                    
                    <div class="llms-form-group">
                        <label class="llms-label"><?php esc_html_e('Post Types', 'wp-llms-txt'); ?></label>
                        <p class="llms-text-sm llms-text-muted llms-mb-2"><?php esc_html_e('Select the post types to include in your LLMS.txt file', 'wp-llms-txt'); ?></p>
                        
                        <div class="llms-post-types-list">
                            <?php
                            $post_types = get_post_types(array('public' => true), 'objects');
                            $selected_types = is_array($settings['post_types']) ? $settings['post_types'] : array();
                            
                            foreach ($post_types as $post_type) {
                                if (in_array($post_type->name, array('attachment', 'llms_txt'))) {
                                    continue;
                                }
                                
                                $is_checked = in_array($post_type->name, $selected_types);
                                ?>
                                <div class="llms-post-type-item <?php echo $is_checked ? 'active' : ''; ?>">
                                    <input type="checkbox" 
                                           id="post_type_<?php echo esc_attr($post_type->name); ?>"
                                           name="llms_generator_settings[post_types][]" 
                                           value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked($is_checked); ?>>
                                    <label for="post_type_<?php echo esc_attr($post_type->name); ?>">
                                        <?php echo esc_html($post_type->labels->name); ?>
                                    </label>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>

                    <div class="llms-grid cols-2">
                        <div class="llms-form-group">
                            <label class="llms-label" for="max-posts"><?php esc_html_e('Maximum posts per type', 'wp-llms-txt'); ?></label>
                            <input type="number" 
                                   id="max-posts"
                                   class="llms-input"
                                   name="llms_generator_settings[max_posts]" 
                                   value="<?php echo esc_attr($settings['max_posts']); ?>"
                                   min="1"
                                   max="100000">
                        </div>

                        <div class="llms-form-group">
                            <label class="llms-label" for="max-words"><?php esc_html_e('Maximum words per post', 'wp-llms-txt'); ?></label>
                            <input type="number"
                                   id="max-words"
                                   class="llms-input"
                                   name="llms_generator_settings[max_words]"
                                   value="<?php echo esc_attr($settings['max_words'] ?? 250); ?>"
                                   min="1"
                                   max="100000">
                        </div>
                    </div>

                    <div class="llms-form-group">
                        <label class="llms-label"><?php esc_html_e('Content Options', 'wp-llms-txt'); ?></label>
                        
                        <div class="llms-checkbox-wrapper">
                            <input type="checkbox" 
                                   id="include-meta"
                                   name="llms_generator_settings[include_meta]" 
                                   value="1"
                                   <?php checked(!empty($settings['include_meta'])); ?>>
                            <label for="include-meta"><?php esc_html_e('Include meta information (publish date, author, etc.)', 'wp-llms-txt'); ?></label>
                        </div>
                        
                        <div class="llms-checkbox-wrapper">
                            <input type="checkbox" 
                                   id="include-excerpts"
                                   name="llms_generator_settings[include_excerpts]" 
                                   value="1"
                                   <?php checked(!empty($settings['include_excerpts'])); ?>>
                            <label for="include-excerpts"><?php esc_html_e('Include post excerpts', 'wp-llms-txt'); ?></label>
                        </div>
                        
                        <div class="llms-checkbox-wrapper">
                            <input type="checkbox" 
                                   id="include-taxonomies"
                                   name="llms_generator_settings[include_taxonomies]" 
                                   value="1"
                                   <?php checked(!empty($settings['include_taxonomies'])); ?>>
                            <label for="include-taxonomies"><?php esc_html_e('Include taxonomies (categories, tags, etc.)', 'wp-llms-txt'); ?></label>
                        </div>
                    </div>

                    <div class="llms-form-group">
                        <label class="llms-label"><?php esc_html_e('Advanced Options', 'wp-llms-txt'); ?></label>
                        
                        <div class="llms-checkbox-wrapper">
                            <input type="checkbox" 
                                   id="include-custom-fields"
                                   name="llms_generator_settings[include_custom_fields]" 
                                   value="1"
                                   <?php checked(!empty($settings['include_custom_fields'])); ?>>
                            <label for="include-custom-fields"><?php esc_html_e('Include custom fields', 'wp-llms-txt'); ?></label>
                        </div>
                        <p class="llms-text-xs llms-text-muted"><?php esc_html_e('Include publicly visible custom field data in the generated content.', 'wp-llms-txt'); ?></p>
                        
                        <div class="llms-checkbox-wrapper">
                            <input type="checkbox" 
                                   id="exclude-private-taxonomies"
                                   name="llms_generator_settings[exclude_private_taxonomies]" 
                                   value="1"
                                   <?php checked(!empty($settings['exclude_private_taxonomies'])); ?>>
                            <label for="exclude-private-taxonomies"><?php esc_html_e('Exclude private taxonomies', 'wp-llms-txt'); ?></label>
                        </div>
                        <p class="llms-text-xs llms-text-muted"><?php esc_html_e('Hide taxonomies marked as private from the generated content.', 'wp-llms-txt'); ?></p>
                    </div>

                    <div class="llms-form-group">
                        <label class="llms-label" for="update-frequency"><?php esc_html_e('Update Frequency', 'wp-llms-txt'); ?></label>
                        <select id="update-frequency" class="llms-select" name="llms_generator_settings[update_frequency]">
                            <option value="immediate" <?php selected($settings['update_frequency'], 'immediate'); ?>>
                                <?php esc_html_e('Immediate', 'wp-llms-txt'); ?>
                            </option>
                            <option value="daily" <?php selected($settings['update_frequency'], 'daily'); ?>>
                                <?php esc_html_e('Daily', 'wp-llms-txt'); ?>
                            </option>
                            <option value="weekly" <?php selected($settings['update_frequency'], 'weekly'); ?>>
                                <?php esc_html_e('Weekly', 'wp-llms-txt'); ?>
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="llms-button primary">
                        <?php esc_html_e('Save Settings', 'wp-llms-txt'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Management Tab -->
    <div id="management-tab" class="llms-tab-panel">
        <div class="llms-card">
            <div class="llms-card-header">
                <h2 class="llms-card-title">🛠️ Cache Management</h2>
                <p class="llms-card-description">Clear caches and regenerate your LLMS.txt file</p>
            </div>
            <div class="llms-card-content">
                <p><?php esc_html_e('This tool helps ensure your LLMS.txt file is properly reflected in your sitemap by:', 'wp-llms-txt'); ?></p>
                <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                    <li><?php esc_html_e('Clearing sitemap caches', 'wp-llms-txt'); ?></li>
                    <li><?php esc_html_e('Resetting WordPress rewrite rules', 'wp-llms-txt'); ?></li>
                    <li><?php esc_html_e('Forcing sitemap regeneration', 'wp-llms-txt'); ?></li>
                </ul>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="clear_caches">
                    <?php wp_nonce_field('clear_caches', 'clear_caches_nonce'); ?>
                    <button type="submit" class="llms-button primary">
                        <?php esc_html_e('Clear Caches', 'wp-llms-txt'); ?>
                    </button>
                </form>
            </div>
        </div>

        <?php
        // Display error logs if any exist
        $errors = get_transient('llms_generation_errors');
        if ($errors && is_array($errors) && !empty($errors)):
        ?>
        <div class="llms-card">
            <div class="llms-card-header">
                <h2 class="llms-card-title">🔍 Error Log</h2>
                <p class="llms-card-description">Recent errors encountered during file generation</p>
            </div>
            <div class="llms-card-content">
                <div class="llms-code">
                    <?php foreach (array_reverse($errors) as $error): ?>
                        <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #475569;">
                            <strong style="color: #fbbf24;">[<?php echo esc_html($error['time']); ?>]</strong><br>
                            <?php echo esc_html($error['message']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 1rem;">
                    <input type="hidden" name="action" value="clear_error_log">
                    <?php wp_nonce_field('clear_error_log', 'clear_error_log_nonce'); ?>
                    <button type="submit" class="llms-button secondary">
                        <?php esc_html_e('Clear Error Log', 'wp-llms-txt'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Import/Export Tab -->
    <div id="import-export-tab" class="llms-tab-panel">
        <div class="llms-grid cols-2">
            <div class="llms-card">
                <div class="llms-card-header">
                    <h2 class="llms-card-title">📤 Export Settings</h2>
                    <p class="llms-card-description">Download your current configuration</p>
                </div>
                <div class="llms-card-content">
                    <p><?php esc_html_e('Export your current plugin settings as a JSON file for backup or transfer to another site.', 'wp-llms-txt'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="llms_export_settings">
                        <?php wp_nonce_field('llms_export_settings', 'llms_export_nonce'); ?>
                        <button type="submit" class="llms-button secondary">
                            <?php esc_html_e('Download Settings', 'wp-llms-txt'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="llms-card">
                <div class="llms-card-header">
                    <h2 class="llms-card-title">📥 Import Settings</h2>
                    <p class="llms-card-description">Upload and restore a configuration</p>
                </div>
                <div class="llms-card-content">
                    <p><?php esc_html_e('Import settings from a previously exported JSON file.', 'wp-llms-txt'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="llms_import_settings">
                        <?php wp_nonce_field('llms_import_settings', 'llms_import_nonce'); ?>
                        <div class="llms-form-group">
                            <label class="llms-label" for="llms_import_file"><?php esc_html_e('Select JSON file', 'wp-llms-txt'); ?></label>
                            <input type="file" 
                                   name="llms_import_file" 
                                   id="llms_import_file" 
                                   class="llms-file-input"
                                   accept=".json">
                            <p class="llms-text-xs llms-text-muted llms-mt-1">
                                <?php esc_html_e('Maximum file size: 1MB. Only JSON files are accepted.', 'wp-llms-txt'); ?>
                            </p>
                        </div>
                        <button type="submit" class="llms-button secondary">
                            <?php esc_html_e('Import Settings', 'wp-llms-txt'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
    <!-- Debug Tab -->
    <div id="debug-tab" class="llms-tab-panel">
        <div class="llms-card">
            <div class="llms-card-header">
                <h2 class="llms-card-title">🐛 Debug Information</h2>
                <p class="llms-card-description">System information for troubleshooting</p>
            </div>
            <div class="llms-card-content">
                <table class="llms-table">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('WordPress Version:', 'wp-llms-txt'); ?></strong></td>
                            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('PHP Version:', 'wp-llms-txt'); ?></strong></td>
                            <td><?php echo esc_html(phpversion()); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Plugin Version:', 'wp-llms-txt'); ?></strong></td>
                            <td><?php echo esc_html(LLMS_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Memory Limit:', 'wp-llms-txt'); ?></strong></td>
                            <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Max Execution Time:', 'wp-llms-txt'); ?></strong></td>
                            <td><?php echo esc_html(ini_get('max_execution_time')); ?> seconds</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Upload Directory Writable:', 'wp-llms-txt'); ?></strong></td>
                            <td><?php 
                                $upload_dir = wp_upload_dir();
                                echo is_writable($upload_dir['basedir']) ? 
                                    '<span class="llms-badge success">✓ ' . esc_html__('Yes', 'wp-llms-txt') . '</span>' : 
                                    '<span class="llms-badge warning">✗ ' . esc_html__('No', 'wp-llms-txt') . '</span>'; 
                            ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- WPLove.co Community Footer -->
    <div class="llms-footer">
        <div class="llms-footer-content">
            <h3>🎨 Join the WPLove.co Community!</h3>
            <p>Connect with passionate WordPress users, photographers, and creatives. Share knowledge, get inspired, and build amazing things together in our niche community packed with real-world WordPress wisdom.</p>
            <div class="llms-footer-buttons">
                <a href="https://wplove.co" target="_blank" class="llms-button primary">
                    Visit WPLove.co 📸
                </a>
                <a href="https://github.com/tomrobak/website-llms-txt" target="_blank" class="llms-button secondary">
                    GitHub Repository
                </a>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.llms-tab-button').on('click', function() {
        const targetTab = $(this).data('tab');
        
        // Update button states
        $('.llms-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Update panel visibility
        $('.llms-tab-panel').removeClass('active');
        $('#' + targetTab + '-tab').addClass('active');
    });

    // Handle post type checkboxes
    $('.llms-post-type-item input[type="checkbox"]').on('change', function() {
        const $item = $(this).closest('.llms-post-type-item');
        if ($(this).is(':checked')) {
            $item.addClass('active');
        } else {
            $item.removeClass('active');
        }
    });

    // Form validation
    $('#llms-settings-form').on('submit', function(e) {
        const checkedTypes = $('.llms-post-type-item input[type="checkbox"]:checked');
        if (checkedTypes.length === 0) {
            e.preventDefault();
            alert('<?php esc_html_e('Please select at least one post type.', 'wp-llms-txt'); ?>');
            return false;
        }
    });
});
</script>