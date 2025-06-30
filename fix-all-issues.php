<?php
/**
 * Comprehensive fix for all WP LLMs.txt issues
 */

// Load WordPress
$wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("WordPress not found. Please adjust the path.\n");
}
require_once $wp_load_path;

// Include required classes
require_once dirname(__FILE__) . '/includes/class-llms-generator.php';
require_once dirname(__FILE__) . '/includes/class-llms-logger.php';
require_once dirname(__FILE__) . '/includes/class-llms-content-cleaner.php';

global $wpdb;

echo "=== Comprehensive Fix for WP LLMs.txt ===\n\n";

// 1. Ensure all tables exist
echo "1. Creating all required tables...\n";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$charset_collate = $wpdb->get_charset_collate();

// Create cache table
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
    `published` DATETIME DEFAULT NULL,
    `modified` DATETIME DEFAULT NULL,
    PRIMARY KEY (`post_id`),
    KEY `is_visible` (`is_visible`),
    KEY `status` (`status`),
    KEY `type` (`type`),
    KEY `last_modified` (`last_modified`)
) $charset_collate;";

dbDelta($sql_cache);
echo "   ✓ Cache table created\n";

// Create progress table
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
echo "   ✓ Progress table created\n";

// Create logs table
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
echo "   ✓ Logs table created\n";

// 2. Fix settings
echo "\n2. Fixing settings...\n";

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

// Clean up post types
if (!is_array($settings['post_types'])) {
    $settings['post_types'] = ['page', 'post'];
}

$settings['post_types'] = array_filter($settings['post_types'], function($type) {
    return $type !== 'llms_txt' && post_type_exists($type);
});

if (empty($settings['post_types'])) {
    $settings['post_types'] = ['page', 'post'];
}

update_option('llms_generator_settings', $settings);
echo "   ✓ Settings fixed: " . implode(', ', $settings['post_types']) . "\n";

// 3. Clear old data
echo "\n3. Clearing old data...\n";

// Clear transients
delete_transient('llms_current_progress_id');
delete_transient('llms_generation_errors');
echo "   ✓ Transients cleared\n";

// Clear stale progress
$wpdb->query("UPDATE {$progress_table} SET status = 'cancelled' WHERE status IN ('running', 'starting')");
echo "   ✓ Stale progress cleared\n";

// 4. Populate cache
echo "\n4. Populating cache...\n";

$generator = new LLMS_Generator();

foreach ($settings['post_types'] as $post_type) {
    echo "   Processing {$post_type}...\n";
    
    $posts = get_posts([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'suppress_filters' => false
    ]);
    
    $total = count($posts);
    echo "   Found {$total} published {$post_type}(s)\n";
    
    $processed = 0;
    foreach ($posts as $post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish') {
            // Force update cache entry
            $generator->handle_post_update($post_id, $post, false, 'populate');
            $processed++;
            
            if ($processed % 10 === 0) {
                echo "   ...processed {$processed}/{$total}\n";
            }
        }
    }
    
    echo "   ✓ Processed {$processed} {$post_type}(s)\n";
}

$final_count = $wpdb->get_var("SELECT COUNT(*) FROM {$cache_table}");
echo "   ✓ Total posts in cache: {$final_count}\n";

// 5. Create directory structure
echo "\n5. Creating directory structure...\n";

$upload_dir = wp_upload_dir();
$llms_dir = $upload_dir['basedir'] . '/llms-txt';

if (!file_exists($llms_dir)) {
    wp_mkdir_p($llms_dir);
    echo "   ✓ Created directory: {$llms_dir}\n";
} else {
    echo "   ✓ Directory exists: {$llms_dir}\n";
}

// 6. Generate files manually
echo "\n6. Generating llms.txt files...\n";

// Create a progress ID for manual generation
$progress_id = 'manual_' . uniqid();
set_transient('llms_current_progress_id', $progress_id, HOUR_IN_SECONDS);

// Insert progress record
$wpdb->insert(
    $progress_table,
    [
        'id' => $progress_id,
        'status' => 'running',
        'total_items' => $final_count * 2,
        'current_item' => 0,
        'started_at' => current_time('mysql'),
        'message' => 'Manual generation started'
    ]
);

// Generate standard file
echo "   Generating standard llms.txt...\n";
$generator->update_llms_file();

// Check if files were created
$standard_path = ABSPATH . 'llms.txt';
$full_path = ABSPATH . 'llms-full.txt';

if (file_exists($standard_path)) {
    $size = filesize($standard_path);
    echo "   ✓ Standard file created: {$standard_path} ({$size} bytes)\n";
} else {
    echo "   ✗ Failed to create standard file\n";
}

if (file_exists($full_path)) {
    $size = filesize($full_path);
    echo "   ✓ Full file created: {$full_path} ({$size} bytes)\n";
} else {
    echo "   ✗ Failed to create full file\n";
}

// 7. Update rewrite rules
echo "\n7. Updating rewrite rules...\n";

// Flush rewrite rules
flush_rewrite_rules();
echo "   ✓ Rewrite rules flushed\n";

// 8. Final checks
echo "\n8. Final checks...\n";

$site_url = get_site_url();
echo "   Your llms.txt should be accessible at:\n";
echo "   - {$site_url}/llms.txt (standard)\n";
echo "   - {$site_url}/llms-full.txt (comprehensive)\n";

// Check recent logs
$recent_logs = $wpdb->get_results(
    "SELECT * FROM {$logs_table} ORDER BY timestamp DESC LIMIT 5"
);

if (!empty($recent_logs)) {
    echo "\n   Recent log entries:\n";
    foreach ($recent_logs as $log) {
        echo "   [{$log->level}] {$log->message}\n";
    }
}

echo "\n=== Fix Complete ===\n";
echo "\nIf files are still not accessible:\n";
echo "1. Check file permissions on your web root\n";
echo "2. Visit Settings > Permalinks and click 'Save Changes'\n";
echo "3. Check your web server error logs\n";
echo "4. Make sure mod_rewrite is enabled (Apache) or URL rewriting is configured (Nginx)\n";