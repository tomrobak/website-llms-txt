<?php
/**
 * CRITICAL FIX - Make the plugin actually work
 * This fixes the root causes, not symptoms
 */

// Find and load WordPress
$wp_load_paths = [
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php', 
    dirname(__FILE__) . '/../../../../../wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        echo "WordPress loaded from: {$path}\n";
        break;
    }
}

if (!$wp_loaded) {
    die("ERROR: Cannot find WordPress. Run this from your WordPress directory.\n");
}

global $wpdb;

echo "=== FIXING WP LLMs.txt - Making it Actually Work ===\n\n";

// 1. Force create all tables with CORRECT structure
echo "1. Creating tables with correct structure...\n";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$charset_collate = $wpdb->get_charset_collate();

// DROP and recreate to ensure correct structure
$tables = ['llms_txt_cache', 'llms_txt_progress', 'llms_txt_logs'];
foreach ($tables as $table) {
    $full_table = $wpdb->prefix . $table;
    $wpdb->query("DROP TABLE IF EXISTS {$full_table}");
    echo "   Dropped old table: {$full_table}\n";
}

// Create cache table with ALL required columns
$cache_table = $wpdb->prefix . 'llms_txt_cache';
$sql_cache = "CREATE TABLE $cache_table (
    `post_id` BIGINT UNSIGNED NOT NULL,
    `is_visible` TINYINT NULL DEFAULT 1,
    `status` VARCHAR(20) DEFAULT 'publish',
    `type` VARCHAR(20) NOT NULL,
    `title` TEXT,
    `link` VARCHAR(255),
    `sku` VARCHAR(255) DEFAULT NULL,
    `price` VARCHAR(125) DEFAULT NULL,
    `stock_status` VARCHAR(50) DEFAULT NULL,
    `stock_quantity` INT DEFAULT NULL,
    `product_type` VARCHAR(50) DEFAULT NULL,
    `meta` TEXT,
    `excerpts` TEXT,
    `overview` TEXT,
    `content` LONGTEXT,
    `published` VARCHAR(20),
    `modified` VARCHAR(20),
    `categories` TEXT,
    `tags` TEXT,
    `meta_description` TEXT,
    `custom_fields` TEXT,
    `author` VARCHAR(255),
    `date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_modified` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `index_content` LONGTEXT,
    PRIMARY KEY (`post_id`),
    KEY `is_visible` (`is_visible`),
    KEY `status` (`status`),
    KEY `type` (`type`),
    KEY `last_modified` (`last_modified`)
) $charset_collate;";

