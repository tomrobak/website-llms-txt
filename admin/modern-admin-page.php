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

// Check for file generated
if (isset($_GET['file_generated']) && $_GET['file_generated'] === 'true' && 
    isset($_GET['_wpnonce'])) {
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
    if (wp_verify_nonce($nonce, 'llms_file_generated')) {
        $notices[] = array(
            'type' => 'success',
            'message' => sprintf(
                __('🎉 LLMS.txt file generated successfully! <a href="%s" target="_blank">View your file →</a>', 'wp-llms-txt'),
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

// Check for cache populated
if (isset($_GET['cache_populated']) && $_GET['cache_populated'] === 'true' &&
    isset($_GET['_wpnonce'])) {
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
    if (wp_verify_nonce($nonce, 'llms_cache_populated')) {
        $notices[] = array(
            'type' => 'success',
            'message' => __('✅ Cache population scheduled! The cache will be populated in the background.', 'wp-llms-txt'),
            'dismissible' => true
        );
    }
}

// Check for cache warmed
if (isset($_GET['cache_warmed']) && $_GET['cache_warmed'] === 'true' &&
    isset($_GET['_wpnonce'])) {
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
    if (wp_verify_nonce($nonce, 'llms_cache_warmed')) {
        $notices[] = array(
            'type' => 'success',
            'message' => __('🔥 Cache warming scheduled! Stale cache entries will be updated in the background.', 'wp-llms-txt'),
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

// File status data using correct generator methods
$generator = new LLMS_Generator();
$file_exists = $generator->file_exists();
$file_size = $generator->get_file_size();
$file_modified = $generator->get_file_mtime();
$file_path = $generator->get_llms_file_path();

// Get settings with proper defaults
$default_settings = array(
    'post_types' => array('page', 'post'), // Only core post types as default
    'max_posts' => 100,
    'max_words' => 250,
    'include_meta' => true,
    'include_excerpts' => true,
    'include_taxonomies' => true,
    'update_frequency' => 'immediate'
);

$settings = get_option('llms_generator_settings', $default_settings);
// Ensure all keys exist
$settings = wp_parse_args($settings, $default_settings);

// Enqueue modern styles
wp_enqueue_style('llms-modern-admin', plugins_url('admin/modern-admin-styles.css', dirname(__FILE__)), array(), LLMS_VERSION);

// Display all notices
foreach ($notices as $notice) {
    $alert_class = $notice['type'] === 'error' ? 'error' : 
                  ($notice['type'] === 'success' ? 'success' : 'info');
    $dismissible_class = !empty($notice['dismissible']) ? ' dismissible' : '';
    printf(
        '<div class="llms-alert %s%s"><p>%s</p>%s</div>',
        esc_attr($alert_class),
        esc_attr($dismissible_class),
        wp_kses_post($notice['message']),
        !empty($notice['dismissible']) ? '<button type="button" class="llms-alert-dismiss">&times;</button>' : ''
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
            <h2 class="llms-card-title">📊 File Status & Quick Actions</h2>
            <p class="llms-card-description">Current status of your LLMS.txt file and generation controls</p>
        </div>
        <div class="llms-card-content">
            <?php if ($file_exists): ?>
                <div class="llms-status success">
                    <span>✅</span>
                    <span><?php esc_html_e('LLMS.txt file is active and working!', 'wp-llms-txt'); ?></span>
                </div>
                
                <div class="llms-grid cols-3" style="margin-top: 1.5rem;">
                    <div>
                        <div class="llms-text-sm llms-text-muted">File Location</div>
                        <div class="llms-font-semibold llms-text-xs" style="word-break: break-all;"><?php echo esc_html(basename($file_path)); ?></div>
                    </div>
                    <div>
                        <div class="llms-text-sm llms-text-muted">File Size</div>
                        <div class="llms-font-semibold"><?php echo esc_html(size_format($file_size)); ?></div>
                    </div>
                    <div>
                        <div class="llms-text-sm llms-text-muted">Last Updated</div>
                        <div class="llms-font-semibold"><?php echo esc_html(human_time_diff($file_modified, current_time('timestamp')) . ' ' . __('ago', 'wp-llms-txt')); ?></div>
                    </div>
                </div>
                
                <div class="llms-flex gap-2" style="margin-top: 1.5rem; align-items: center; flex-wrap: wrap;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                        <input type="hidden" name="action" value="generate_llms_file">
                        <?php wp_nonce_field('generate_llms_file', 'generate_llms_file_nonce'); ?>
                        <button type="submit" class="llms-button primary">
                            🔄 <?php esc_html_e('Regenerate File', 'wp-llms-txt'); ?>
                        </button>
                    </form>
                    <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank" class="llms-button secondary">
                        👁️ <?php esc_html_e('View File', 'wp-llms-txt'); ?>
                    </a>
                    <?php if (class_exists('RankMath') || (defined('WPSEO_VERSION') && class_exists('WPSEO_Sitemaps'))): ?>
                        <a href="<?php echo esc_url(home_url('/llms-sitemap.xml')); ?>" target="_blank" class="llms-button secondary">
                            🗺️ <?php esc_html_e('View Sitemap', 'wp-llms-txt'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="llms-status warning">
                    <span>⚠️</span>
                    <span><?php esc_html_e('LLMS.txt file not found - ready to generate!', 'wp-llms-txt'); ?></span>
                </div>
                <p class="llms-text-sm llms-text-muted llms-mt-1 llms-mb-2">
                    <?php esc_html_e('Click the button below to generate your LLMS.txt file and make your site AI-discoverable.', 'wp-llms-txt'); ?>
                </p>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                    <input type="hidden" name="action" value="generate_llms_file">
                    <?php wp_nonce_field('generate_llms_file', 'generate_llms_file_nonce'); ?>
                    <button type="submit" class="llms-button primary" style="font-size: 1rem; padding: 0.875rem 1.5rem;">
                        🚀 <?php esc_html_e('Generate LLMS.txt File', 'wp-llms-txt'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="llms-tabs">
        <ul class="llms-tab-list">
            <li><button class="llms-tab-button active" data-tab="content">📝 Content Settings</button></li>
            <li><button class="llms-tab-button" data-tab="management">🛠️ Management</button></li>
            <li><button class="llms-tab-button" data-tab="import-export">📦 Import/Export</button></li>
            <li><button class="llms-tab-button" data-tab="logs">📋 Logs</button></li>
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
                    <input type="hidden" id="active-tab" name="active_tab" value="content" />
                    
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
                                        <small style="color: #999; font-size: 11px;">(<?php echo esc_html($post_type->name); ?>)</small>
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
                                   value="<?php echo esc_attr($settings['max_words']); ?>"
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
        <div class="llms-grid cols-2">
            <div class="llms-card">
                <div class="llms-card-header">
                    <h2 class="llms-card-title">🚀 File Generation</h2>
                    <p class="llms-card-description">Generate or regenerate your LLMS.txt file</p>
                </div>
                <div class="llms-card-content">
                    <p><?php esc_html_e('Generate a fresh LLMS.txt file based on your current settings and content.', 'wp-llms-txt'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="generate_llms_file">
                        <?php wp_nonce_field('generate_llms_file', 'generate_llms_file_nonce'); ?>
                        <button type="submit" class="llms-button primary">
                            🔄 <?php esc_html_e('Generate LLMS.txt File', 'wp-llms-txt'); ?>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="llms-card">
                <div class="llms-card-header">
                    <h2 class="llms-card-title">🛠️ Advanced: Clear Caches</h2>
                    <p class="llms-card-description">Clear system caches and force regeneration</p>
                </div>
                <div class="llms-card-content">
                    <p><?php esc_html_e('Clear SEO plugin caches and reset rewrite rules. Use this if you have sitemap issues.', 'wp-llms-txt'); ?></p>
                    <ul style="margin: 1rem 0; padding-left: 1.5rem; font-size: 0.875rem;">
                        <li><?php esc_html_e('Clears sitemap caches', 'wp-llms-txt'); ?></li>
                        <li><?php esc_html_e('Resets WordPress rewrite rules', 'wp-llms-txt'); ?></li>
                        <li><?php esc_html_e('Forces sitemap regeneration', 'wp-llms-txt'); ?></li>
                    </ul>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="clear_caches">
                        <?php wp_nonce_field('clear_caches', 'clear_caches_nonce'); ?>
                        <button type="submit" class="llms-button secondary">
                            🧹 <?php esc_html_e('Clear All Caches', 'wp-llms-txt'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="llms-card">
            <div class="llms-card-header">
                <h2 class="llms-card-title">🔄 Cache Management</h2>
                <p class="llms-card-description">Manage content cache for better performance</p>
            </div>
            <div class="llms-card-content">
                <p><?php esc_html_e('The plugin uses a database cache to improve generation performance. Use these tools to manage the cache.', 'wp-llms-txt'); ?></p>
                
                <div class="llms-grid cols-2" style="margin-top: 1rem;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="populate_llms_cache">
                        <?php wp_nonce_field('populate_llms_cache', 'populate_llms_cache_nonce'); ?>
                        <button type="submit" class="llms-button secondary" style="width: 100%;">
                            📦 <?php esc_html_e('Populate Cache', 'wp-llms-txt'); ?>
                        </button>
                        <p class="llms-text-xs llms-text-muted" style="margin-top: 0.5rem;">
                            <?php esc_html_e('Add all existing posts to cache', 'wp-llms-txt'); ?>
                        </p>
                    </form>
                    
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="warm_llms_cache">
                        <?php wp_nonce_field('warm_llms_cache', 'warm_llms_cache_nonce'); ?>
                        <button type="submit" class="llms-button secondary" style="width: 100%;">
                            🔥 <?php esc_html_e('Warm Cache', 'wp-llms-txt'); ?>
                        </button>
                        <p class="llms-text-xs llms-text-muted" style="margin-top: 0.5rem;">
                            <?php esc_html_e('Update stale cache entries', 'wp-llms-txt'); ?>
                        </p>
                    </form>
                </div>
                
                <?php
                // Show cache stats
                global $wpdb;
                $table_cache = $wpdb->prefix . 'llms_txt_cache';
                $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cache}");
                $stale_count = $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$table_cache} c 
                     LEFT JOIN {$wpdb->posts} p ON c.post_id = p.ID 
                     WHERE p.post_modified > c.modified 
                     AND p.post_status = 'publish'"
                );
                
                if ($cache_count !== null) {
                    echo '<div class="llms-stats" style="margin-top: 1.5rem; padding: 1rem; background: #f1f5f9; border-radius: 0.5rem;">';
                    echo '<h4 style="margin: 0 0 0.5rem 0; font-size: 0.875rem; font-weight: 600;">Cache Statistics</h4>';
                    echo '<div class="llms-grid cols-2" style="gap: 1rem;">';
                    echo '<div>';
                    echo '<p class="llms-text-sm llms-text-muted" style="margin: 0;">';
                    printf(
                        esc_html__('Total cached posts: %s', 'wp-llms-txt'),
                        '<strong>' . number_format($cache_count) . '</strong>'
                    );
                    echo '</p>';
                    echo '</div>';
                    echo '<div>';
                    echo '<p class="llms-text-sm llms-text-muted" style="margin: 0;">';
                    printf(
                        esc_html__('Stale cache entries: %s', 'wp-llms-txt'),
                        '<strong>' . number_format($stale_count ?: 0) . '</strong>'
                    );
                    echo '</p>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <!-- Progress Tracker Section -->
        <?php
        $progress_id = isset($_GET['progress']) ? sanitize_text_field($_GET['progress']) : '';
        $show_progress = !empty($progress_id) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'llms_progress_' . $progress_id);
        ?>
        <div class="llms-progress-section" <?php echo $show_progress ? 'data-progress-id="' . esc_attr($progress_id) . '"' : 'style="display: none;"'; ?>>
            <div class="llms-card">
                <div class="llms-card-header">
                    <h2 class="llms-card-title">📊 Generation Progress</h2>
                    <p class="llms-card-description">Real-time progress tracking</p>
                </div>
                <div class="llms-card-content">
                    <div class="llms-progress-container">
                        <div class="llms-progress-wrapper">
                            <div class="llms-progress-bar-container">
                                <div class="llms-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
                            </div>
                            <div class="llms-progress-text">0% (0/0)</div>
                        </div>
                        
                        <div class="llms-progress-details"></div>
                        
                        <div class="llms-progress-controls">
                            <button type="button" class="llms-cancel-btn">❌ Cancel</button>
                            <button type="button" class="llms-clear-logs-btn">🗑️ Clear Old Logs</button>
                        </div>
                    </div>
                    
                    <!-- Log Viewer -->
                    <div class="llms-log-viewer">
                        <div class="llms-log-header">
                            <h3 class="llms-log-title">📝 Activity Log</h3>
                            <div class="llms-log-controls">
                                <select class="llms-log-filter">
                                    <option value="">All Levels</option>
                                    <option value="INFO">Info</option>
                                    <option value="WARNING">Warnings</option>
                                    <option value="ERROR">Errors</option>
                                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                                    <option value="DEBUG">Debug</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="llms-log-container"></div>
                    </div>
                </div>
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

    <!-- Logs Tab -->
    <div id="logs-tab" class="llms-tab-panel">
        <div class="llms-card">
            <div class="llms-card-header">
                <h2 class="llms-card-title">📋 Generation Logs</h2>
                <p class="llms-card-description">View logs from LLMS.txt file generation process</p>
            </div>
            <div class="llms-card-content">
                <!-- Log Filters -->
                <div class="llms-form-group">
                    <label for="log-level-filter">Filter by Level:</label>
                    <select id="log-level-filter" class="llms-select">
                        <option value="ALL">All Levels</option>
                        <option value="INFO">Info</option>
                        <option value="WARNING">Warning</option>
                        <option value="ERROR">Error</option>
                        <option value="DEBUG">Debug</option>
                    </select>
                </div>

                <!-- Logs Container -->
                <div id="logs-container" class="llms-logs-container" style="background: #f4f4f4; border: 1px solid #ddd; border-radius: 4px; padding: 15px; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 13px;">
                    <p style="color: #666;">Loading logs...</p>
                </div>

                <!-- Log Actions -->
                <div class="llms-button-group" style="margin-top: 15px;">
                    <button type="button" id="refresh-logs" class="llms-button llms-button-secondary">
                        🔄 Refresh Logs
                    </button>
                    <button type="button" id="clear-logs" class="llms-button llms-button-secondary llms-button-danger">
                        🗑️ Clear Old Logs
                    </button>
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
        
        // Update hidden field
        $('#active-tab').val(targetTab);
        
        // Store in localStorage
        localStorage.setItem('llms_active_tab', targetTab);
    });
    
    // Restore active tab from URL or localStorage
    const urlParams = new URLSearchParams(window.location.search);
    const tabFromUrl = urlParams.get('tab');
    const tabFromStorage = localStorage.getItem('llms_active_tab');
    const activeTab = tabFromUrl || tabFromStorage || 'content';
    
    if (activeTab !== 'content') {
        $('.llms-tab-button[data-tab="' + activeTab + '"]').click();
    }

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
    
    // Add active tab to all forms before submission
    $('form').on('submit', function() {
        const activeTab = $('.llms-tab-button.active').data('tab') || 'content';
        if (!$(this).find('input[name="active_tab"]').length) {
            $(this).append('<input type="hidden" name="active_tab" value="' + activeTab + '">');
        }
    });

    // Handle dismissible notifications
    $('.llms-alert-dismiss').on('click', function() {
        $(this).closest('.llms-alert').fadeOut(300, function() {
            $(this).remove();
        });
    });

    // Auto-dismiss success notifications after 5 seconds
    $('.llms-alert.success.dismissible').delay(5000).fadeOut(300, function() {
        $(this).remove();
    });

    // Logs functionality
    let lastLogId = 0;
    
    function loadLogs(append = false) {
        const level = $('#log-level-filter').val();
        
        $.ajax({
            url: '<?php echo esc_url(rest_url('wp-llms-txt/v1/logs')); ?>',
            method: 'GET',
            data: {
                last_id: append ? lastLogId : 0,
                level: level,
                limit: 100
            },
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            },
            success: function(response) {
                if (response.logs && response.logs.length > 0) {
                    let logsHtml = '';
                    response.logs.forEach(function(log) {
                        const levelClass = log.level.toLowerCase();
                        logsHtml += `<div class="log-entry log-${levelClass}" style="margin-bottom: 8px; padding: 8px; border-left: 3px solid ${getLevelColor(log.level)};">`;
                        logsHtml += `<span style="color: #666;">[${log.timestamp}]</span> `;
                        logsHtml += `<span style="font-weight: bold; color: ${getLevelColor(log.level)};">${log.level}</span>: `;
                        logsHtml += `<span>${escapeHtml(log.message)}</span>`;
                        if (log.context) {
                            logsHtml += `<pre style="margin-top: 5px; font-size: 11px; color: #666;">${JSON.stringify(log.context, null, 2)}</pre>`;
                        }
                        logsHtml += '</div>';
                        lastLogId = Math.max(lastLogId, log.id);
                    });
                    
                    if (append) {
                        $('#logs-container').append(logsHtml);
                    } else {
                        $('#logs-container').html(logsHtml);
                    }
                } else if (!append) {
                    $('#logs-container').html('<p style="color: #666;">No logs found.</p>');
                }
            },
            error: function(xhr) {
                $('#logs-container').html('<p style="color: red;">Error loading logs. Make sure to deactivate and reactivate the plugin.</p>');
                console.error('Error loading logs:', xhr);
            }
        });
    }
    
    function getLevelColor(level) {
        switch(level) {
            case 'ERROR': return '#dc3545';
            case 'WARNING': return '#ffc107';
            case 'INFO': return '#17a2b8';
            case 'DEBUG': return '#6c757d';
            default: return '#333';
        }
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    // Load logs when tab is shown
    $('.llms-tab-button[data-tab="logs"]').on('click', function() {
        loadLogs();
    });
    
    // Auto-start generation if progress ID is present
    const progressElement = document.querySelector('[data-progress-id]');
    if (progressElement && progressElement.dataset.progressId) {
        const progressId = progressElement.dataset.progressId;
        console.log('Starting generation for progress ID:', progressId);
        
        // Start the generation process via REST API
        $.ajax({
            url: '<?php echo esc_url(rest_url('wp-llms-txt/v1/generate/start')); ?>',
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            },
            success: function(response) {
                console.log('Generation started:', response);
            },
            error: function(xhr) {
                console.error('Failed to start generation:', xhr);
                if (xhr.status === 409) {
                    console.log('Generation already running');
                }
            }
        });
    }
    
    // Refresh logs button
    $('#refresh-logs').on('click', function() {
        loadLogs();
    });
    
    // Filter change
    $('#log-level-filter').on('change', function() {
        lastLogId = 0;
        loadLogs();
    });
    
    // Clear logs button
    $('#clear-logs').on('click', function() {
        if (confirm('Are you sure you want to clear old logs? This will delete logs older than 24 hours.')) {
            $.ajax({
                url: '<?php echo esc_url(rest_url('wp-llms-txt/v1/logs')); ?>',
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                },
                success: function(response) {
                    alert('Old logs cleared successfully.');
                    loadLogs();
                },
                error: function() {
                    alert('Error clearing logs.');
                }
            });
        }
    });
});
</script>