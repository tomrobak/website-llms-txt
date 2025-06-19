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

// Check for errors
if (isset($_GET['error'])) {
    $error_code = sanitize_text_field($_GET['error']);
    $error_messages = array(
        'no_post_types' => __('⚠️ Please select at least one post type to include in your LLMS.txt file.', 'wp-llms-txt'),
        'generation_failed' => __('❌ Failed to generate LLMS.txt file. Please check file permissions and try again.', 'wp-llms-txt'),
        'permission_denied' => __('❌ Permission denied. Please check your user capabilities.', 'wp-llms-txt')
    );
    
    if (isset($error_messages[$error_code])) {
        $notices[] = array(
            'type' => 'error',
            'message' => $error_messages[$error_code],
            'dismissible' => true
        );
    }
}

// Display all notices
foreach ($notices as $notice) {
    printf(
        '<div class="notice notice-%s %s"><p>%s</p></div>',
        esc_attr($notice['type']),
        $notice['dismissible'] ? 'is-dismissible' : '',
        wp_kses_post($notice['message'])
    );
}
?>

<div class="wrap">
    <h1><?php esc_html_e('WP llms.txt', 'wp-llms-txt'); ?></h1>

    <div class="card">
        <h2><?php esc_html_e('📊 File Status', 'wp-llms-txt'); ?></h2>
        <?php 
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/llms.txt';
        $file_exists = file_exists($file_path);
        $file_size = $file_exists ? filesize($file_path) : 0;
        $file_modified = $file_exists ? filemtime($file_path) : 0;
        ?>
        
        <?php if ($latest_post && $file_exists): ?>
            <div style="background: #e7f7e7; border-left: 4px solid #46b450; padding: 12px; margin: 12px 0;">
                <p style="margin: 0;"><strong>✅ <?php esc_html_e('LLMS.txt is active and working!', 'wp-llms-txt'); ?></strong></p>
            </div>
            
            <table class="widefat" style="margin-top: 15px;">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('File Size:', 'wp-llms-txt'); ?></strong></td>
                        <td><?php echo esc_html(size_format($file_size)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Last Updated:', 'wp-llms-txt'); ?></strong></td>
                        <td><?php echo esc_html(human_time_diff($file_modified, current_time('timestamp')) . ' ' . __('ago', 'wp-llms-txt')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Available URLs:', 'wp-llms-txt'); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank" class="button button-small">
                                <?php esc_html_e('View LLMS.txt', 'wp-llms-txt'); ?> ↗
                            </a>
                            <?php if (class_exists('RankMath') || (defined('WPSEO_VERSION') && class_exists('WPSEO_Sitemaps'))): ?>
                                <a href="<?php echo esc_url(home_url('/llms-sitemap.xml')); ?>" target="_blank" class="button button-small">
                                    <?php esc_html_e('View Sitemap', 'wp-llms-txt'); ?> ↗
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <div style="background: #fcf0f1; border-left: 4px solid #d63638; padding: 12px; margin: 12px 0;">
                <p style="margin: 0;"><strong>❌ <?php esc_html_e('LLMS.txt file not found', 'wp-llms-txt'); ?></strong></p>
                <p style="margin: 5px 0 0 0; color: #666;"><?php esc_html_e('Click "Clear Caches" below to generate the file.', 'wp-llms-txt'); ?></p>
            </div>
        <?php endif; ?>
    </div>

   <div class="card">
        <h2><?php esc_html_e('Content Settings', 'wp-llms-txt'); ?></h2>
        <form method="post" action="options.php" id="llms-settings-form">
            <?php
            settings_fields('llms_generator_settings');
            $settings = get_option('llms_generator_settings', array(
                'post_types' => array('page', 'documentation', 'post'),
                'max_posts' => 100,
                'include_meta' => true,
                'include_excerpts' => true,
                'include_taxonomies' => true,
                'update_frequency' => 'immediate'
            ));
            ?>
            
            <h3><?php esc_html_e('Post Types', 'wp-llms-txt'); ?></h3>
            <p class="description"><?php esc_html_e('Select and order the post types to include in your llms.txt file. Drag to reorder.', 'wp-llms-txt'); ?></p>
            
            <div id="llms-post-types-sortable" class="sortable-list">
                <?php
                $post_types = get_post_types(array('public' => true), 'objects');
                $ordered_types = array_flip($settings['post_types']); // Create lookup array
                $unordered_types = array(); // For types not in the current order

                // Separate ordered and unordered post types
                foreach ($post_types as $post_type) {
                    if (in_array($post_type->name, array('attachment', 'llms_txt'))) {
                        continue;
                    }
                    
                    if (!isset($ordered_types[$post_type->name])) {
                        $unordered_types[] = $post_type;
                    }
                }
                
                // Output ordered items first
                foreach ($settings['post_types'] as $type_name) {
                    if (isset($post_types[$type_name])) {
                        $post_type = $post_types[$type_name];
                        ?>
                        <div class="sortable-item active" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                            <label>
                                <input type="checkbox" 
                                       name="llms_generator_settings[post_types][]" 
                                       value="<?php echo esc_attr($post_type->name); ?>"
                                       checked>
                                <span class="dashicons dashicons-menu"></span>
                                <?php echo esc_html($post_type->labels->name); ?>
                            </label>
                        </div>
                        <?php
                    }
                }
                
                // Output unordered items
                foreach ($unordered_types as $post_type) {
                    ?>
                    <div class="sortable-item" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                        <label>
                            <input type="checkbox" 
                                   name="llms_generator_settings[post_types][]" 
                                   value="<?php echo esc_attr($post_type->name); ?>">
                            <span class="dashicons dashicons-menu"></span>
                            <?php echo esc_html($post_type->labels->name); ?>
                        </label>
                    </div>
                    <?php
                }
                ?>
            </div>

            <h3><?php esc_html_e('Content Options', 'wp-llms-txt'); ?></h3>
            <p>
                <label>
                    <?php esc_html_e('Maximum posts per type:', 'wp-llms-txt'); ?>
                    <input type="number" 
                           name="llms_generator_settings[max_posts]" 
                           value="<?php echo esc_attr($settings['max_posts']); ?>"
                           min="1"
                           max="100000">
                </label>
            </p>

            <p>
                <label>
                    <?php esc_html_e('Maximum words:', 'wp-llms-txt'); ?>
                    <input type="number"
                           name="llms_generator_settings[max_words]"
                           value="<?php echo esc_attr($settings['max_words'] ?? 250); ?>"
                           min="1"
                           max="100000">
                </label>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" 
                           name="llms_generator_settings[include_meta]" 
                           value="1"
                           <?php checked(!empty($settings['include_meta'])); ?>>
                    <?php esc_html_e('Include meta information (publish date, author, etc.)', 'wp-llms-txt'); ?>
                </label>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" 
                           name="llms_generator_settings[include_excerpts]" 
                           value="1"
                           <?php checked(!empty($settings['include_excerpts'])); ?>>
                    <?php esc_html_e('Include post excerpts', 'wp-llms-txt'); ?>
                </label>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" 
                           name="llms_generator_settings[include_taxonomies]" 
                           value="1"
                           <?php checked(!empty($settings['include_taxonomies'])); ?>>
                    <?php esc_html_e('Include taxonomies (categories, tags, etc.)', 'wp-llms-txt'); ?>
                </label>
            </p>
            
            <h3><?php esc_html_e('Advanced Options', 'wp-llms-txt'); ?></h3>
            
            <p>
                <label>
                    <input type="checkbox" 
                           name="llms_generator_settings[include_custom_fields]" 
                           value="1"
                           <?php checked(!empty($settings['include_custom_fields'])); ?>>
                    <?php esc_html_e('Include custom fields', 'wp-llms-txt'); ?>
                </label>
                <br>
                <span class="description"><?php esc_html_e('Include publicly visible custom field data in the generated content.', 'wp-llms-txt'); ?></span>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" 
                           name="llms_generator_settings[exclude_private_taxonomies]" 
                           value="1"
                           <?php checked(!empty($settings['exclude_private_taxonomies'])); ?>>
                    <?php esc_html_e('Exclude private taxonomies', 'wp-llms-txt'); ?>
                </label>
                <br>
                <span class="description"><?php esc_html_e('Hide taxonomies marked as private from the generated content.', 'wp-llms-txt'); ?></span>
            </p>
            
            <div style="margin-top: 20px;">
                <label>
                    <strong><?php esc_html_e('Specific Taxonomies to Include:', 'wp-llms-txt'); ?></strong>
                </label>
                <div style="margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                    <?php
                    $taxonomies = get_taxonomies(array('public' => true), 'objects');
                    $selected_taxonomies = isset($settings['selected_taxonomies']) ? $settings['selected_taxonomies'] : array('category', 'post_tag');
                    
                    foreach ($taxonomies as $taxonomy) {
                        if ($taxonomy->name === 'post_format') continue;
                        ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" 
                                   name="llms_generator_settings[selected_taxonomies][]" 
                                   value="<?php echo esc_attr($taxonomy->name); ?>"
                                   <?php checked(in_array($taxonomy->name, $selected_taxonomies)); ?>>
                            <?php echo esc_html($taxonomy->labels->name); ?>
                            <span style="color: #666;">(<?php echo esc_html($taxonomy->name); ?>)</span>
                        </label>
                        <?php
                    }
                    ?>
                </div>
            </div>
            
            <p style="margin-top: 20px;">
                <label>
                    <?php esc_html_e('Custom fields to include (comma-separated):', 'wp-llms-txt'); ?>
                    <input type="text" 
                           name="llms_generator_settings[custom_field_keys]" 
                           value="<?php echo esc_attr(isset($settings['custom_field_keys']) ? $settings['custom_field_keys'] : ''); ?>"
                           style="width: 100%;"
                           placeholder="field_key1, field_key2, field_key3">
                </label>
                <span class="description"><?php esc_html_e('Enter the meta keys of custom fields you want to include.', 'wp-llms-txt'); ?></span>
            </p>

            <h3><?php esc_html_e('Update Frequency', 'wp-llms-txt'); ?></h3>
            <p>
                <label>
                    <select name="llms_generator_settings[update_frequency]">
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
                </label>
            </p>

            <?php submit_button(esc_html__('Save Settings', 'wp-llms-txt')); ?>
        </form>
    </div>

    <div class="card">
        <h2><?php esc_html_e('Cache Management', 'wp-llms-txt'); ?></h2>
        <p><?php esc_html_e('This tool helps ensure your LLMS.txt file is properly reflected in your sitemap by:', 'wp-llms-txt'); ?></p>
       	<ul class="llms-bullet-list">
            <li><?php esc_html_e('Clearing sitemap caches', 'wp-llms-txt'); ?></li>
            <li><?php esc_html_e('Resetting WordPress rewrite rules', 'wp-llms-txt'); ?></li>
            <li><?php esc_html_e('Forcing sitemap regeneration', 'wp-llms-txt'); ?></li>
        </ul>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="clear_caches">
            <?php wp_nonce_field('clear_caches', 'clear_caches_nonce'); ?>
            <p class="submit">
                <?php submit_button(esc_html__('Clear Caches', 'wp-llms-txt'), 'primary', 'submit', false); ?>
            </p>
        </form>
    </div>
    
    <?php
    // Display error logs if any exist
    $errors = get_transient('llms_generation_errors');
    if ($errors && is_array($errors) && !empty($errors)):
    ?>
    <div class="card">
        <h2><?php esc_html_e('🔍 Error Log', 'wp-llms-txt'); ?></h2>
        <p class="description"><?php esc_html_e('Recent errors encountered during file generation:', 'wp-llms-txt'); ?></p>
        
        <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;">
            <?php foreach (array_reverse($errors) as $error): ?>
                <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0;">
                    <strong style="color: #d63638;">[<?php echo esc_html($error['time']); ?>]</strong><br>
                    <?php echo esc_html($error['message']); ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 15px;">
            <input type="hidden" name="action" value="clear_error_log">
            <?php wp_nonce_field('clear_error_log', 'clear_error_log_nonce'); ?>
            <p class="submit">
                <input type="submit" class="button" value="<?php esc_attr_e('Clear Error Log', 'wp-llms-txt'); ?>">
            </p>
        </form>
    </div>
    <?php endif; ?>
    
    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
    <div class="card">
        <h2><?php esc_html_e('🐛 Debug Information', 'wp-llms-txt'); ?></h2>
        <table class="widefat">
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
                            '<span style="color: green;">✓ ' . esc_html__('Yes', 'wp-llms-txt') . '</span>' : 
                            '<span style="color: red;">✗ ' . esc_html__('No', 'wp-llms-txt') . '</span>'; 
                    ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>