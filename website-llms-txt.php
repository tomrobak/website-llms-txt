<?php
/**
 * Plugin Name: WP LLMs.txt
 * Description: Manages and automatically generates LLMS.txt files for LLM/AI consumption and integrates with SEO plugins (Yoast SEO, RankMath). Originally created by Website LLM - forked and modified by Tom Robak.
 * Version: 2.1.7
 * Author: Tom Robak
 * Author URI: https://wplove.co
 * Text Domain: wp-llms-txt
 * Domain Path: /languages
 * Requires at least: 6.7
 * Requires PHP: 8.3
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// PHP 8.3+ compatibility check
if (version_compare(PHP_VERSION, '8.3', '<')) {
    add_action('admin_notices', function(): void {
        echo '<div class="notice notice-error"><p>';
        printf(
            /* translators: %s: current PHP version */
            esc_html__('WP LLMs.txt requires PHP 8.3 or higher. You are running PHP %s. Please upgrade your PHP version.', 'wp-llms-txt'),
            esc_html(PHP_VERSION)
        );
        echo '</p></div>';
    });
    return;
}

// WordPress version compatibility check
if (version_compare(get_bloginfo('version'), '6.7', '<')) {
    add_action('admin_notices', function(): void {
        echo '<div class="notice notice-error"><p>';
        printf(
            /* translators: %s: current WordPress version */
            esc_html__('WP LLMs.txt requires WordPress 6.7 or higher. You are running WordPress %s. Please upgrade your WordPress installation.', 'wp-llms-txt'),
            esc_html(get_bloginfo('version'))
        );
        echo '</p></div>';
    });
    return;
}

// Define plugin constants
define('LLMS_VERSION', '2.1.7');
define('LLMS_PLUGIN_FILE', __FILE__);
define('LLMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LLMS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize plugin - load dependencies first
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-content-cleaner.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-cache-manager.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-progress.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-logger.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-rest-api.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-generator.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-core.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-updater.php';
// Note: class-llms-provider.php is loaded conditionally in LLMS_Core::init_seo_integrations()

/**
 * Initialize the plugin with modern PHP 8.3+ features
 */
function llms_init(): void {
    // Initialize logger first with singleton pattern
    $GLOBALS['llms_logger'] = new LLMS_Logger();
    
    // Initialize REST API handler
    LLMS_REST_API::init();
    
    new LLMS_Core();
    new LLMS_Cache_Manager();
    new LLMS_Progress();
    
    // Initialize auto-updater
    new LLMS_Updater(plugin_file: __FILE__, github_repo: 'tomrobak/website-llms-txt');
}

// Hook the initialization function - priority 0 to ensure early loading
add_action('init', 'llms_init', 0);

/**
 * Get the logger instance
 */
function llms_get_logger(): ?LLMS_Logger {
    return $GLOBALS['llms_logger'] ?? null;
}

/**
 * Activation hook - create database tables
 */
function llms_activate(): void {
    global $wpdb;
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
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
        KEY idx_published (published),
        KEY idx_stock_status (stock_status),
        KEY idx_product_type (product_type)
    ) $charset_collate;";
    
    dbDelta($sql);
    
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
        `execution_time` FLOAT DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_timestamp (timestamp),
        KEY idx_level (level),
        KEY idx_post_id (post_id)
    ) $charset_collate;";
    
    dbDelta($sql_logs);
    
    // Create progress table
    $progress_table = $wpdb->prefix . 'llms_txt_progress';
    $sql_progress = "CREATE TABLE $progress_table (
        `id` VARCHAR(50) NOT NULL,
        `status` VARCHAR(20) DEFAULT 'running',
        `current_item` INT DEFAULT 0,
        `total_items` INT DEFAULT 0,
        `current_post_id` BIGINT UNSIGNED DEFAULT NULL,
        `current_post_title` TEXT,
        `started_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        `memory_peak` BIGINT DEFAULT 0,
        `errors` INT DEFAULT 0,
        `warnings` INT DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    dbDelta($sql_progress);
    
    // Also ensure rewrite rules are flushed
    flush_rewrite_rules();
    
    // Set a flag that activation has run
    update_option('llms_activation_run', true);
    
    // Schedule initial cache population
    wp_schedule_single_event(time() + 10, 'llms_populate_cache');
}

register_activation_hook(__FILE__, 'llms_activate');