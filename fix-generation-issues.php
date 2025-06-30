<?php
/**
 * Fix generation issues in WP LLMs.txt plugin
 */

// Load WordPress
$wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("WordPress not found. Please adjust the path.\n");
}
require_once $wp_load_path;

global $wpdb;

echo "=== Fixing WP LLMs.txt Generation Issues ===\n\n";

// 1. Create all required tables
echo "1. Creating missing tables...\n";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$charset_collate = $wpdb->get_charset_collate();

// Cache table
$cache_table = $wpdb->prefix . 'llms_txt_cache';
$sql_cache = "CREATE TABLE $cache_table (
    `post_id` BIGINT UNSIGNED NOT NULL,
    `is_visible` TINYINT NULL DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT NULL,
    `type` VARCHAR(20) DEFAULT NULL,
    `title` TEXT DEFAULT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `sku` VARCHAR(255) DEFAULT NULL,
    `price` VARCHAR(125) DEFAULT NULL,
    `stock_status` VARCHAR(50) DEFAULT NULL,
    `stock_quantity` INT DEFAULT NULL,
    `product_type` VARCHAR(50) DEFAULT NULL,
    `excerpts` TEXT DEFAULT NULL,
    `content` LONGTEXT DEFAULT NULL,
    `categories` TEXT DEFAULT NULL,
    `tags` TEXT DEFAULT NULL,
    `meta_description` TEXT DEFAULT NULL,
    `custom_fields` TEXT DEFAULT NULL,
    `meta` TEXT DEFAULT NULL,
    `author` VARCHAR(255) DEFAULT NULL,
    `date` DATETIME DEFAULT NULL,
    `last_modified` DATETIME DEFAULT NULL,
    `index_content` LONGTEXT DEFAULT NULL,
    PRIMARY KEY (`post_id`),
    KEY `is_visible` (`is_visible`),
    KEY `status` (`status`),
    KEY `type` (`type`),
    KEY `last_modified` (`last_modified`)
) $charset_collate;";

dbDelta($sql_cache);
echo "   - Cache table created/updated\n";

// Progress table
$progress_table = $wpdb->prefix . 'llms_txt_progress';
$sql_progress = "CREATE TABLE $progress_table (
    `id` VARCHAR(32) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'pending',
    `current_item` INT DEFAULT 0,
    `total_items` INT DEFAULT 0,
    `message` TEXT,
    `error` TEXT,
    `started_at` DATETIME,
    `completed_at` DATETIME,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `status` (`status`),
    KEY `updated_at` (`updated_at`)
) $charset_collate;";

dbDelta($sql_progress);
echo "   - Progress table created/updated\n";

// Logs table
$logs_table = $wpdb->prefix . 'llms_txt_logs';
$sql_logs = "CREATE TABLE $logs_table (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `timestamp` DATETIME DEFAULT NULL,
    `level` VARCHAR(10) DEFAULT 'INFO',
    `message` TEXT,
    `context` TEXT,
    `post_id` BIGINT UNSIGNED DEFAULT NULL,
    `memory_usage` BIGINT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `timestamp` (`timestamp`),
    KEY `level` (`level`),
    KEY `post_id` (`post_id`)
) $charset_collate;";

dbDelta($sql_logs);
echo "   - Logs table created/updated\n";

// 2. Fix settings if needed
echo "\n2. Checking and fixing settings...\n";

$settings = get_option('llms_generator_settings', []);
$defaults = [
    'post_types' => ['page', 'post'],
    'max_posts' => 500,
    'max_words' => 1000,
    'include_meta' => true,
    'include_excerpts' => true,
    'include_taxonomies' => true,
    'update_frequency' => 'immediate',
    'need_check_option' => true,
];

$settings = wp_parse_args($settings, $defaults);

// Ensure post_types is an array
if (!is_array($settings['post_types'])) {
    $settings['post_types'] = ['page', 'post'];
}

// Remove llms_txt from post types if present
$settings['post_types'] = array_filter($settings['post_types'], function($type) {
    return $type !== 'llms_txt';
});

// If no post types, set defaults
if (empty($settings['post_types'])) {
    $settings['post_types'] = ['page', 'post'];
}

update_option('llms_generator_settings', $settings);
echo "   - Settings updated: " . json_encode($settings['post_types']) . "\n";

// 3. Clear stale progress
echo "\n3. Clearing stale progress...\n";

