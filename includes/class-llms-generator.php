<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once LLMS_PLUGIN_DIR . 'includes/class-llms-content-cleaner.php';
require_once LLMS_PLUGIN_DIR . 'includes/class-llms-generation-lock.php';

class LLMS_Generator
{
    private $settings;
    private $content_cleaner;
    private $wp_filesystem;
    private $llms_path;
    private $write_log;
    private $llms_name;
    private $limit = 500;
    private $batch_size = 100; // Smaller batches for better memory management
    private $memory_threshold = 134217728; // 128MB threshold
    private ?LLMS_Logger $logger = null;
    private $current_file_type = 'standard'; // 'standard' or 'full'

    public function __construct()
    {
        // Get settings with proper defaults
        $defaults = array(
            'post_types' => array('page', 'post'), // Only core post types as default
            'max_posts' => 500, // Higher limit for comprehensive AI training
            'max_words' => 1000, // Better default for AI training
            'include_meta' => true,
            'include_excerpts' => true,
            'include_taxonomies' => true,
            'update_frequency' => 'immediate',
            'need_check_option' => true,
        );
        
        $this->settings = get_option('llms_generator_settings', $defaults);
        
        // Ensure settings have all required keys
        $this->settings = wp_parse_args($this->settings, $defaults);

        // Initialize content cleaner
        $this->content_cleaner = new LLMS_Content_Cleaner();
        
        // Get logger instance
        $this->logger = llms_get_logger();

        // Initialize hooks
        add_action('init', array($this, 'init_generator'), 20);
        
        // Hook into settings update to populate cache
        add_action('update_option_llms_generator_settings', array($this, 'populate_cache_on_settings_change'), 10, 2);
        
        // Hook for cache population
        add_action('llms_populate_cache', array($this, 'populate_cache_for_existing_posts'));
        add_action('llms_warm_cache', array($this, 'warm_cache_for_stale_posts'));

        // Hook into post updates
        add_action('save_post', array($this, 'handle_post_update'), 10, 3);
        add_action('deleted_post', array($this, 'handle_post_deletion'), 999, 2);
        add_action('wp_update_term', array($this, 'handle_term_update'));
        add_action('llms_scheduled_update', array($this, 'llms_scheduled_update'));
        add_action('schedule_updates', array($this, 'schedule_updates'));
        add_filter('get_llms_content', array($this, 'get_llms_content'));
        add_action('init', array($this, 'llms_maybe_create_ai_sitemap_page'));
        add_action('llms_update_llms_file_cron', array($this, 'update_llms_file'));
        add_action('init', array($this, 'llms_create_txt_cache_table_if_not_exists'), 999);
        add_action('updates_all_posts', array($this, 'updates_all_posts'), 999);
        add_action('init', array($this, 'check_version_update'), 5);
    }

