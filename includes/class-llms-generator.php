<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once LLMS_PLUGIN_DIR . 'includes/class-llms-content-cleaner.php';

class LLMS_Generator
{
    private $settings;
    private $content_cleaner;
    private $wp_filesystem;
    private $llms_path;
    private $write_log;
    private $llms_name;
    private $limit = 500;
    private ?LLMS_Logger $logger = null;

    public function __construct()
    {
        $this->settings = get_option('llms_generator_settings', array(
            'post_types' => array('page', 'documentation', 'post'),
            'max_posts' => 100,
            'max_words' => 250,
            'include_meta' => true,
            'include_excerpts' => true,
            'include_taxonomies' => true,
            'update_frequency' => 'immediate',
            'need_check_option' => true,
        ));

        // Initialize content cleaner
        $this->content_cleaner = new LLMS_Content_Cleaner();
        
        // Initialize logger
        $this->logger = new LLMS_Logger();

        // Initialize hooks
        add_action('init', array($this, 'init_generator'), 20);
        
        // Hook into settings update to populate cache
        add_action('update_option_llms_generator_settings', array($this, 'populate_cache_on_settings_change'), 10, 2);
        
        // Hook for cache population
        add_action('llms_populate_cache', array($this, 'populate_cache_for_existing_posts'));

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
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (
                `post_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
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
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }
        $this->wp_filesystem = $wp_filesystem;
    }

    public function init_generator($force = false)
    {
        // Initialize filesystem if not already done
        if (empty($this->wp_filesystem)) {
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
     * Get the actual file path where LLMS.txt is stored
     * @return string
     */
    public function get_llms_file_path() {
        $upload_dir = wp_upload_dir();
        
        // Initialize llms_name if not set
        if (empty($this->llms_name)) {
            $siteurl = get_option('siteurl');
            if($siteurl) {
                $parsed = parse_url($siteurl);
                $this->llms_name = isset($parsed['host']) ? $parsed['host'] : 'localhost';
            } else {
                $this->llms_name = 'localhost';
            }
        }
        
        return $upload_dir['basedir'] . '/' . $this->llms_name . '.llms.txt';
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
                $upload_dir = wp_upload_dir();
                if (isset($upload_dir['error']) && $upload_dir['error']) {
                    $this->log_error('Failed to get upload directory: ' . $upload_dir['error']);
                    return false;
                }
                $this->llms_path = $upload_dir['basedir'] . '/' . $this->llms_name . '.llms.txt';
            }

            // Ensure directory exists
            $dir = dirname($this->llms_path);
            if (!file_exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    $this->log_error('Failed to create directory: ' . $dir);
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
            $upload_dir = wp_upload_dir();
            if (isset($upload_dir['error']) && $upload_dir['error']) {
                $this->log_error('Failed to get upload directory for reading: ' . $upload_dir['error']);
                return $content;
            }
            
            $upload_path = $upload_dir['basedir'] . '/' . $this->llms_name . '.llms.txt';
            
            // Generate cache key based on file path
            $cache_key = 'llms_txt_content_' . md5($upload_path);
            
            // Try to get cached content
            $cached_content = get_transient($cache_key);
            
            if (false !== $cached_content) {
                // Return cached content
                return $content . $cached_content;
            }
            
            // File not in cache, read from disk
            if (file_exists($upload_path)) {
                // Check if file is readable
                if (!is_readable($upload_path)) {
                    $this->log_error('File exists but is not readable: ' . $upload_path);
                    return $content;
                }
                
                $file_content = @file_get_contents($upload_path);
                if (false !== $file_content) {
                    // Cache for 1 hour
                    set_transient($cache_key, $file_content, HOUR_IN_SECONDS);
                    $content .= $file_content;
                } else {
                    $this->log_error('Failed to read file contents: ' . $upload_path);
                }
            }
            
            return $content;
            
        } catch (Exception $e) {
            $this->log_error('Exception in get_llms_content: ' . $e->getMessage());
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

            $offset = 0;
            do {
                $params = [$post_type];
                $params[] = $this->limit;
                $params[] = $offset;
                $params = [$post_type, $this->limit, $offset];
                $conditions = "WHERE p.post_type = %s AND p.post_status = 'publish' AND cache.post_id IS NULL";
                $joins = " LEFT JOIN {$table_cache} cache ON p.ID = cache.post_id ";
                $posts = $wpdb->get_results($wpdb->prepare("SELECT p.ID, cache.* FROM {$wpdb->posts} p $joins $conditions ORDER BY p.post_date DESC LIMIT %d OFFSET %d", ...$params));

                if (!empty($posts)) {
                    foreach ($posts as $cache_post) {
                        if(!$cache_post->post_id) {
                            $post = get_post($cache_post->ID);
                            $this->handle_post_update($cache_post->ID, $post, 'manual');
                            unset($post);
                        }
                    }
                }

                $offset = $offset + $this->limit;
            } while (!empty($posts));

            unset($posts);

            if (defined('WP_CLI') && WP_CLI) {
                \WP_CLI::log('END processing type: ' . $post_type);
            }
        }
    }

    public function generate_content()
    {
        // Set initial progress
        LLMS_Progress::set_progress('generate_content', 0, 4, __('Starting content generation...', 'wp-llms-txt'));
        
        // Fire action before generation starts
        do_action('llms_txt_before_generate', $this->settings);
        
        $this->updates_all_posts();
        LLMS_Progress::set_progress('generate_content', 1, 4, __('Generating site info...', 'wp-llms-txt'));
        
        $this->generate_site_info();
        LLMS_Progress::set_progress('generate_content', 2, 4, __('Generating overview...', 'wp-llms-txt'));
        
        $this->generate_overview();
        LLMS_Progress::set_progress('generate_content', 3, 4, __('Generating detailed content...', 'wp-llms-txt'));
        
        $this->generate_detailed_content();
        LLMS_Progress::set_progress('generate_content', 4, 4, __('Content generation completed!', 'wp-llms-txt'));
        
        // Fire action after generation completes
        do_action('llms_txt_after_generate', $this->get_llms_file_path(), $this->settings);
        
        // Clear progress after completion
        LLMS_Progress::clear_progress();
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
            $i = 0;
            $exit = false;

            do {
                $conditions = " WHERE `type` = %s AND `is_visible`=1 AND `status`='publish' ";
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
                if (!empty($posts)) {
                    
                    // Allow developers to filter max posts per type
                    $max_posts = apply_filters('llms_txt_max_posts_per_type', $this->settings['max_posts'], $post_type);
                    
                    // Debug: Log max posts setting
                    $this->log_error("Max posts for {$post_type}: {$max_posts}");
                    
                    foreach ($posts as $data) {
                        if($max_posts > 0 && $i >= $max_posts) {
                            $exit = true;
                            break;
                        }

                        if($data->overview) {
                            // Allow developers to filter overview content
                            $overview = apply_filters('llms_txt_overview_content', $data->overview, $data->post_id, $post_type);
                            $output .= $overview;
                            $i++;
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
            $i = 0;

            do {
                $conditions = " WHERE `type` = %s AND `is_visible`=1 AND `status`='publish' ";
                $params = [
                    $post_type,
                    $this->limit,
                    $offset
                ];

                $posts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cache $conditions ORDER BY `published` DESC LIMIT %d OFFSET %d", ...$params));
                
                // Debug logging
                if (empty($posts)) {
                    $this->log_error("No posts found for type: {$post_type}, offset: {$offset}");
                } else {
                    $this->log_error("Found " . count($posts) . " posts for type: {$post_type}, offset: {$offset}");
                }
                
                $output = '';
                if (!empty($posts)) {
                    
                    // Allow developers to filter max posts per type
                    $max_posts = apply_filters('llms_txt_max_posts_per_type', $this->settings['max_posts'], $post_type);
                    
                    foreach ($posts as $data) {
                        if (!$data->content) continue;
                        if ($max_posts > 0 && $i >= $max_posts) {
                            $exit = true;
                            break;
                        }
                        
                        // Log progress
                        $this->logger->update_progress($i + 1, intval($data->post_id), $data->title);
                        $this->logger->debug('Processing post', [
                            'post_id' => $data->post_id,
                            'title' => $data->title,
                            'type' => $data->type,
                            'content_length' => strlen($data->content)
                        ], intval($data->post_id));

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
                                    $terms = get_the_terms($data->post_id, $tax->name);
                                    if ($terms && !is_wp_error($terms)) {
                                        $term_names = wp_list_pluck($terms, 'name');
                                        $output .= "- " . $tax->labels->name . ": " . implode(', ', $term_names) . "\n";
                                    }
                                }
                            }
                        }

                        // Add post title
                        $output .= "\n### " . esc_html($data->title) . "\n";

                        $content = wp_trim_words($data->content, $this->settings['max_words'] ?? 250, '...');
                        
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

                        $i++;
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

        $is_visible = 1;
        $use_yoast = class_exists('WPSEO_Meta');
        $use_rankmath = function_exists('rank_math');
        if($use_yoast) {
            $robots_noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
            $robots_nofollow = get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true);
            if($robots_noindex || $robots_nofollow) {
                $is_visible = 0;
            }
        }

        if ($use_rankmath) {
            $robots_noindex = get_post_meta($post_id, 'rank_math_robots', true);
            if($robots_noindex) {
                $is_visible = 0;
            }
        }

        $aioseo_enabled = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aioseo_posts'") === "{$wpdb->prefix}aioseo_posts";
        if($aioseo_enabled) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT robots_noindex, robots_nofollow FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d", $post_id));
            if(isset($row->robots_noindex) && $row->robots_noindex) {
                $is_visible = 0;
            }

            if(isset($row->robots_nofollow) && $row->robots_nofollow) {
                $is_visible = 0;
            }
        }
        
        // Allow developers to override whether to include a post
        $is_visible = apply_filters('llms_txt_include_post', $is_visible, $post_id, $post);

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

        $wpdb->replace(
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
        // Get progress ID from transient or generate new one
        $progress_id = get_transient('llms_current_progress_id');
        if (!$progress_id) {
            $progress_id = 'file_generation_' . time();
        }
        
        // Start progress tracking
        $total_posts = $this->count_total_posts();
        $this->logger->start_progress($progress_id, $total_posts);
        $this->logger->info('Starting LLMS.txt file generation', [
            'total_posts' => $total_posts,
            'post_types' => $this->settings['post_types']
        ]);
        
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log('Start');
        }

        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['error']) && $upload_dir['error']) {
            $this->log_error('Failed to get upload directory in update_llms_file: ' . $upload_dir['error']);
            $this->logger->error('Failed to get upload directory', ['error' => $upload_dir['error']]);
            $this->logger->complete_progress('error');
            return;
        }
        
        $upload_path = $upload_dir['basedir'] . '/llms.txt';
        if (file_exists($upload_path)) {
            if (!@unlink($upload_path)) {
                $this->log_error('Failed to delete file: ' . $upload_path);
            }
        }

        $upload_path = $upload_dir['basedir'] . '/' . $this->llms_name . '.llms.txt';
        if (file_exists($upload_path)) {
            if (!@unlink($upload_path)) {
                $this->log_error('Failed to delete file: ' . $upload_path);
            } else {
                // Clear file content cache
                $cache_key = 'llms_txt_content_' . md5($upload_path);
                delete_transient($cache_key);
            }
        }

        if(defined('FLYWHEEL_PLUGIN_DIR')) {
            $file_path = dirname(ABSPATH) . 'www/' . 'llms.txt';
            if (file_exists($file_path)) {
                if (!@unlink($file_path)) {
                    $this->log_error('Failed to delete Flywheel file: ' . $file_path);
                }
            }
        } else {
            $file_path = ABSPATH . 'llms.txt';
            if (file_exists($file_path)) {
                if (!@unlink($file_path)) {
                    $this->log_error('Failed to delete root file: ' . $file_path);
                }
            }
        }

        $this->generate_content();

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
        
        // Complete progress tracking
        $this->logger->info('LLMS.txt file generation completed successfully');
        $this->logger->complete_progress('completed');
        
        // Clear the progress ID transient
        delete_transient('llms_current_progress_id');
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
        
        foreach ($this->settings['post_types'] as $post_type) {
            if ($post_type === 'llms_txt') continue;
            
            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'paged' => 1,
                'fields' => 'ids'
            );
            
            $query = new WP_Query($args);
            $total_pages = $query->max_num_pages;
            
            for ($page = 1; $page <= $total_pages; $page++) {
                $args['paged'] = $page;
                $query = new WP_Query($args);
                
                foreach ($query->posts as $post_id) {
                    $post = get_post($post_id);
                    if ($post) {
                        $this->handle_post_update($post_id, $post, false, 'populate');
                    }
                }
                
                // Free up memory
                wp_reset_postdata();
            }
        }
        
        // Regenerate the file after populating cache
        wp_schedule_single_event(time() + 10, 'llms_update_llms_file_cron');
    }
}