delete_transient('llms_current_progress_id');
$wpdb->query("UPDATE {$progress_table} SET status = 'cancelled' WHERE status IN ('running', 'starting')");
echo "   - Cleared stale progress\n";

// 4. Populate cache if empty
echo "\n4. Checking cache population...\n";

$cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$cache_table}");
echo "   - Current cache count: {$cache_count}\n";

if ($cache_count == 0) {
    echo "   - Cache is empty, populating...\n";
    
    foreach ($settings['post_types'] as $post_type) {
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'suppress_filters' => false,
            'fields' => 'ids'
        ]);
        
        $count = count($posts);
        echo "   - Found {$count} {$post_type} posts\n";
        
        // Load generator class
        if (!class_exists('LLMS_Generator')) {
            require_once dirname(__FILE__) . '/includes/class-llms-generator.php';
        }
        
        $generator = new LLMS_Generator();
        
        $processed = 0;
        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            if ($post && $post->post_status === 'publish') {
                // Call the update method
                $generator->handle_post_update($post_id, $post, false, 'populate');
                $processed++;
                
                if ($processed % 10 == 0) {
                    echo "     Processed {$processed}/{$count} {$post_type} posts...\n";
                }
            }
        }
        
        echo "   - Populated {$processed} {$post_type} posts\n";
    }
    
    $new_count = $wpdb->get_var("SELECT COUNT(*) FROM {$cache_table}");
    echo "   - New cache count: {$new_count}\n";
}

// 5. Create uploads directory structure
echo "\n5. Creating directory structure...\n";

$upload_dir = wp_upload_dir();
$llms_dir = $upload_dir['basedir'] . '/llms-txt';

if (!file_exists($llms_dir)) {
    wp_mkdir_p($llms_dir);
    echo "   - Created directory: {$llms_dir}\n";
} else {
    echo "   - Directory exists: {$llms_dir}\n";
}

// 6. Generate files immediately
echo "\n6. Generating llms.txt files...\n";

// Load generator if not already loaded
if (!class_exists('LLMS_Generator')) {
    require_once dirname(__FILE__) . '/includes/class-llms-generator.php';
}

$generator = new LLMS_Generator();

// Generate standard file
echo "   - Generating standard llms.txt...\n";
$result = $generator->update_llms_file();
$standard_path = $generator->get_llms_file_path('standard');
if (file_exists($standard_path)) {
    echo "   - Standard file created: " . $standard_path . " (" . filesize($standard_path) . " bytes)\n";
} else {
    echo "   - Failed to create standard file\n";
}

// Generate full file
echo "   - Generating full llms-full.txt...\n";
$result_full = $generator->update_llms_file('full');
$full_path = $generator->get_llms_file_path('full');
if (file_exists($full_path)) {
    echo "   - Full file created: " . $full_path . " (" . filesize($full_path) . " bytes)\n";
} else {
    echo "   - Failed to create full file\n";
}

// 7. Fix cron schedules
echo "\n7. Fixing cron schedules...\n";

// Clear all LLMS scheduled actions
$cron = _get_cron_array();
foreach ($cron as $timestamp => $hooks) {
    foreach ($hooks as $hook => $dings) {
        if (strpos($hook, 'llms_') === 0) {
            wp_clear_scheduled_hook($hook);
            echo "   - Cleared scheduled hook: {$hook}\n";
        }
    }
}

// Schedule immediate update
wp_schedule_single_event(time() + 5, 'llms_update_llms_file_cron');
echo "   - Scheduled immediate update\n";

// 8. Test file serving
echo "\n8. Testing file serving...\n";

$site_url = get_site_url();
$llms_url = $site_url . '/llms.txt';
echo "   - File should be accessible at: {$llms_url}\n";

// Check .htaccess
$htaccess_path = ABSPATH . '.htaccess';
if (file_exists($htaccess_path)) {
    $htaccess = file_get_contents($htaccess_path);
    if (strpos($htaccess, 'llms\.txt') !== false) {
        echo "   - Rewrite rule found in .htaccess\n";
    } else {
        echo "   - WARNING: No rewrite rule found in .htaccess\n";
        echo "   - You may need to flush permalinks in Settings > Permalinks\n";
    }
}

echo "\n=== Fix Complete ===\n";
echo "Please check:\n";
echo "1. {$llms_url} - Should show your llms.txt file\n";
echo "2. Admin panel > WP LLMs.txt - Should show generation status\n";
echo "3. If issues persist, check error logs and run this script again\n";