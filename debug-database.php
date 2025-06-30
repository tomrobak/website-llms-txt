<?php
/**
 * Debug script to check database tables and post counts
 */

// Load WordPress
$wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("WordPress not found. Please adjust the path.\n");
}
require_once $wp_load_path;

global $wpdb;

echo "=== WP LLMs.txt Database Debug ===\n\n";

// 1. Check if tables exist
echo "1. Checking database tables...\n";

$cache_table = $wpdb->prefix . 'llms_txt_cache';
$progress_table = $wpdb->prefix . 'llms_progress'; 
$logs_table = $wpdb->prefix . 'llms_logs';

$tables = [
    'cache' => $cache_table,
    'progress' => $progress_table,
    'logs' => $logs_table
];

foreach ($tables as $name => $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    echo "   - {$name} table ({$table}): " . ($exists ? "EXISTS" : "MISSING") . "\n";
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo "     Records: {$count}\n";
    }
}

// 2. Check post counts
echo "\n2. Checking post counts...\n";

$post_types = get_option('llms_generator_settings', ['post_types' => ['post', 'page']]);
$selected_types = $post_types['post_types'] ?? ['post', 'page'];

echo "   Selected post types: " . implode(', ', $selected_types) . "\n";

foreach ($selected_types as $post_type) {
    $args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ];
    
    $query = new WP_Query($args);
    $count = $query->found_posts;
    
    echo "   - {$post_type}: {$count} published posts\n";
}

// 3. Check if posts are visible to AI
echo "\n3. Checking SEO visibility...\n";

$sample_posts = get_posts([
    'post_type' => $selected_types,
    'post_status' => 'publish',
    'posts_per_page' => 5
]);

foreach ($sample_posts as $post) {
    $is_visible = true;
    
    // Check Yoast SEO
    if (defined('WPSEO_VERSION')) {
        $yoast_noindex = get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true);
        if ($yoast_noindex == '1') {
            $is_visible = false;
        }
    }
    
    // Check RankMath
    if (function_exists('rank_math')) {
        $robots = get_post_meta($post->ID, 'rank_math_robots', true);
        if (is_array($robots) && in_array('noindex', $robots)) {
            $is_visible = false;
        }
    }
    
    echo "   - Post #{$post->ID} '{$post->post_title}': " . ($is_visible ? "VISIBLE" : "HIDDEN") . "\n";
}

// 4. Check current progress
echo "\n4. Checking current progress...\n";

$progress_id = get_transient('llms_current_progress_id');
echo "   Current progress ID: " . ($progress_id ?: "NONE") . "\n";

if ($progress_id && $exists) {
    $progress = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$progress_table} WHERE id = %s",
        $progress_id
    ));
    
    if ($progress) {
        echo "   Status: {$progress->status}\n";
        echo "   Current: {$progress->current_item}/{$progress->total_items}\n";
        echo "   Updated: {$progress->updated_at}\n";
    }
}

// 5. Check file generation settings
echo "\n5. Checking file generation...\n";

$upload_dir = wp_upload_dir();
$llms_dir = $upload_dir['basedir'] . '/llms-txt';
$llms_path = $llms_dir . '/llms.txt';
$llms_full_path = $llms_dir . '/llms-full.txt';

echo "   Upload directory: " . $upload_dir['basedir'] . "\n";
echo "   LLMS directory: " . (is_dir($llms_dir) ? "EXISTS" : "MISSING") . "\n";
echo "   llms.txt: " . (file_exists($llms_path) ? "EXISTS (" . filesize($llms_path) . " bytes)" : "MISSING") . "\n";
echo "   llms-full.txt: " . (file_exists($llms_full_path) ? "EXISTS (" . filesize($llms_full_path) . " bytes)" : "MISSING") . "\n";

// 6. Check scheduled actions
echo "\n6. Checking scheduled actions...\n";

$scheduled = _get_cron_array();
$llms_actions = [];

foreach ($scheduled as $timestamp => $cron) {
    foreach ($cron as $hook => $dings) {
        if (strpos($hook, 'llms_') === 0) {
            $llms_actions[] = [
                'hook' => $hook,
                'time' => date('Y-m-d H:i:s', $timestamp),
                'timestamp' => $timestamp
            ];
        }
    }
}

if (empty($llms_actions)) {
    echo "   No LLMS scheduled actions found\n";
} else {
    foreach ($llms_actions as $action) {
        echo "   - {$action['hook']} scheduled for {$action['time']}\n";
    }
}

// 7. Test cache population
echo "\n7. Testing cache population...\n";

// Get a sample post
$test_post = get_posts([
    'post_type' => $selected_types,
    'post_status' => 'publish',
    'posts_per_page' => 1
]);

if (!empty($test_post)) {
    $post = $test_post[0];
    echo "   Testing with post #{$post->ID} '{$post->post_title}'\n";
    
    // Try to populate cache for this post
    if (class_exists('LLMS_Generator')) {
        $generator = new LLMS_Generator();
        
        // Check if method exists
        if (method_exists($generator, 'update_post_cache')) {
            $result = $generator->update_post_cache($post->ID, $post);
            echo "   Cache update result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        } else {
            echo "   ERROR: update_post_cache method not found\n";
        }
    } else {
        echo "   ERROR: LLMS_Generator class not found\n";
    }
}

echo "\n=== End Debug ===\n";