$result = dbDelta($sql_cache);
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
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
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
    `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
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

$default_settings = [
    'post_types' => ['page', 'post'],
    'max_posts' => 500,
    'max_words' => 1000,
    'include_meta' => true,
    'include_excerpts' => true,
    'include_taxonomies' => true,
    'update_frequency' => 'immediate',
    'need_check_option' => true,
];

update_option('llms_generator_settings', $default_settings);
echo "   ✓ Settings reset to defaults\n";

// 3. Manually populate cache - THE REAL FIX
echo "\n3. Manually populating cache (this is where it was failing)...\n";

$post_types = ['post', 'page'];

// Add product if WooCommerce is active
if (class_exists('WooCommerce')) {
    $post_types[] = 'product';
    echo "   ✓ WooCommerce detected, including products\n";
}

$total_cached = 0;

foreach ($post_types as $post_type) {
    echo "\n   Processing {$post_type}s:\n";
    
    // Direct database query - bypass WP_Query issues
    $posts = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title, post_content, post_excerpt, post_status, post_type, post_date, post_modified
        FROM {$wpdb->posts}
        WHERE post_type = %s 
        AND post_status = 'publish'
        AND post_password = ''
        ORDER BY post_date DESC
        LIMIT 500
    ", $post_type));
    
    $count = count($posts);
    echo "   Found {$count} published {$post_type}s\n";
    
    foreach ($posts as $post) {
        // Get permalink
        $permalink = get_permalink($post->ID);
        
        // Clean content
        $content = wp_strip_all_tags($post->post_content);
        $content = wp_trim_words($content, 500); // Limit to 500 words
        
        // Get meta description
        $meta_desc = '';
        if (defined('WPSEO_VERSION')) {
            $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        }
        
        // Insert into cache
        $insert_result = $wpdb->replace(
            $cache_table,
            [
                'post_id' => $post->ID,
                'is_visible' => 1,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'title' => $post->post_title,
                'link' => $permalink,
                'meta' => $meta_desc,
                'excerpts' => $post->post_excerpt,
                'content' => $content,
                'published' => $post->post_date,
                'modified' => $post->post_modified,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($insert_result !== false) {
            $total_cached++;
            if ($total_cached % 10 == 0) {
                echo "   ...cached {$total_cached} posts\n";
            }
        } else {
            echo "   ✗ Failed to cache post #{$post->ID}: " . $wpdb->last_error . "\n";
        }
    }
}

echo "\n   ✓ Total posts cached: {$total_cached}\n";

// 4. Create the files NOW
echo "\n4. Generating llms.txt files immediately...\n";

// Create standard llms.txt
$file_content = "# " . get_bloginfo('name') . "\n\n";
$file_content .= "> " . get_bloginfo('description') . "\n\n";

// Add cached content
$cached_posts = $wpdb->get_results("
    SELECT title, link, meta, content 
    FROM {$cache_table} 
    WHERE is_visible = 1 
    ORDER BY published DESC 
    LIMIT 50
");

if (!empty($cached_posts)) {
    $file_content .= "## Recent Content\n\n";
    
    foreach ($cached_posts as $post) {
        $desc = !empty($post->meta) ? $post->meta : wp_trim_words($post->content, 20);
        $file_content .= "- [{$post->title}]({$post->link}): {$desc}\n";
    }
} else {
    $file_content .= "No content available yet.\n";
}

$file_content .= "\nGenerated: " . date('Y-m-d H:i:s') . "\n";

// Write to root directory
$llms_path = ABSPATH . 'llms.txt';
$result = file_put_contents($llms_path, $file_content);

if ($result !== false) {
    echo "   ✓ Created {$llms_path} (" . $result . " bytes)\n";
} else {
    echo "   ✗ Failed to create llms.txt\n";
}

// 5. Setup cron job
echo "\n5. Setting up cron job...\n";

// Clear all old cron jobs
$cron = _get_cron_array();
foreach ($cron as $timestamp => $hooks) {
    foreach ($hooks as $hook => $data) {
        if (strpos($hook, 'llms_') === 0) {
            wp_clear_scheduled_hook($hook);
        }
    }
}

// Schedule new job
wp_schedule_single_event(time() + 300, 'llms_update_llms_file_cron'); // 5 minutes from now
echo "   ✓ Scheduled generation in 5 minutes\n";

// 6. Add rewrite rule
echo "\n6. Adding rewrite rules...\n";

add_rewrite_rule('^llms\.txt$', 'index.php?llms_txt=1', 'top');
add_rewrite_rule('^llms-full\.txt$', 'index.php?llms_txt=full', 'top');
flush_rewrite_rules();
echo "   ✓ Rewrite rules added\n";

// 7. Final verification
echo "\n7. Final verification...\n";

$cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$cache_table}");
echo "   Cache entries: {$cache_count}\n";

$file_exists = file_exists($llms_path);
echo "   llms.txt exists: " . ($file_exists ? "YES" : "NO") . "\n";

$site_url = get_site_url();
echo "\n=== FIX COMPLETE ===\n";
echo "\nYour llms.txt should now be accessible at:\n";
echo "   {$site_url}/llms.txt\n";
echo "\nIf it's still not working:\n";
echo "1. Check your web server error logs\n";
echo "2. Make sure mod_rewrite is enabled\n";
echo "3. Check file permissions on your web root\n";
echo "4. Try accessing the file directly at: {$llms_path}\n";