    public function llms_create_txt_cache_table_if_not_exists()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'llms_txt_cache';
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));

        if ($table_exists !== $table) {
            if ($this->logger) {
                $this->logger->info('Creating cache table');
            }
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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
        } else {
            // Add indexes to existing table if they don't exist
            $this->llms_add_table_indexes();
        }
    }

    /**
     * Add performance indexes to existing table
     * @since 1.1
     */
    private function llms_add_table_indexes() {
        global $wpdb;
        $table = $wpdb->prefix . 'llms_txt_cache';
        
        // Check if indexes already exist
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}");
        $existing_indexes = array();
        foreach ($indexes as $index) {
            $existing_indexes[] = $index->Key_name;
        }
        
        // Add composite index for type, is_visible, status if not exists
        if (!in_array('idx_type_visible_status', $existing_indexes)) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_type_visible_status (type, is_visible, status)");
        }
        
        // Add index for published date if not exists
        if (!in_array('idx_published', $existing_indexes)) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_published (published)");
        }
        
        // Add WooCommerce indexes if they don't exist
        if (!in_array('idx_stock_status', $existing_indexes)) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_stock_status (stock_status)");
        }
        
        if (!in_array('idx_product_type', $existing_indexes)) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_product_type (product_type)");
        }
        
        // Add WooCommerce columns if they don't exist  
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
        $column_names = array();
        foreach ($columns as $column) {
            $column_names[] = $column->Field;
        }
        
        // Migrate 'show' column to 'is_visible' if needed
        if (in_array('show', $column_names) && !in_array('is_visible', $column_names)) {
            $wpdb->query("ALTER TABLE {$table} CHANGE COLUMN `show` `is_visible` TINYINT NULL DEFAULT NULL");
        }
        
        if (!in_array('stock_status', $column_names)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN stock_status VARCHAR(50) DEFAULT NULL AFTER price");
        }
        
        if (!in_array('stock_quantity', $column_names)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN stock_quantity INT DEFAULT NULL AFTER stock_status");
        }
        
        if (!in_array('product_type', $column_names)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN product_type VARCHAR(50) DEFAULT NULL AFTER stock_quantity");
        }
    }

    public function check_version_update()
    {
        $last_version = get_option('llms_version_activated', '1.0.0');
        if (version_compare($last_version, LLMS_VERSION, '<')) {
            // Version updated, force cache refresh
            if ($this->logger) {
                $this->logger->info("Version updated from {$last_version} to " . LLMS_VERSION . ", scheduling cache refresh");
            }
            wp_schedule_single_event(time() + 5, 'llms_populate_cache');
            update_option('llms_version_activated', LLMS_VERSION);
        }
    }

    public function llms_maybe_create_ai_sitemap_page()
    {
        if (!isset($this->settings['removed_ai_sitemap']))
        {
            $page = get_page_by_path('ai-sitemap');
            if ($page && $page->post_type === 'page')
            {
                wp_delete_post($page->ID, true);
                $this->settings['removed_ai_sitemap'] = true;
                update_option('llms_generator_settings', $this->settings);
            }
        }
    }

    public function llms_scheduled_update()
    {
        $this->init_generator(true);
    }

    private function init_filesystem()
    {
        global $wp_filesystem;
        if (!isset($wp_filesystem) || $wp_filesystem === null) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }
        $this->wp_filesystem = $wp_filesystem;
    }

    public function init_generator($force = false)
    {
        // Initialize filesystem if not already done
        if ($this->wp_filesystem === null) {
            $this->init_filesystem();
        }

        $siteurl = get_option('siteurl');
        if($siteurl) {
            $parsed = parse_url($siteurl);
            $this->llms_name = isset($parsed['host']) ? $parsed['host'] : 'localhost';
        } else {
            $this->llms_name = 'localhost';
        }

        if ($this->settings['update_frequency'] !== 'immediate') {
            do_action('schedule_updates');
        }

        if (isset($_POST['llms_generator_settings'], $_POST['llms_generator_settings']['update_frequency']) || $force) {
            wp_clear_scheduled_hook('llms_update_llms_file_cron');
            wp_schedule_single_event(time() + 30, 'llms_update_llms_file_cron');
        }
    }

    /**
     * Check memory usage and clear caches if needed
     * @return bool True if memory is available, false if cleanup was needed
     */
    private function check_memory_usage(): bool {
        $memory_usage = memory_get_usage(true);
        $memory_limit = $this->get_memory_limit();
        
        // Log memory usage
        if ($this->logger) {
            $this->logger->debug(sprintf(
                'Memory usage: %s / %s (%.2f%%)',
                size_format($memory_usage),
                size_format($memory_limit),
                ($memory_usage / $memory_limit) * 100
            ));
        }
        
        // If we're using more than 80% of available memory, clear caches
        if ($memory_usage > ($memory_limit * 0.8)) {
            wp_cache_flush();
            
            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            if ($this->logger) {
                $this->logger->warning('Memory threshold reached, clearing caches');
            }
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Get PHP memory limit in bytes
     * @return int
     */
    private function get_memory_limit(): int {
        $memory_limit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            if ($matches[2] == 'M') {
                return $matches[1] * 1024 * 1024;
            } else if ($matches[2] == 'K') {
                return $matches[1] * 1024;
            } else if ($matches[2] == 'G') {
                return $matches[1] * 1024 * 1024 * 1024;
            }
        }
        
        return 134217728; // Default to 128MB
    }
    
    /**
     * Get the actual file path where LLMS.txt is stored
     * @param string $type 'standard' or 'full'
     * @return string
     */
    public function get_llms_file_path($type = 'standard') {
        // Use website root directory, not uploads folder
        $filename = ($type === 'full') ? 'llms-full.txt' : 'llms.txt';
        return ABSPATH . $filename;
    }
    
    /**
     * Check if LLMS.txt file exists
     * @return bool
     */
    public function file_exists() {
        return file_exists($this->get_llms_file_path());
    }
    
    /**
     * Get file modification time
     * @return int|false
     */
    public function get_file_mtime() {
        $file_path = $this->get_llms_file_path();
        return file_exists($file_path) ? filemtime($file_path) : false;
    }
    
    /**
     * Get file size
     * @return int|false
     */
    public function get_file_size() {
        $file_path = $this->get_llms_file_path();
        return file_exists($file_path) ? filesize($file_path) : false;
    }

    private function write_log($content)
    {
        if (!$this->write_log) {
            $upload_dir = wp_upload_dir();
            $this->write_log = $upload_dir['basedir'] . '/log.txt';
        }

        file_put_contents($this->write_log, $content, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log error messages
     * @param string $message Error message
     * @since 1.1
     */
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WP LLMs.txt] ' . $message);
        }
        
        // Also log to admin transient for display
        $errors = get_transient('llms_generation_errors');
        if (!is_array($errors)) {
            $errors = array();
        }
        
        $errors[] = array(
            'message' => $message,
            'time' => current_time('mysql')
        );
        
        // Keep only last 10 errors
        if (count($errors) > 10) {
            $errors = array_slice($errors, -10);
        }
        
        set_transient('llms_generation_errors', $errors, HOUR_IN_SECONDS);
    }

    private function write_file($content)
    {
        if (!$this->wp_filesystem) {
            $this->init_filesystem();
        }

        if ($this->wp_filesystem) {
            if (!$this->llms_path) {
                // Use website root directory with correct filename
                $this->llms_path = $this->get_llms_file_path($this->current_file_type);
            }

            // Check if we can write to the root directory
            $dir = dirname($this->llms_path);
            if (!is_writable($dir)) {
                $this->log_error('Cannot write to root directory: ' . $dir . '. Please check file permissions.');
                // Try to set permissions (may not work on all hosts)
                @chmod($dir, 0755);
                // Check again
                if (!is_writable($dir)) {
                    $this->log_error('Root directory is not writable even after chmod. Manual intervention required.');
                    return false;
                }
            }

            // Check if this is the first write (file doesn't exist or we're writing the header)
            if (!file_exists($this->llms_path) || strpos((string)$content, '# LLMs.txt') !== false) {
                // First write - overwrite the file
                $result = file_put_contents($this->llms_path, (string)$content, LOCK_EX);
            } else {
                // Subsequent writes - append to the file
                $result = file_put_contents($this->llms_path, (string)$content, FILE_APPEND | LOCK_EX);
            }
            
            if ($result === false) {
                $this->log_error('Failed to write to file: ' . $this->llms_path);
                return false;
            }
            
            return true;
        }
        
        return false;
    }

    public function get_llms_content($content)
    {
        try {
            // Default to standard file for backward compatibility
            $file_path = $this->get_llms_file_path('standard');
            
            // Generate cache key based on file path
            $cache_key = 'llms_txt_content_' . md5($file_path);
            
            // Try to get cached content
            $cached_content = get_transient($cache_key);
            
            if (false !== $cached_content) {
                // Return cached content
                return $content . $cached_content;
            }
            
            // File not in cache, read from disk
            if (file_exists($file_path)) {
                // Check if file is readable
                if (!is_readable($file_path)) {
                    $this->log_error('File exists but is not readable: ' . $file_path);
                    return $content;
                }
                
                $file_content = @file_get_contents($file_path);
                if (false !== $file_content) {
                    // Cache for 1 hour
                    set_transient($cache_key, $file_content, HOUR_IN_SECONDS);
                    $content .= $file_content;
                } else {
                    $this->log_error('Failed to read file contents: ' . $file_path);
                }
            }
            
            return $content;
            
        } catch (Exception $e) {
            $this->log_error('Exception in get_llms_content: ' . $e->getMessage());
            return $content;
        }
    }
    
    /**
     * Get content from a specific file type
     * @param string $content Existing content
     * @param string $type 'standard' or 'full'
     * @return string
     */
    public function get_llms_content_by_type($content, $type = 'standard')
    {
        try {
            $file_path = $this->get_llms_file_path($type);
            
            // Generate cache key based on file path
            $cache_key = 'llms_txt_content_' . md5($file_path);
            
            // Try to get cached content
            $cached_content = get_transient($cache_key);
            
            if (false !== $cached_content) {
                // Return cached content
                return $content . $cached_content;
            }
            
            // File not in cache, read from disk
            if (file_exists($file_path)) {
                // Check if file is readable
                if (!is_readable($file_path)) {
                    $this->log_error('File exists but is not readable: ' . $file_path);
                    return $content;
                }
                
                $file_content = @file_get_contents($file_path);
                if (false !== $file_content) {
                    // Cache for 1 hour
                    set_transient($cache_key, $file_content, HOUR_IN_SECONDS);
                    $content .= $file_content;
                } else {
                    $this->log_error('Failed to read file contents: ' . $file_path);
                }
            }
            
            return $content;
            
        } catch (Exception $e) {
            $this->log_error('Exception in get_llms_content_by_type: ' . $e->getMessage());
            return $content;
        }
    }

    public function updates_all_posts()
    {
        global $wpdb;
        $table_cache = $wpdb->prefix . 'llms_txt_cache';
        foreach ($this->settings['post_types'] as $post_type) {
            if ($post_type === 'llms_txt') continue;

            if (defined('WP_CLI') && WP_CLI) {
                \WP_CLI::log('Processing type: ' . $post_type);
            }
            
            // Debug: Check if table exists and has data
            $count_in_cache = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_cache} WHERE type = %s", $post_type));
            $this->log_error("Posts in cache for {$post_type}: {$count_in_cache}");
            
            // Debug: Check actual posts in WordPress
            $count_in_wp = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $post_type));
            $this->log_error("Posts in WordPress for {$post_type}: {$count_in_wp}");
            
            // Debug: Check visibility status
            $count_visible = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_cache} WHERE type = %s AND (is_visible = 1 OR is_visible IS NULL)", $post_type));
            $this->log_error("Visible posts in cache for {$post_type}: {$count_visible}");

            $offset = 0;
            do {
                $params = [$post_type];
                $params[] = $this->limit;
                $params[] = $offset;
                $params = [$post_type, $this->limit, $offset];
                $conditions = "WHERE p.post_type = %s AND p.post_status = 'publish' AND cache.post_id IS NULL";
                $joins = " LEFT JOIN {$table_cache} cache ON p.ID = cache.post_id ";
                $posts = $wpdb->get_results($wpdb->prepare("SELECT p.ID, cache.* FROM {$wpdb->posts} p $joins $conditions ORDER BY p.post_date DESC LIMIT %d OFFSET %d", ...$params));

                if (is_array($posts) && count($posts) > 0) {
                    foreach ($posts as $cache_post) {
                        if(!$cache_post->post_id) {
                            $post = get_post($cache_post->ID);
                            if ($post) {
                                $this->handle_post_update($cache_post->ID, $post, 'manual');
                            }
                            unset($post);
                        }
                    }
                }

                $offset = $offset + $this->limit;
            } while (is_array($posts) && count($posts) > 0);

            unset($posts);

            if (defined('WP_CLI') && WP_CLI) {
                \WP_CLI::log('END processing type: ' . $post_type);
            }
        }
    }

    /**
     * Legacy method - redirect to generate both files
     */
    public function generate_content()
    {
        $this->update_llms_file();
    }
    
    /**
     * Generate standard llms.txt according to llmstxt.org specification
     */
    private function generate_standard_content()
    {
        // Reset path for this file type
        $this->llms_path = null;
        
        if ($this->logger) {
            $this->logger->info('Starting standard llms.txt generation');
        }
        
        // Fire action before generation starts
        do_action('llms_txt_before_generate', $this->settings);
        
        // Ensure cache is populated before generating
        $this->ensure_cache_populated();
        
        // Check cache status
        global $wpdb;
        $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}llms_txt_cache");
        if ($this->logger) {
            $this->logger->info("Cache status before generation: {$cache_count} posts");
        }
        
        // Generate standard format header
        $this->generate_standard_header();
        
        // Generate standard sections
        $this->generate_standard_sections();
        
        if ($this->logger) {
            $this->logger->info('Standard llms.txt generation completed!');
        }
        
        // Fire action after generation completes
        do_action('llms_txt_after_generate', $this->get_llms_file_path('standard'), $this->settings);
    }
    
    /**
     * Generate comprehensive llms-full.txt with all content
     */
    private function generate_full_content()
    {
        // Reset path for this file type
        $this->llms_path = null;
        
        if ($this->logger) {
            $this->logger->info('Starting comprehensive llms-full.txt generation');
        }
        
        // Fire action before generation starts
        do_action('llms_txt_before_generate', $this->settings);
        
        // Don't call updates_all_posts here - cache should already be populated
        
        // Ensure cache is populated before generating
        $this->ensure_cache_populated();
        
        // Check cache status
        global $wpdb;
        $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}llms_txt_cache");
        if ($this->logger) {
            $this->logger->info("Cache status before full generation: {$cache_count} posts");
        }
        
        if ($this->logger) {
            $this->logger->info('Generating site info...');
        }
        $this->generate_site_info();
        
        if ($this->logger) {
            $this->logger->info('Generating overview...');
        }
        $this->generate_overview();
        
        if ($this->logger) {
            $this->logger->info('Generating detailed content...');
        }
        $this->generate_detailed_content();
        
        if ($this->logger) {
            $this->logger->info('Comprehensive llms-full.txt generation completed!');
        }
        
        // Fire action after generation completes
        do_action('llms_txt_after_generate', $this->get_llms_file_path('full'), $this->settings);
    }
    
    /**
     * Generate standard llms.txt header according to llmstxt.org
     */
    private function generate_standard_header()
    {
        $site_name = get_bloginfo('name');
        $meta_description = $this->get_site_meta_description();
        
        // Start with UTF-8 BOM
        $output = "\xEF\xBB\xBF";
        
        // H1 with site name (required)
        $output .= "# " . $site_name . "\n\n";
        
        // Optional blockquote with description
        if ($meta_description) {
            $output .= "> " . $meta_description . "\n\n";
        }
        
        // Add site context
        $output .= "This is a WordPress-powered website focused on " . $this->get_site_focus() . ".\n\n";
        
        $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));
    }
    
    /**
     * Generate standard sections for llms.txt
     */
    private function generate_standard_sections()
    {
        global $wpdb;
        $table_cache = $wpdb->prefix . 'llms_txt_cache';
        
        // Key Pages Section
        $output = "## Key Pages\n\n";
        
        // Get important pages
        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, title, link, meta FROM $table_cache 
             WHERE type = 'page' AND (is_visible=1 OR is_visible IS NULL) AND status='publish'
             ORDER BY published DESC LIMIT %d",
            10
        ));
        
        if (!empty($pages)) {
            foreach ($pages as $page) {
                $description = $page->meta ?: 'Page content';
                $output .= "- [" . esc_html($page->title) . "](" . esc_url($page->link) . "): " . 
                          wp_trim_words($description, 15) . "\n";
            }
            $output .= "\n";
        }
        
        $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));
        
        // Recent Content Section
        $output = "## Recent Content\n\n";
        
        // Get recent posts
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, title, link, meta, type FROM $table_cache 
             WHERE type IN ('post', 'product') AND (is_visible=1 OR is_visible IS NULL) AND status='publish'
             ORDER BY published DESC LIMIT %d",
            20
        ));
        
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $description = $post->meta ?: 'Content';
                $output .= "- [" . esc_html($post->title) . "](" . esc_url($post->link) . "): " . 
                          wp_trim_words($description, 15) . "\n";
            }
            $output .= "\n";
        }
        
        $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));
        
        // Categories/Topics Section
        $output = "## Topics\n\n";
        $categories = get_categories(['number' => 10, 'orderby' => 'count', 'order' => 'DESC']);
        
        if (!empty($categories)) {
            foreach ($categories as $cat) {
                $output .= "- **" . esc_html($cat->name) . "** (" . $cat->count . " posts)\n";
            }
            $output .= "\n";
        }
        
        $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));
        
        // Optional Section
        $output = "## Optional\n\n";
        $output .= "For comprehensive content including full post text, see `/llms-full.txt`\n";
        $output .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
        
        $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));
    }
    
    /**
     * Helper to determine site focus from content
     */
    private function get_site_focus()
    {
        // Check if it's a WooCommerce site
        if (class_exists('WooCommerce')) {
            return "e-commerce and online shopping";
        }
        
        // Check for specific post types
        $post_types = $this->settings['post_types'];
        if (in_array('portfolio', $post_types)) {
            return "portfolio and creative work";
        }
        
        // Default based on site tagline
        $tagline = get_bloginfo('description');
        if ($tagline) {
            return strtolower($tagline);
        }
        
        return "content and information sharing";
    }

    private function generate_site_info()
    {
        // Try to get meta description from Yoast or RankMath
        $meta_description = $this->get_site_meta_description();
        $slug = 'ai-sitemap';
        $existing_page = get_page_by_path( $slug );
        $output = "\xEF\xBB\xBF";
        if(is_a($existing_page,'WP_Post')) {
            $output .= "# Learn more:" . get_permalink($existing_page) . "\n\n";
        }
        $output .= "# " . get_bloginfo('name') . "\n\n";
        if ($meta_description) {
            $output .= "> " . $meta_description . "\n\n";
        }
        $output .= "---\n\n";
        $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));
        unset($output);
        unset($meta_description);
    }

    private function remove_shortcodes($content)
    {
        $clean = preg_replace('/\[[^\]]+\]/', '', $content);

        $clean = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $clean);

        $clean = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}\x{202A}-\x{202E}\x{2060}]/u', ' ', $clean);

        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $clean = preg_replace('/[ \t]+/', ' ', $clean);
        $clean = preg_replace('/\s{2,}/u', ' ', $clean);
        $clean = preg_replace('/[\r\n]+/', "\n", $clean);

        return trim(strip_tags($clean));
    }

    private function generate_overview()
    {
        global $wpdb;
        
        // Suspend cache addition for performance
        $suspend = wp_suspend_cache_addition();
        wp_suspend_cache_addition(true);
        
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log('Start generate overview');
        }

        $table_cache = $wpdb->prefix . 'llms_txt_cache';
        
        // Allow developers to filter post types
        $post_types = apply_filters('llms_txt_post_types', $this->settings['post_types']);
        
        foreach ($post_types as $post_type) {
            if ($post_type === 'llms_txt') continue;

            $post_type_obj = get_post_type_object($post_type);
            if (is_object($post_type_obj) && isset($post_type_obj->labels->name)) {
                $this->write_file(mb_convert_encoding("\n## {$post_type_obj->labels->name}\n\n", 'UTF-8', 'auto'));
            }

            $offset = 0;
            $posts_processed_for_type = 0; // Counter per post type
            $exit = false;

            do {
                $conditions = " WHERE `type` = %s AND (`is_visible`=1 OR `is_visible` IS NULL) AND `status`='publish' ";
                $params = [
                    $post_type,
                    $this->limit,
                    $offset
                ];

                $posts = $wpdb->get_results($wpdb->prepare("SELECT `post_id`, `overview` FROM $table_cache $conditions ORDER BY `published` DESC LIMIT %d OFFSET %d", ...$params));
                
                // Debug: Log query and results
                $this->log_error("Overview query for {$post_type}: " . $wpdb->prepare("SELECT `post_id`, `overview` FROM $table_cache $conditions ORDER BY `published` DESC LIMIT %d OFFSET %d", ...$params));
                $this->log_error("Overview results count: " . count($posts));
                
                if (defined('WP_CLI') && WP_CLI) {
                    \WP_CLI::log('Count: ' . count($posts));
                    \WP_CLI::log($wpdb->prepare("SELECT `post_id`, `overview` FROM $table_cache $conditions ORDER BY `published` DESC LIMIT %d OFFSET %d", ...$params));
                }
                $output = '';
                if (is_array($posts) && count($posts) > 0) {
                    
                    // Allow developers to filter max posts per type
                    $max_posts = apply_filters('llms_txt_max_posts_per_type', $this->settings['max_posts'], $post_type);
                    
                    // Debug: Log max posts setting
                    $this->log_error("Max posts for {$post_type}: {$max_posts}");
                    
                    foreach ($posts as $data) {
                        if($max_posts > 0 && $posts_processed_for_type >= $max_posts) {
                            $exit = true;
                            break;
                        }

                        if($data->overview) {
                            // Allow developers to filter overview content
                            $overview = apply_filters('llms_txt_overview_content', $data->overview, $data->post_id, $post_type);
                            $output .= $overview;
                            $posts_processed_for_type++;
                        }

                        unset($data);
                    }

                    if (!empty($output)) {
                    $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));
                }
                    unset($output);
                }

                $offset += $this->limit;

            } while (!empty($posts) && !$exit);

            $this->write_file(mb_convert_encoding("\n---\n\n", 'UTF-8', 'auto'));

            if (defined('WP_CLI') && WP_CLI) {
                \WP_CLI::log('End generate overview');
            }
        }
        
        // Restore cache addition
        wp_suspend_cache_addition($suspend);
    }

    private function generate_detailed_content()
    {
        global $wpdb;

        // Suspend cache addition for performance
        $suspend = wp_suspend_cache_addition();
        wp_suspend_cache_addition(true);
        
        // Increase memory limit if possible
        $memory_limit = ini_get('memory_limit');
        if (intval($memory_limit) < 256) {
            @ini_set('memory_limit', '256M');
        }

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log('Start generate detailed content');
        }

        $output = "#\n" . "# Detailed Content\n\n";
        $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));

        $table_cache = $wpdb->prefix . 'llms_txt_cache';

        // Allow developers to filter post types
        $post_types = apply_filters('llms_txt_post_types', $this->settings['post_types']);
        
        $global_posts_processed = 0; // Global counter across all post types
        
        foreach ($post_types as $post_type) {
            if ($post_type === 'llms_txt') continue;

            $post_type_obj = get_post_type_object($post_type);
            if (is_object($post_type_obj) && isset($post_type_obj->labels->name)) {
                $output = "\n## " . $post_type_obj->labels->name . "\n\n";
                if (!empty($output)) {
                    $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));
                }
            }

            if (defined('WP_CLI') && WP_CLI) {
                \WP_CLI::log('Generate detailed: ' . $post_type);
            }

            $offset = 0;
            $exit = false;
            $posts_processed_for_type = 0; // Counter per post type

            do {
                // Check memory before processing batch
                if (!$this->check_memory_usage()) {
                    if ($this->logger) {
                        $this->logger->warning('Memory limit reached during generation, continuing with reduced batch size');
                    }
                    // Reduce batch size
                    $this->limit = max(50, intval($this->limit / 2));
                }
                
                $conditions = " WHERE `type` = %s AND (`is_visible`=1 OR `is_visible` IS NULL) AND `status`='publish' ";
                $params = [
                    $post_type,
                    $this->limit,
                    $offset
                ];

                $posts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cache $conditions ORDER BY `published` DESC LIMIT %d OFFSET %d", ...$params));
                
                // Debug: Check total counts by visibility
                if ($offset === 0) {
                    $total_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_cache WHERE `type` = %s", $post_type));
                    $visible_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_cache WHERE `type` = %s AND (`is_visible`=1 OR `is_visible` IS NULL) AND `status`='publish'", $post_type));
                    $hidden_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_cache WHERE `type` = %s AND `is_visible`=0", $post_type));
                    
                    if ($this->logger) {
                        $this->logger->info("Cache stats for {$post_type}: Total={$total_count}, Visible={$visible_count}, Hidden={$hidden_count}");
                    }
                }
                
                // Debug logging
                if (!is_array($posts) || count($posts) === 0) {
                    $this->log_error("No posts found for type: {$post_type}, offset: {$offset}");
                    if ($this->logger) {
                        $this->logger->warning("No posts found for type: {$post_type}, offset: {$offset}");
                    }
                } else {
                    $this->log_error("Found " . count($posts) . " posts for type: {$post_type}, offset: {$offset}");
                    if ($this->logger) {
                        $this->logger->info("Found " . count($posts) . " posts for type: {$post_type}, offset: {$offset}");
                    }
                }
                
                $output = '';
                if (is_array($posts) && count($posts) > 0) {
                    
                    // Allow developers to filter max posts per type
                    $max_posts = apply_filters('llms_txt_max_posts_per_type', $this->settings['max_posts'], $post_type);
                    
                    foreach ($posts as $data) {
                        if (!$data->content) continue;
                        if ($max_posts > 0 && $posts_processed_for_type >= $max_posts) {
                            $exit = true;
                            break;
                        }
                        
                        // Log progress with global counter
                        $global_posts_processed++;
                        if ($this->logger) {
                            $this->logger->update_progress($global_posts_processed, intval($data->post_id), $data->title);
                            $this->logger->debug('Processing post', [
                                'post_id' => $data->post_id,
                                'title' => $data->title,
                                'type' => $data->type,
                                'content_length' => strlen($data->content),
                                'global_count' => $global_posts_processed,
                                'type_count' => $posts_processed_for_type + 1
                            ], intval($data->post_id));
                        }

                        if ($this->settings['include_meta']) {
                            if ($data->meta) {
                                $output .= "> " . wp_trim_words($data->meta, $this->settings['max_words'] ?? 250, '...') . "\n\n";
                            }

                            $output .= "- Published: " . esc_html(date('Y-m-d', strtotime($data->published))) . "\n";
                            $output .= "- Modified: " . esc_html(date('Y-m-d', strtotime($data->modified))) . "\n";
                            $output .= "- URL: " . esc_html($data->link) . "\n";

                            // WooCommerce product data
                            if ($data->type === 'product' && class_exists('WooCommerce')) {
                                if ($data->sku) {
                                    $output .= '- SKU: ' . esc_html($data->sku) . "\n";
                                }

                                if ($data->price) {
                                    $output .= '- Price: ' . esc_html($data->price) . "\n";
                                }
                                
                                if ($data->stock_status) {
                                    $stock_label = $data->stock_status === 'instock' ? 'In Stock' : 
                                                  ($data->stock_status === 'outofstock' ? 'Out of Stock' : 'On Backorder');
                                    $output .= '- Availability: ' . esc_html($stock_label);
                                    if ($data->stock_quantity && $data->stock_status === 'instock') {
                                        $output .= ' (' . esc_html($data->stock_quantity) . ' available)';
                                    }
                                    $output .= "\n";
                                }
                                
                                if ($data->product_type && $data->product_type !== 'simple') {
                                    $output .= '- Product Type: ' . esc_html(ucfirst($data->product_type)) . "\n";
                                }
                            }

                            if ($this->settings['include_taxonomies']) {
                                $taxonomies = get_object_taxonomies($data->type, 'objects');
                                foreach ($taxonomies as $tax) {
                                    // Skip private taxonomies if option is enabled
                                    if (!empty($this->settings['exclude_private_taxonomies']) && !$tax->public) {
                                        continue;
                                    }
                                    
                                    $terms = get_the_terms($data->post_id, $tax->name);
                                    if ($terms && !is_wp_error($terms)) {
                                        $term_names = wp_list_pluck($terms, 'name');
                                        $output .= "- " . $tax->labels->name . ": " . implode(', ', $term_names) . "\n";
                                    }
                                }
                            }
                            
                            // Include custom fields if enabled
                            if (!empty($this->settings['include_custom_fields'])) {
                                $custom_fields = get_post_meta($data->post_id);
                                if (!empty($custom_fields)) {
                                    $public_fields = array();
                                    foreach ($custom_fields as $key => $values) {
                                        // Skip private fields (starting with _)
                                        if (substr($key, 0, 1) === '_') continue;
                                        
                                        // Skip known system fields
                                        if (in_array($key, ['_edit_lock', '_edit_last', '_wp_page_template'])) continue;
                                        
                                        // Get first value (most custom fields have single value)
                                        $value = isset($values[0]) ? $values[0] : '';
                                        
                                        // Skip empty values
                                        if (empty($value)) continue;
                                        
                                        // Skip serialized data
                                        if (is_serialized($value)) continue;
                                        
                                        $public_fields[$key] = $value;
                                    }
                                    
                                    if (!empty($public_fields)) {
                                        $output .= "- Custom Fields:\n";
                                        foreach ($public_fields as $key => $value) {
                                            // Truncate long values
                                            if (strlen($value) > 100) {
                                                $value = substr($value, 0, 100) . '...';
                                            }
                                            $output .= "  - " . esc_html($key) . ": " . esc_html($value) . "\n";
                                        }
                                    }
                                }
                            }
                        }

                        // Add post title
                        $output .= "\n### " . esc_html($data->title) . "\n";

                        // Use a higher default if max_words is too low for AI training
                        $max_words = $this->settings['max_words'] ?? 250;
                        if ($max_words < 500) {
                            $max_words = 1000; // Better default for AI training
                        }
                        $content = wp_trim_words($data->content, $max_words, '[content truncated due to word limit]');
                        
                        // Allow developers to filter the content
                        $content = apply_filters('llms_txt_content', $content, $data->post_id, $post_type);
                        
                        $output .= "\n";

                        if ($this->settings['include_excerpts'] && $data->excerpts) {
                            $output .= $data->excerpts . "\n\n";
                        }

                        if ($content) {
                            $output .= $content . "\n\n";
                        }

                        $output .= "---\n\n";
                        unset($data);

                        $posts_processed_for_type++;
                    }
                }

                if (!empty($output)) {
                    $this->write_file(mb_convert_encoding($output, 'UTF-8', 'auto'));
                }
                unset($output);

                $offset += $this->limit;

            } while (!empty($posts) && !$exit);

            $this->write_file(mb_convert_encoding("\n---\n\n", 'UTF-8', 'auto'));

            if (defined('WP_CLI') && WP_CLI) {
                \WP_CLI::log('End generate detailed content');
            }
        }
        
        // Restore cache addition
        wp_suspend_cache_addition($suspend);
    }

    public function remove_emojis($text) {
        return preg_replace('/[\x{1F600}-\x{1F64F}'
            . '\x{1F300}-\x{1F5FF}'
            . '\x{1F680}-\x{1F6FF}'
            . '\x{1F1E0}-\x{1F1FF}'
            . '\x{2600}-\x{26FF}'
            . '\x{2700}-\x{27BF}'
            . '\x{FE00}-\x{FE0F}'
            . '\x{1F900}-\x{1F9FF}'
            . '\x{1F018}-\x{1F270}'
            . '\x{238C}-\x{2454}'
            . '\x{20D0}-\x{20FF}]/u', '', $text);
    }

    private function get_site_meta_description()
    {
        if (class_exists('WPSEO_Options') && function_exists('YoastSEO')) {
            return YoastSEO()->meta->for_posts_page()->description;
        } elseif (class_exists('RankMath') || class_exists('RankMath\RankMath')) {
            return get_option('rank_math_description');
        } else {
            $description = get_bloginfo('description');
            if ($description) {
                return get_bloginfo('description');
            } else {
                $front_page_id = get_option('page_on_front');
                $description = '';
                if ($front_page_id) {
                    $description = get_the_excerpt($front_page_id);
                    if (empty($description)) {
                        $description = get_post_field('post_content', $front_page_id);
                    }
                }

                $description = $this->remove_shortcodes(str_replace(']]>', ']]&gt;', apply_filters('the_content', $description)));
                return wp_trim_words(strip_tags(preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}\x{202A}-\x{202E}\x{2060}]/u', ' ', html_entity_decode($description))), 30, '');
            }
        }
    }

    private function get_post_meta_description( $post )
    {
        if (class_exists('WPSEO_Meta') && function_exists('YoastSEO')) {
            return YoastSEO()->meta->for_post($post->ID)->description;
        } elseif (class_exists('RankMath') || class_exists('RankMath\RankMath')) {
            // Try using RankMath's helper class first
            if (class_exists('RankMath\Helper')) {
                $desc = RankMath\Helper::get_post_meta('description', $post->ID);
                if (!empty($desc)) {
                    return $desc;
                }
            }

            // Fallback to Post class if Helper doesn't work
            if (class_exists('RankMath\Post\Post')) {
                return RankMath\Post\Post::get_meta('description', $post->ID);
            }
        }
        return false;
    }
    
    private function get_variable_product_price_range( $product_id )
    {
        global $wpdb;
        
        $prices = $wpdb->get_results($wpdb->prepare("
            SELECT MIN(CAST(meta_value AS DECIMAL(10,2))) as min_price, 
                   MAX(CAST(meta_value AS DECIMAL(10,2))) as max_price
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_parent = %d 
            AND p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_price'
            AND pm.meta_value != ''
        ", $product_id));
        
        if (!empty($prices) && $prices[0]->min_price !== null) {
            return array(
                'min' => $prices[0]->min_price,
                'max' => $prices[0]->max_price
            );
        }
        
        return false;
    }

    /**
     * @param int $post_id
     * @param WP_Post $post
     * @param $update
     * @return void
     */
    public function handle_post_update($post_id, $post, $update, $mode = 'normal')
    {
        global $wpdb;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!in_array($post->post_type, $this->settings['post_types'])) {
            return;
        }

        $table = $wpdb->prefix . 'llms_txt_cache';
        $overview = '';
        $price = '';
        $sku = '';

        $permalink = get_permalink($post->ID);

        $description = $this->get_post_meta_description( $post );
        if (!$description) {
            $fallback_content = $this->remove_shortcodes(apply_filters( 'get_the_excerpt', $post->post_excerpt, $post ) ?: get_the_content(null, false, $post));
            $fallback_content = $this->content_cleaner->clean($fallback_content);
            $description = wp_trim_words(strip_tags($fallback_content), 20, '...');
            if($description) {
                $overview = sprintf("- [%s](%s): %s\n", $post->post_title, $permalink, preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', ' ', $description));
            }
        } else {
            $overview = sprintf("- [%s](%s): %s\n", $post->post_title, $permalink, preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', ' ', $description));
        }

        if (isset($post->post_type) && $post->post_type === 'product' && class_exists('WooCommerce')) {
            // Basic product data
            $sku = get_post_meta($post->ID, '_sku', true);
            $price = get_post_meta($post->ID, '_price', true);
            $regular_price = get_post_meta($post->ID, '_regular_price', true);
            $sale_price = get_post_meta($post->ID, '_sale_price', true);
            $currency = get_option('woocommerce_currency');
            
            // Format price with currency
            if (!empty($price)) {
                if ($sale_price && $sale_price < $regular_price) {
                    $price = sprintf('%s %s (was %s %s)', 
                        number_format((float)$sale_price, 2), 
                        $currency,
                        number_format((float)$regular_price, 2),
                        $currency
                    );
                } else {
                    $price = number_format((float)$price, 2) . " " . $currency;
                }
            }
            
            // Stock status
            $stock_status = get_post_meta($post->ID, '_stock_status', true);
            $stock_quantity = get_post_meta($post->ID, '_stock', true);
            
            // Product type
            $product_type = wp_get_post_terms($post->ID, 'product_type', array('fields' => 'names'));
            $product_type = !empty($product_type) ? $product_type[0] : 'simple';
            
            // Handle variations for variable products
            if ($product_type === 'variable') {
                $variations = get_posts(array(
                    'post_type' => 'product_variation',
                    'post_parent' => $post->ID,
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ));
                
                if (!empty($variations)) {
                    $variation_count = count($variations);
                    $price_range = $this->get_variable_product_price_range($post->ID);
                    if ($price_range) {
                        $price = sprintf('%s - %s %s', 
                            number_format((float)$price_range['min'], 2),
                            number_format((float)$price_range['max'], 2),
                            $currency
                        );
                    }
                }
            }
        }

        $clean_description = '';
        $meta_description = $this->get_post_meta_description( $post );
        if ($meta_description) {
            $clean_description = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', ' ', $meta_description);
        }

        // Start with visible = NULL (let query decide with OR condition)
        $is_visible = null;
        
        // Only mark as not visible if explicitly set to noindex in SEO plugins
        $use_yoast = class_exists('WPSEO_Meta');
        $use_rankmath = function_exists('rank_math');
        
        if($use_yoast) {
            $robots_noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
            // Only check noindex, not nofollow (nofollow doesn't mean exclude from AI training)
            if($robots_noindex === '1') {
                $is_visible = 0;
            }
        }

        if ($use_rankmath && $is_visible !== 0) {
            $robots_meta = get_post_meta($post_id, 'rank_math_robots', true);
            // Check if noindex is specifically set in the robots array
            if(is_array($robots_meta) && in_array('noindex', $robots_meta)) {
                $is_visible = 0;
            }
        }

        $aioseo_enabled = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aioseo_posts'") === "{$wpdb->prefix}aioseo_posts";
        if($aioseo_enabled && $is_visible !== 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT robots_noindex FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d", $post_id));
            // Only check noindex, and only if it's explicitly set to 1
            if(isset($row->robots_noindex) && $row->robots_noindex == 1) {
                $is_visible = 0;
            }
        }
        
        // Default to visible if no SEO plugin explicitly set noindex
        if ($is_visible === null) {
            $is_visible = 1;
        }
        
        // Allow developers to override whether to include a post
        $is_visible = apply_filters('llms_txt_include_post', $is_visible, $post_id, $post);
        
        // Debug logging for visibility decisions
        if ($mode === 'populate' && $this->logger) {
            $this->logger->debug("Post {$post_id} ({$post->post_title}) visibility: {$is_visible}", [
                'post_type' => $post->post_type,
                'post_status' => $post->post_status,
                'yoast_detected' => $use_yoast,
                'rankmath_detected' => $use_rankmath,
                'aioseo_detected' => $aioseo_enabled
            ], $post_id);
        }

        $excerpts = $this->remove_shortcodes($post->post_excerpt);
        
        // Process content directly without ob_start() to prevent memory leaks
        $raw_content = get_the_content(null, false, $post);
        
        // If get_the_content returns empty, try getting post_content directly
        if (empty($raw_content) && !empty($post->post_content)) {
            $raw_content = $post->post_content;
        }
        
        // Debug logging
        if (empty($raw_content)) {
            $this->log_error("Empty content for post ID: {$post_id}, Title: {$post->post_title}");
        }
        
        $processed_content = do_shortcode($raw_content);
        $content = $this->content_cleaner->clean($this->remove_emojis($this->remove_shortcodes($processed_content)));

        $result = $wpdb->replace(
            $table,
            [
                'post_id' => $post_id,
                'is_visible' => $is_visible,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'title' => $post->post_title,
                'link' => $permalink,
                'sku' => isset($sku) ? $sku : null,
                'price' => isset($price) ? $price : null,
                'stock_status' => isset($stock_status) ? $stock_status : null,
                'stock_quantity' => isset($stock_quantity) ? $stock_quantity : null,
                'product_type' => isset($product_type) ? $product_type : null,
                'meta' => $clean_description,
                'excerpts' => $excerpts,
                'overview' => $overview,
                'content' => $content,
                'published' => get_the_date('Y-m-d', $post),
                'modified' => get_the_modified_date('Y-m-d', $post),
            ], [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            ]
        );
        
        if ($result === false) {
            if ($this->logger) {
                $this->logger->error("Failed to insert/update cache for post {$post_id}. Last DB error: " . $wpdb->last_error);
            }
            error_log("[WP LLMs.txt] Failed to cache post {$post_id}: " . $wpdb->last_error);
        } else {
            if ($mode === 'populate' && $this->logger) {
                $this->logger->debug("Successfully cached post {$post_id}");
            }
        }

        if ($this->settings['update_frequency'] === 'immediate' && $update !== 'manual' && $mode !== 'populate') {
            wp_clear_scheduled_hook('llms_update_llms_file_cron');
            wp_schedule_single_event(time() + 30, 'llms_update_llms_file_cron');
        }
    }

    public function handle_post_deletion($post_id, $post)
    {
        global $wpdb;
        if (!$post || $post->post_type === 'revision') {
            return;
        }

        $table = $wpdb->prefix . 'llms_txt_cache';
        $wpdb->delete($table, [
            'post_id' => $post_id
        ], [
            '%d'
        ]);

        if ($this->settings['update_frequency'] === 'immediate') {
            wp_clear_scheduled_hook('llms_update_llms_file_cron');
            wp_schedule_single_event(time() + 30, 'llms_update_llms_file_cron');
        }
    }

    public function handle_term_update($term_id)
    {
        if ($this->settings['update_frequency'] === 'immediate') {
            wp_clear_scheduled_hook('llms_update_llms_file_cron');
            wp_schedule_single_event(time() + 30, 'llms_update_llms_file_cron');
        }
    }

    public function update_llms_file()
    {
        try {
            // Get progress ID from transient
            $progress_id = get_transient('llms_current_progress_id');
            if (!$progress_id) {
                if ($this->logger) {
                    $this->logger->error('No progress ID found, cannot continue');
                }
                return false;
            }
            
            // Check if we can proceed (lock should already be acquired by REST API)
            if (!LLMS_Generation_Lock::is_locked($progress_id)) {
                // Try to acquire lock (in case called directly)
                if (!LLMS_Generation_Lock::acquire($progress_id)) {
                    if ($this->logger) {
                        $this->logger->error('Could not acquire generation lock');
                    }
                    return false;
                }
            }
            
            // Update status to running
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'llms_txt_progress',
                [
                    'status' => 'running',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $progress_id]
            );
            
            // Calculate total posts to process
            $total_posts = $this->count_total_posts();
            
            // Update progress with correct total
            $wpdb->update(
                $wpdb->prefix . 'llms_txt_progress',
                [
                    'total_items' => $total_posts * 2, // Multiply by 2 for both file types
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $progress_id]
            );
            
            if ($this->logger) {
                $this->logger->info('Starting LLMS files generation', [
                    'progress_id' => $progress_id,
                    'total_posts' => $total_posts,
                    'post_types' => $this->settings['post_types']
                ]);
            }
            
            // Ensure cache is populated first
            $this->ensure_cache_populated();
            
            // Generate standard llms.txt first
            $this->generate_llms_file('standard');
            
            // Then generate comprehensive llms-full.txt
            $this->generate_llms_file('full');
            
            // Complete progress tracking
            if ($this->logger) {
                $this->logger->info('LLMS files generation completed successfully');
                $this->logger->complete_progress('completed');
            }
            
            // Clear the progress ID transient and release lock
            delete_transient('llms_current_progress_id');
            LLMS_Generation_Lock::release($progress_id, 'completed');
            
            return true;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Generation failed: ' . $e->getMessage());
                $this->logger->complete_progress('error');
            }
            delete_transient('llms_current_progress_id');
            
            // Release lock on error
            if (isset($progress_id)) {
                LLMS_Generation_Lock::release($progress_id, 'error');
            }
            
            return false;
        }
    }
    
    /**
     * Generate a specific version of the LLMS file
     * @param string $type 'standard' or 'full'
     */
    private function generate_llms_file($type = 'standard')
    {
        $this->current_file_type = $type;
        
        if ($this->logger) {
            $this->logger->info("Generating {$type} LLMS file");
        }
        
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log('Start');
        }

        // Clean up old file locations first (migration from old versions)
        if ($type === 'standard') {
            $upload_dir = wp_upload_dir();
            if (!isset($upload_dir['error']) || !$upload_dir['error']) {
                $old_upload_path = $upload_dir['basedir'] . '/llms.txt';
                if (file_exists($old_upload_path)) {
                    @unlink($old_upload_path);
                    $this->log_error('Removed old llms.txt from uploads directory');
                }
            }
        }
        
        // Delete the current file type from root directory
        $file_path = $this->get_llms_file_path($type);
        if (file_exists($file_path)) {
            if (!@unlink($file_path)) {
                $this->log_error('Failed to delete file: ' . $file_path);
            } else {
                // Clear file content cache
                $cache_key = 'llms_txt_content_' . md5($file_path);
                delete_transient($cache_key);
            }
        }

        // Generate content based on type
        if ($type === 'standard') {
            $this->generate_standard_content();
        } else {
            $this->generate_full_content();
        }

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log('End generate_content event');
        }

        if ( ! is_multisite() ) {
            if (file_exists($upload_path)) {
                $this->wp_filesystem->copy($upload_path, $file_path, true);
            }
        }

        // Update the hidden post
        $core = new LLMS_Core();
        $existing_post = $core->get_llms_post();

        $post_data = array(
            'post_title' => 'LLMS.txt',
            'post_content' => 'content',
            'post_status' => 'publish',
            'post_type' => 'llms_txt'
        );

        if ($existing_post) {
            $post_data['ID'] = $existing_post->ID;
            wp_update_post($post_data);
        } else {
            wp_insert_post($post_data);
        }

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log('Clear cache event');
        }

        do_action('llms_clear_seo_caches');
    }

    public function schedule_updates()
    {
        if (!wp_next_scheduled('llms_scheduled_update')) {
            $interval = ($this->settings['update_frequency'] === 'daily') ? 'daily' : 'weekly';
            wp_schedule_event(time(), $interval, 'llms_scheduled_update');
        }
    }
    
    /**
     * Populate cache when settings change
     */
    public function populate_cache_on_settings_change($old_value, $new_value)
    {
        // Update settings
        $this->settings = $new_value;
        
        // Schedule cache population
        wp_schedule_single_event(time() + 5, 'llms_populate_cache');
    }
    
    /**
     * Check if cache needs to be populated
     */
    private function ensure_cache_populated(): void
    {
        global $wpdb;
        
        $table_cache = $wpdb->prefix . 'llms_txt_cache';
        
        // First check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_cache
        ));
        
        if (!$table_exists) {
            if ($this->logger) {
                $this->logger->error("Cache table does not exist: {$table_cache}");
            }
            $this->llms_create_txt_cache_table_if_not_exists();
            if ($this->logger) {
                $this->logger->info("Cache table created");
            }
        }
        
        // Also ensure logs and progress tables exist
        $this->ensure_all_tables_exist();
        
        // Check if cache has any entries
        $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cache}");
        
        if ($this->logger) {
            $this->logger->info("Cache count check: {$cache_count} entries found");
        }
        
        if ($cache_count == 0) {
            if ($this->logger) {
                $this->logger->info('Cache is empty, populating with existing posts');
            }
            $this->populate_entire_cache();
        } else {
            if ($this->logger) {
                $this->logger->info("Cache contains {$cache_count} posts, skipping population");
            }
        }
    }
    
    /**
     * Ensure all required tables exist
     */
    private function ensure_all_tables_exist(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Check logs table
        $logs_table = $wpdb->prefix . 'llms_txt_logs';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $logs_table))) {
            $charset_collate = $wpdb->get_charset_collate();
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
            if ($this->logger) {
                $this->logger->info("Logs table created");
            }
        }
        
        // Check progress table
        $progress_table = $wpdb->prefix . 'llms_txt_progress';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $progress_table))) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql_progress = "CREATE TABLE $progress_table (
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
                KEY idx_status (status),
                KEY idx_updated (updated_at)
            ) $charset_collate;";
            dbDelta($sql_progress);
            if ($this->logger) {
                $this->logger->info("Progress table created");
            }
        }
    }
    
    /**
     * Populate entire cache with all posts
     */
    private function populate_entire_cache(): void
    {
        global $wpdb;
        
        if ($this->logger) {
            $this->logger->info("Starting populate_entire_cache");
            $this->logger->info("Post types to process: " . json_encode($this->settings['post_types']));
        }
        
        // First check if post types are registered
        $registered_types = get_post_types(['public' => true]);
        if ($this->logger) {
            $this->logger->info("Registered public post types: " . json_encode($registered_types));
        }
        
        foreach ($this->settings['post_types'] as $post_type) {
            if ($post_type === 'llms_txt') continue;
            
            if (!post_type_exists($post_type)) {
                if ($this->logger) {
                    $this->logger->warning("Post type '{$post_type}' does not exist!");
                }
                continue;
            }
            
            if ($this->logger) {
                $this->logger->info("Populating cache for post type: {$post_type}");
            }
            
            // Get all published posts of this type
            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'suppress_filters' => true
            );
            
            $query = new WP_Query($args);
            $total = count($query->posts);
            
            if ($this->logger) {
                $this->logger->info("WP_Query found {$total} {$post_type} posts to cache");
                $this->logger->debug("SQL Query: " . $query->request);
            }
            
            if ($total === 0) {
                // Try direct database query
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                    $post_type
                ));
                if ($this->logger) {
                    $this->logger->info("Direct DB query found {$count} {$post_type} posts");
                }
            }
            
            $processed = 0;
            foreach ($query->posts as $post_id) {
                $post = get_post($post_id);
                if ($post) {
                    if ($this->logger) {
                        $this->logger->debug("Processing post ID: {$post_id}, Title: {$post->post_title}");
                    }
                    
                    try {
                        $this->handle_post_update($post_id, $post, false, 'populate');
                        $processed++;
                        
                        if ($processed % 5 == 0 && $this->logger) {
                            $this->logger->info("Cached {$processed}/{$total} {$post_type} posts");
                        }
                    } catch (Exception $e) {
                        if ($this->logger) {
                            $this->logger->error("Error caching post {$post_id}: " . $e->getMessage());
                        }
                    }
                } else {
                    if ($this->logger) {
                        $this->logger->warning("Could not get post object for ID: {$post_id}");
                    }
                }
            }
            
            if ($this->logger) {
                $this->logger->info("Completed caching {$processed} {$post_type} posts");
            }
            wp_reset_postdata();
        }
        
        // Verify cache was populated
        $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}llms_txt_cache");
        if ($this->logger) {
            $this->logger->info("Total posts in cache after population: {$cache_count}");
        }
    }
    
    /**
     * Count total posts to process
     */
    private function count_total_posts(): int
    {
        global $wpdb;
        
        $total = 0;
        foreach ($this->settings['post_types'] as $post_type) {
            if ($post_type === 'llms_txt') continue;
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = %s AND post_status = 'publish'",
                $post_type
            ));
            
            $total += intval($count);
        }
        
        return $total;
    }
    
    /**
     * Populate cache for all existing posts
     */
    public function populate_cache_for_existing_posts()
    {
        global $wpdb;
        
        $table_cache = $wpdb->prefix . 'llms_txt_cache';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_cache
        ));
        
        if ($table_exists !== $table_cache) {
            $this->log_error('Cache table does not exist when trying to populate');
            return;
        }
        
        // Track start time for performance monitoring
        $start_time = microtime(true);
        $processed_count = 0;
        
        if ($this->logger) {
            $this->logger->info('Starting cache population for existing posts');
        }
        
        foreach ($this->settings['post_types'] as $post_type) {
            if ($post_type === 'llms_txt') continue;
            
            // Use smaller batch size for better memory management
            $batch_size = min($this->batch_size, 50);
            
            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $batch_size,
                'paged' => 1,
                'fields' => 'ids',
                'no_found_rows' => true, // Performance optimization
                'update_post_meta_cache' => false, // Skip meta cache
                'update_post_term_cache' => false, // Skip term cache
            );
            
            $query = new WP_Query($args);
            $page = 1;
            
            while ($query->have_posts()) {
                // Check memory usage before processing batch
                if (!$this->check_memory_usage()) {
                    if ($this->logger) {
                        $this->logger->warning('Memory limit approaching, pausing cache population');
                    }
                    // Schedule continuation
                    wp_schedule_single_event(time() + 60, 'llms_populate_cache');
                    return;
                }
                
                foreach ($query->posts as $post_id) {
                    $post = get_post($post_id);
                    if ($post) {
                        $this->handle_post_update($post_id, $post, false, 'populate');
                        $processed_count++;
                        
                        // Log progress every 100 posts
                        if ($processed_count % 100 === 0 && $this->logger) {
                            $this->logger->info(sprintf(
                                'Processed %d posts, memory usage: %s',
                                $processed_count,
                                size_format(memory_get_usage(true))
                            ));
                        }
                    }
                }
                
                // Free up memory after each batch
                wp_reset_postdata();
                wp_cache_flush();
                
                // Next page
                $page++;
                $args['paged'] = $page;
                $query = new WP_Query($args);
            }
        }
        
        // Log completion
        $execution_time = microtime(true) - $start_time;
        if ($this->logger) {
            $this->logger->info(sprintf(
                'Cache population completed: %d posts processed in %.2f seconds, peak memory: %s',
                $processed_count,
                $execution_time,
                size_format(memory_get_peak_usage(true))
            ));
        }
        
        // Regenerate the file after populating cache
        wp_schedule_single_event(time() + 10, 'llms_update_llms_file_cron');
    }
    
    /**
     * Warm cache by updating stale entries
     */
    public function warm_cache_for_stale_posts() {
        global $wpdb;
        
        $table_cache = $wpdb->prefix . 'llms_txt_cache';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_cache
        ));
        
        if ($table_exists !== $table_cache) {
            $this->log_error('Cache table does not exist when trying to warm cache');
            return;
        }
        
        // Track start time
        $start_time = microtime(true);
        $updated_count = 0;
        
        if ($this->logger) {
            $this->logger->info('Starting cache warming for stale posts');
        }
        
        // Find stale cache entries
        // Build post types for IN clause safely
        $post_types_placeholders = array_fill(0, count($this->settings['post_types']), '%s');
        $post_types_in = implode(',', $post_types_placeholders);
        
        // Build query without prepare for dynamic parts
        $query = "SELECT c.post_id, p.post_type 
                 FROM {$table_cache} c 
                 LEFT JOIN {$wpdb->posts} p ON c.post_id = p.ID 
                 WHERE p.post_modified > c.modified 
                 AND p.post_status = 'publish'";
        
        if (!empty($this->settings['post_types'])) {
            $query .= " AND p.post_type IN (" . $post_types_in . ")";
        }
        
        $query .= " LIMIT %d";
        
        // Prepare with all values
        $prepared_query = $wpdb->prepare(
            $query,
            array_merge($this->settings['post_types'], [100])
        );
        
        $stale_posts = $wpdb->get_results($prepared_query);
        
        foreach ($stale_posts as $stale_post) {
            // Check memory usage
            if (!$this->check_memory_usage()) {
                if ($this->logger) {
                    $this->logger->warning('Memory limit approaching during cache warming');
                }
                // Schedule continuation
                wp_schedule_single_event(time() + 60, 'llms_warm_cache');
                break;
            }
            
            $post = get_post($stale_post->post_id);
            if ($post) {
                $this->handle_post_update($stale_post->post_id, $post, false, 'warm');
                $updated_count++;
                
                // Log progress every 20 posts
                if ($updated_count % 20 === 0 && $this->logger) {
                    $this->logger->info(sprintf(
                        'Warmed %d stale cache entries',
                        $updated_count
                    ));
                }
            }
        }
        
        // Log completion
        $execution_time = microtime(true) - $start_time;
        if ($this->logger) {
            $this->logger->info(sprintf(
                'Cache warming completed: %d entries updated in %.2f seconds',
                $updated_count,
                $execution_time
            ));
        }
        
        // If we updated any posts, regenerate the file
        if ($updated_count > 0) {
            wp_schedule_single_event(time() + 10, 'llms_update_llms_file_cron');
        }
    }
}