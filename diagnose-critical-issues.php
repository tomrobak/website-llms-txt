<?php
/**
 * Critical Diagnosis Script - Find why nothing works
 */

// Load WordPress
$wp_load_paths = [
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
    dirname(__FILE__) . '/../../../../../wp-load.php',
    '/var/www/wp-load.php',
    '/var/www/html/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("ERROR: Cannot find WordPress. Please run this from your WordPress directory.\n");
}

global $wpdb;

echo "=== CRITICAL DIAGNOSIS: WP LLMs.txt ===\n\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Site URL: " . get_site_url() . "\n";
echo "Plugin Directory: " . dirname(__FILE__) . "\n\n";

// 1. Check if plugin is active
echo "1. PLUGIN STATUS\n";
echo "================\n";

$active_plugins = get_option('active_plugins', []);
$plugin_file = 'website-llms-txt/website-llms-txt.php';
$is_active = in_array($plugin_file, $active_plugins);

echo "Plugin active: " . ($is_active ? "YES" : "NO") . "\n";

if (!$is_active) {
    // Try alternative paths
    $alternatives = [
        'website-llms-txt-2.1/website-llms-txt.php',
        'wp-llms-txt/website-llms-txt.php'
    ];
    
    foreach ($alternatives as $alt) {
        if (in_array($alt, $active_plugins)) {
            echo "Found active under: {$alt}\n";
            $is_active = true;
            break;
        }
    }
}

// 2. Check database tables
echo "\n2. DATABASE TABLES\n";
echo "==================\n";

$tables = [
    'llms_txt_cache',
    'llms_txt_progress', 
    'llms_txt_logs'
];

foreach ($tables as $table) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table}'");
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_table}");
        $structure = $wpdb->get_results("DESCRIBE {$full_table}");
        echo "✓ {$full_table}: EXISTS ({$count} rows)\n";
        
        // Show first few rows if any
        if ($count > 0 && $count < 10) {
            $rows = $wpdb->get_results("SELECT * FROM {$full_table} LIMIT 5");
            echo "  Sample data:\n";
            foreach ($rows as $row) {
                echo "  - " . json_encode($row) . "\n";
            }
        }
    } else {
        echo "✗ {$full_table}: MISSING!\n";
    }
}

// 3. Check plugin settings
echo "\n3. PLUGIN SETTINGS\n";
echo "==================\n";

$settings = get_option('llms_generator_settings', null);
if ($settings === null) {
    echo "✗ No settings found!\n";
} else {
    echo "Settings: " . json_encode($settings, JSON_PRETTY_PRINT) . "\n";
    
    // Validate post types
    if (isset($settings['post_types'])) {
        echo "\nChecking post types:\n";
        foreach ($settings['post_types'] as $post_type) {
            $count = wp_count_posts($post_type);
            $published = isset($count->publish) ? $count->publish : 0;
            echo "- {$post_type}: {$published} published posts\n";
        }
    }
}

// 4. Check WordPress posts directly
echo "\n4. WORDPRESS POSTS\n";
echo "==================\n";

$post_types = ['post', 'page', 'product'];
foreach ($post_types as $type) {
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
        $type
    ));
    echo "{$type}: {$count} published\n";
}

// 5. Check cron jobs
echo "\n5. CRON JOBS\n";
echo "============\n";

$cron = _get_cron_array();
$llms_crons = [];

foreach ($cron as $timestamp => $hooks) {
    foreach ($hooks as $hook => $data) {
        if (strpos($hook, 'llms_') === 0) {
            $llms_crons[] = [
                'hook' => $hook,
                'time' => date('Y-m-d H:i:s', $timestamp),
                'in' => human_time_diff(time(), $timestamp)
            ];
        }
    }
}

if (empty($llms_crons)) {
    echo "✗ No LLMS cron jobs scheduled!\n";
} else {
    foreach ($llms_crons as $job) {
        echo "- {$job['hook']} at {$job['time']} (in {$job['in']})\n";
    }
}

// 6. Test cache population directly
echo "\n6. TESTING CACHE POPULATION\n";
echo "===========================\n";

