<?php
/**
 * Debug and fix script for LLMS.txt generation issues
 * 
 * This file helps diagnose and fix common issues with the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class LLMS_Debug_Fix {
    
    public static function run_diagnostics() {
        global $wpdb;
        
        $issues = [];
        $fixes = [];
        
        // Check if tables exist
        $tables = [
            'llms_txt_cache' => $wpdb->prefix . 'llms_txt_cache',
            'llms_txt_logs' => $wpdb->prefix . 'llms_txt_logs',
            'llms_txt_progress' => $wpdb->prefix . 'llms_txt_progress'
        ];
        
        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$exists) {
                $issues[] = "Table {$table} does not exist";
                $fixes[] = "create_table_{$name}";
            }
        }
        
        // Check cache status
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tables['llms_txt_cache']))) {
            $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['llms_txt_cache']}");
            if ($cache_count == 0) {
                $issues[] = "Cache table is empty";
                $fixes[] = "populate_cache";
            }
        }
        
        // Check settings
        $settings = get_option('llms_generator_settings');
        if (!$settings || empty($settings['post_types'])) {
            $issues[] = "No post types configured";
            $fixes[] = "fix_settings";
        }
        
        return ['issues' => $issues, 'fixes' => $fixes];
    }
    
    public static function apply_fixes($fixes) {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $results = [];
        
        foreach ($fixes as $fix) {
            switch ($fix) {
                case 'create_table_llms_txt_cache':
                    $table = $wpdb->prefix . 'llms_txt_cache';
                    $charset_collate = $wpdb->get_charset_collate();
                    $sql = "CREATE TABLE $table (
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
                        `overview` TEXT DEFAULT NULL,
                        `meta` TEXT DEFAULT NULL,
                        `content` LONGTEXT DEFAULT NULL,
                        `published` DATETIME DEFAULT NULL,
                        `modified` DATETIME DEFAULT NULL,
                        PRIMARY KEY (post_id),
                        KEY idx_type_visible_status (type, is_visible, status),
                        KEY idx_published (published)
                    ) $charset_collate;";
                    dbDelta($sql);
                    $results[] = "Cache table created";
                    break;
                    
                case 'create_table_llms_txt_logs':
                    $table = $wpdb->prefix . 'llms_txt_logs';
                    $charset_collate = $wpdb->get_charset_collate();
                    $sql = "CREATE TABLE $table (
                        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `timestamp` DATETIME DEFAULT NULL,
                        `level` VARCHAR(10) DEFAULT 'INFO',
                        `message` TEXT,
                        `context` TEXT,
                        `post_id` BIGINT UNSIGNED DEFAULT NULL,
                        `memory_usage` BIGINT DEFAULT NULL,
                        `execution_time` FLOAT DEFAULT NULL,
                        PRIMARY KEY (id),
                        KEY idx_timestamp (timestamp),
                        KEY idx_level (level)
                    ) $charset_collate;";
                    dbDelta($sql);
                    $results[] = "Logs table created";
                    break;
                    
                case 'create_table_llms_txt_progress':
                    $table = $wpdb->prefix . 'llms_txt_progress';
                    $charset_collate = $wpdb->get_charset_collate();
                    $sql = "CREATE TABLE $table (
                        `id` VARCHAR(50) NOT NULL,
                        `status` VARCHAR(20) DEFAULT 'running',
                        `current_item` INT DEFAULT 0,
                        `total_items` INT DEFAULT 0,
                        `current_post_id` BIGINT UNSIGNED DEFAULT NULL,
                        `current_post_title` TEXT,
                        `started_at` DATETIME DEFAULT NULL,
                        `updated_at` DATETIME DEFAULT NULL,
                        `memory_peak` BIGINT DEFAULT NULL,
                        `errors` INT DEFAULT 0,
                        `warnings` INT DEFAULT 0,
                        PRIMARY KEY (id),
                        KEY idx_status (status)
                    ) $charset_collate;";
                    dbDelta($sql);
                    $results[] = "Progress table created";
                    break;
                    
                case 'populate_cache':
                    // Trigger cache population
                    do_action('llms_populate_cache');
                    $results[] = "Cache population scheduled";
                    break;
                    
                case 'fix_settings':
                    $defaults = array(
                        'post_types' => array('page', 'post'),
                        'max_posts' => 500,
                        'max_words' => 1000,
                        'include_meta' => true,
                        'include_excerpts' => true,
                        'include_taxonomies' => true,
                        'update_frequency' => 'immediate'
                    );
                    update_option('llms_generator_settings', $defaults);
                    $results[] = "Settings reset to defaults";
                    break;
            }
        }
        
        return $results;
    }
    
    public static function test_generation() {
        // Create a test progress ID
        $progress_id = 'test_generation_' . time();
        set_transient('llms_current_progress_id', $progress_id, HOUR_IN_SECONDS);
        
        // Trigger generation
        do_action('llms_update_llms_file_cron');
        
        return $progress_id;
    }
}

// Add admin menu for debug
add_action('admin_menu', function() {
    add_submenu_page(
        'llms-file-manager',
        'Debug & Fix',
        'Debug & Fix',
        'manage_options',
        'llms-debug-fix',
        'llms_debug_fix_page'
    );
});

function llms_debug_fix_page() {
    ?>
    <div class="wrap">
        <h1>LLMS.txt Debug & Fix</h1>
        
        <?php
        if (isset($_POST['run_diagnostics'])) {
            $diagnostics = LLMS_Debug_Fix::run_diagnostics();
            ?>
            <div class="notice notice-info">
                <h2>Diagnostics Results</h2>
                <?php if (empty($diagnostics['issues'])): ?>
                    <p>✅ No issues found!</p>
                <?php else: ?>
                    <h3>Issues Found:</h3>
                    <ul>
                        <?php foreach ($diagnostics['issues'] as $issue): ?>
                            <li>❌ <?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="post">
                        <?php wp_nonce_field('llms_apply_fixes'); ?>
                        <input type="hidden" name="fixes" value="<?php echo esc_attr(json_encode($diagnostics['fixes'])); ?>">
                        <button type="submit" name="apply_fixes" class="button button-primary">Apply Fixes</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php
        }
        
        if (isset($_POST['apply_fixes']) && wp_verify_nonce($_POST['_wpnonce'], 'llms_apply_fixes')) {
            $fixes = json_decode(stripslashes($_POST['fixes']), true);
            $results = LLMS_Debug_Fix::apply_fixes($fixes);
            ?>
            <div class="notice notice-success">
                <h2>Fixes Applied</h2>
                <ul>
                    <?php foreach ($results as $result): ?>
                        <li>✅ <?php echo esc_html($result); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
        
        if (isset($_POST['test_generation'])) {
            $progress_id = LLMS_Debug_Fix::test_generation();
            ?>
            <div class="notice notice-info">
                <h2>Generation Test Started</h2>
                <p>Progress ID: <?php echo esc_html($progress_id); ?></p>
                <p><a href="<?php echo admin_url('admin.php?page=llms-file-manager&tab=management&progress=' . $progress_id . '&_wpnonce=' . wp_create_nonce('llms_progress_' . $progress_id)); ?>" class="button">View Progress</a></p>
            </div>
            <?php
        }
        ?>
        
        <form method="post">
            <h2>Run Diagnostics</h2>
            <p>Check for common issues with the LLMS.txt plugin.</p>
            <button type="submit" name="run_diagnostics" class="button button-primary">Run Diagnostics</button>
        </form>
        
        <hr>
        
        <form method="post">
            <h2>Test Generation</h2>
            <p>Run a test generation to check if everything is working.</p>
            <button type="submit" name="test_generation" class="button">Test Generation</button>
        </form>
        
        <hr>
        
        <h2>Current Status</h2>
        <?php
        global $wpdb;
        $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}llms_txt_cache");
        $logs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}llms_txt_logs");
        $progress_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}llms_txt_progress WHERE status = 'running'");
        ?>
        <ul>
            <li>Cache entries: <?php echo intval($cache_count); ?></li>
            <li>Log entries: <?php echo intval($logs_count); ?></li>
            <li>Running processes: <?php echo intval($progress_count); ?></li>
        </ul>
    </div>
    <?php
}