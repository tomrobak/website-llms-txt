<?php
/**
 * Plugin Name: WP LLMs.txt
 * Description: Manages and automatically generates LLMS.txt files for LLM/AI consumption and integrates with SEO plugins (Yoast SEO, RankMath). Originally created by Website LLM (https://www.websitellm.com) - forked and modified by Tom Robak.
 * Version: 1.0
 * Author: Tom Robak
 * Author URI: https://wplove.co
 * Text Domain: wp-llms-txt
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LLMS_VERSION', '1.0');
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

// Initialize the plugin
function llms_init() {
    new LLMS_Core();
    new LLMS_Cache_Manager();
    new LLMS_Progress();
    
    // Initialize auto-updater
    new LLMS_Updater(__FILE__, 'tomrobak/website-llms-txt');
}

// Hook the initialization function
add_action('init', 'llms_init');