// Get one published post
$test_post = $wpdb->get_row("
    SELECT * FROM {$wpdb->posts} 
    WHERE post_type IN ('post', 'page') 
    AND post_status = 'publish' 
    LIMIT 1
");

if ($test_post) {
    echo "Found test post: #{$test_post->ID} - {$test_post->post_title}\n";
    
    // Try to manually add to cache
    $cache_table = $wpdb->prefix . 'llms_txt_cache';
    
    // Check if already in cache
    $in_cache = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$cache_table} WHERE post_id = %d",
        $test_post->ID
    ));
    
    if ($in_cache) {
        echo "✓ Post already in cache\n";
    } else {
        // Try to insert
        $result = $wpdb->insert(
            $cache_table,
            [
                'post_id' => $test_post->ID,
                'status' => 'publish',
                'type' => $test_post->post_type,
                'title' => $test_post->post_title,
                'link' => get_permalink($test_post->ID),
                'content' => substr($test_post->post_content, 0, 1000),
                'published' => $test_post->post_date,
                'modified' => $test_post->post_modified
            ]
        );
        
        if ($result === false) {
            echo "✗ Failed to insert into cache!\n";
            echo "Last error: " . $wpdb->last_error . "\n";
        } else {
            echo "✓ Successfully added to cache\n";
        }
    }
} else {
    echo "✗ No published posts found!\n";
}

// 7. Check file permissions
echo "\n7. FILE PERMISSIONS\n";
echo "===================\n";

$paths_to_check = [
    ABSPATH => 'WordPress root',
    wp_upload_dir()['basedir'] => 'Uploads directory',
    dirname(__FILE__) => 'Plugin directory'
];

foreach ($paths_to_check as $path => $name) {
    if (is_writable($path)) {
        echo "✓ {$name}: WRITABLE\n";
    } else {
        echo "✗ {$name}: NOT WRITABLE\n";
    }
}

// 8. Test generation process
echo "\n8. GENERATION PROCESS TEST\n";
echo "==========================\n";

// Check if generator class exists
if (!class_exists('LLMS_Generator')) {
    $generator_path = dirname(__FILE__) . '/includes/class-llms-generator.php';
    if (file_exists($generator_path)) {
        require_once $generator_path;
        echo "✓ Generator class loaded\n";
    } else {
        echo "✗ Generator class file not found!\n";
    }
}

// Check required functions
$required_functions = [
    'llms_get_logger',
    'wp_schedule_single_event',
    'get_permalink',
    'get_post_meta'
];

foreach ($required_functions as $func) {
    if (function_exists($func)) {
        echo "✓ Function {$func}() exists\n";
    } else {
        echo "✗ Function {$func}() missing!\n";
    }
}

// 9. Check transients
echo "\n9. TRANSIENTS\n";
echo "=============\n";

$transients = [
    'llms_current_progress_id',
    'llms_generation_errors',
    'llms_txt_content_*'
];

foreach ($transients as $transient) {
    if (strpos($transient, '*') !== false) {
        // Pattern search
        $pattern = str_replace('*', '%', $transient);
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $pattern
        ));
        echo "{$transient}: {$count} found\n";
    } else {
        $value = get_transient($transient);
        if ($value !== false) {
            echo "{$transient}: " . json_encode($value) . "\n";
        } else {
            echo "{$transient}: NOT SET\n";
        }
    }
}

// 10. Final recommendations
echo "\n10. DIAGNOSIS SUMMARY\n";
echo "=====================\n";

$issues = [];

// Check each critical component
if (!$is_active) {
    $issues[] = "Plugin is not active!";
}

$cache_table = $wpdb->prefix . 'llms_txt_cache';
if (!$wpdb->get_var("SHOW TABLES LIKE '{$cache_table}'")) {
    $issues[] = "Cache table does not exist!";
}

$cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$cache_table}");
if ($cache_count == 0) {
    $issues[] = "Cache is completely empty!";
}

if (empty($llms_crons)) {
    $issues[] = "No cron jobs scheduled!";
}

if (empty($issues)) {
    echo "✓ No critical issues found\n";
} else {
    echo "CRITICAL ISSUES FOUND:\n";
    foreach ($issues as $issue) {
        echo "❌ {$issue}\n";
    }
    
    echo "\nRECOMMENDED ACTIONS:\n";
    echo "1. Run: php fix-all-issues.php\n";
    echo "2. Deactivate and reactivate the plugin\n";
    echo "3. Check PHP error logs for fatal errors\n";
    echo "4. Ensure WordPress cron is enabled (define('DISABLE_WP_CRON', false))\n";
}

echo "\n=== END DIAGNOSIS ===\n";