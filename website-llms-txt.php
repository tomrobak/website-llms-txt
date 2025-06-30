<?php
/**
 * Plugin Name: WP LLMs.txt
 * Description: Manages and automatically generates LLMS.txt files for LLM/AI consumption and integrates with SEO plugins (Yoast SEO, RankMath). Originally created by Website LLM (https://www.websitellm.com) - forked and modified by Tom Robak.
 * Version: 2.0.1
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
define('LLMS_VERSION', '2.0');
define('LLMS_PLUGIN_FILE', __FILE__);
define('LLMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LLMS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize plugin - load dependencies first
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-content-cleaner.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-cache-manager.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-progress.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-generator.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-core.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-updater.php';
// Note: class-llms-provider.php is loaded conditionally in LLMS_Core::init_seo_integrations()

/**
 * Initialize the plugin with modern PHP 8.3+ features
 */
function llms_init(): void {
    new LLMS_Core();
    new LLMS_Cache_Manager();
    new LLMS_Progress();
    
    // Initialize auto-updater
    new LLMS_Updater(plugin_file: __FILE__, github_repo: 'tomrobak/website-llms-txt');
}

// Hook the initialization function
add_action('init', 'llms_init');