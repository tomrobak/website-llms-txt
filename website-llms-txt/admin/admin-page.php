<?php
if (!defined('ABSPATH')) {
    exit;
}

$llms_post = (new LLMS_Core())->get_llms_post();
$file_exists = file_exists(ABSPATH . 'llms.txt');
$is_auto_generated = $llms_post !== null;

// Verify cache cleared nonce and display message
if (isset($_GET['cache_cleared']) && $_GET['cache_cleared'] === 'true' && 
    isset($_GET['_wpnonce'])) {
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
    if (wp_verify_nonce($nonce, 'llms_cache_cleared')) {
        echo '<div class="notice notice-success"><p>' . esc_html__('Caches cleared successfully!', 'website-llms-txt') . '</p></div>';
    }
}

// Verify settings updated nonce and display message
if (isset($_GET['settings-updated']) && 
    isset($_GET['_wpnonce'])) {
    $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
    if (wp_verify_nonce($nonce, 'llms_options_update')) {
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'website-llms-txt') . '</p></div>';
    }
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Website llms.txt', 'website-llms-txt'); ?></h1>

    <div class="card">
        <h2><?php esc_html_e('File Status', 'website-llms-txt'); ?></h2>
        <?php if ($file_exists): ?>
            <p><?php esc_html_e('File is being auto-generated based on your settings.', 'website-llms-txt'); ?></p>
            <p><?php esc_html_e('View files:', 'website-llms-txt'); ?></p>
            <ul>
                <li><a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank"><?php echo esc_url(home_url('/llms.txt')); ?></a></li>
                <?php if (class_exists('RankMath') || (defined('WPSEO_VERSION') && class_exists('WPSEO_Sitemaps'))): ?>
                    <li><a href="<?php echo esc_url(home_url('/sitemap_index.xml')); ?>" target="_blank"><?php echo esc_url(home_url('/sitemap_index.xml')); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/llms-sitemap.xml')); ?>" target="_blank"><?php echo esc_url(home_url('/llms-sitemap.xml')); ?></a></li>
                <?php endif; ?>
            </ul>
        <?php else: ?>
            <p style="color: red;">✗ <?php esc_html_e('No LLMS.txt file found in root directory', 'website-llms-txt'); ?></p>
        <?php endif; ?>
    </div>

   <div class="card">
        <h2><?php esc_html_e('Content Settings', 'website-llms-txt'); ?></h2>
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
            
            <h3><?php esc_html_e('Post Types', 'website-llms-txt'); ?></h3>
            <p class="description"><?php esc_html_e('Select and order the post types to include in your llms.txt file. Drag to reorder.', 'website-llms-txt'); ?></p>
            
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

            <h3><?php esc_html_e('Content Options', 'website-llms-txt'); ?></h3>
            <p>
                <label>
                    <?php esc_html_e('Maximum posts per type:', 'website-llms-txt'); ?>
                    <input type="number" 
                           name="llms_generator_settings[max_posts]" 
                           value="<?php echo esc_attr($settings['max_posts']); ?>"
                           min="1"
                           max="1000">
                </label>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" 
                           name="llms_generator_settings[include_meta]" 
                           value="1"
                           <?php checked(!empty($settings['include_meta'])); ?>>
                    <?php esc_html_e('Include meta information (publish date, author, etc.)', 'website-llms-txt'); ?>
                </label>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" 
                           name="llms_generator_settings[include_excerpts]" 
                           value="1"
                           <?php checked(!empty($settings['include_excerpts'])); ?>>
                    <?php esc_html_e('Include post excerpts', 'website-llms-txt'); ?>
                </label>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" 
                           name="llms_generator_settings[include_taxonomies]" 
                           value="1"
                           <?php checked(!empty($settings['include_taxonomies'])); ?>>
                    <?php esc_html_e('Include taxonomies (categories, tags, etc.)', 'website-llms-txt'); ?>
                </label>
            </p>

            <h3><?php esc_html_e('Update Frequency', 'website-llms-txt'); ?></h3>
            <p>
                <label>
                    <select name="llms_generator_settings[update_frequency]">
                        <option value="immediate" <?php selected($settings['update_frequency'], 'immediate'); ?>>
                            <?php esc_html_e('Immediate', 'website-llms-txt'); ?>
                        </option>
                        <option value="daily" <?php selected($settings['update_frequency'], 'daily'); ?>>
                            <?php esc_html_e('Daily', 'website-llms-txt'); ?>
                        </option>
                        <option value="weekly" <?php selected($settings['update_frequency'], 'weekly'); ?>>
                            <?php esc_html_e('Weekly', 'website-llms-txt'); ?>
                        </option>
                    </select>
                </label>
            </p>

            <?php submit_button(esc_html__('Save Settings', 'website-llms-txt')); ?>
        </form>
    </div>

    <div class="card">
        <h2><?php esc_html_e('Cache Management', 'website-llms-txt'); ?></h2>
        <p><?php esc_html_e('This tool helps ensure your LLMS.txt file is properly reflected in your sitemap by:', 'website-llms-txt'); ?></p>
        <ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 15px;">
            <li><?php esc_html_e('Clearing sitemap caches', 'website-llms-txt'); ?></li>
            <li><?php esc_html_e('Resetting WordPress rewrite rules', 'website-llms-txt'); ?></li>
            <li><?php esc_html_e('Forcing sitemap regeneration', 'website-llms-txt'); ?></li>
        </ul>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="clear_caches">
            <?php wp_nonce_field('clear_caches', 'clear_caches_nonce'); ?>
            <p class="submit">
                <?php submit_button(esc_html__('Clear Caches', 'website-llms-txt'), 'primary', 'submit', false); ?>
            </p>
        </form>
    </div>
</div>

<style>
.sortable-list {
    max-width: 500px;
    margin: 20px 0;
}

.sortable-item {
    padding: 10px;
    background: #fff;
    border: 1px solid #ccd0d4;
    margin-bottom: 5px;
    cursor: move;
    display: flex;
    align-items: center;
}

.sortable-item.active {
    background: #f0f6fc;
}

.sortable-item label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: move;
    flex-grow: 1;
}

.sortable-item .dashicons-menu {
    color: #999;
}

.sortable-item input[type="checkbox"] {
    cursor: pointer;
}

.notice.inline {
    margin: 15px 0;
}
</style>

<script>
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
});
</script>