<?php
/**
 * LLMS Core Class - Modern PHP 8.3+ Implementation
 * 
 * Core plugin orchestrator with type safety and modern features
 * 
 * @package WP_LLMs_txt
 * @since 2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LLMS_Core {
    private ?LLMS_Generator $generator = null;

    public function __construct()
    {
        // Initialize core functionality
        add_action('init', [$this, 'init'], 0);

        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_filter('plugin_action_links_' . plugin_basename(LLMS_PLUGIN_FILE), [$this, 'add_settings_link']);

        // Handle cache clearing and file generation
        add_action('admin_post_clear_caches', [$this, 'handle_cache_clearing']);
        add_action('admin_post_clear_error_log', [$this, 'handle_clear_error_log']);
        add_action('admin_post_generate_llms_file', [$this, 'handle_generate_file']);
        add_action('admin_post_populate_llms_cache', [$this, 'handle_populate_cache']);
        add_action('admin_post_warm_llms_cache', [$this, 'handle_warm_cache']);
        
        // Handle import/export
        add_action('admin_post_llms_export_settings', [$this, 'handle_export_settings']);
        add_action('admin_post_llms_import_settings', [$this, 'handle_import_settings']);

        // Initialize SEO integrations before post type registration
        add_action('init', [$this, 'init_seo_integrations'], -1);

        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Handle settings redirect
        add_filter('wp_redirect', [$this, 'modify_settings_redirect'], 10, 2);

        // Add required scripts for admin
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        add_action('wp_head', [$this, 'wp_head']);
        
        // AJAX action for triggering generation
        add_action('wp_ajax_llms_trigger_generation', [$this, 'ajax_trigger_generation']);
    }


    public function wp_head(): void {
        echo '<link rel="llms-sitemap" href="' . esc_url( home_url( '/llms.txt' ) ) . '" />' . "\n";
    }

    public function get_llms_post(): ?WP_Post {
        $posts = get_posts([
            'post_type' => 'llms_txt',
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    public function init(): void {
        // Register post type
        $this->create_post_type();
        // Initialize generator after post type
        $this->generator = new LLMS_Generator();

        // Add rewrite rules
        $this->add_rewrite_rule();
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_llms_request']);
    }

    public function create_post_type(): void {
        register_post_type('llms_txt', [
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor'],
            'exclude_from_sitemap' => true
        ]);
    }

    public function init_seo_integrations(): void {
        if (class_exists('RankMath')) {
            require_once LLMS_PLUGIN_DIR . 'includes/class-llms-provider.php';
            require_once LLMS_PLUGIN_DIR . 'includes/rank-math.php';
        }

        if (defined('WPSEO_VERSION') && class_exists('WPSEO_Sitemaps')) {
            require_once LLMS_PLUGIN_DIR . 'includes/yoast.php';
        }
    }

    public function register_settings(): void {
        register_setting(
            'llms_generator_settings',
            'llms_generator_settings',
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [
                    'post_types' => ['page', 'post'], // Only core post types as default
                    'max_posts' => 100,
                    'max_words' => 250,
                    'include_meta' => true,
                    'include_excerpts' => true,
                    'include_taxonomies' => true,
                    'update_frequency' => 'immediate',
                    'need_check_option' => true,
                ]
            ]
        );
    }

    public function sanitize_settings(mixed $value): array {
        if (!is_array($value)) {
            add_settings_error(
                'llms_generator_settings',
                'invalid_data',
                __('Invalid settings data format.', 'wp-llms-txt'),
                'error'
            );
            return [];
        }
        $clean = [];
        
        // Ensure post_types is an array and contains only valid post types
        $clean['post_types'] = [];
        if (isset($value['post_types']) && is_array($value['post_types'])) {
            $valid_types = get_post_types(['public' => true]);
            foreach ($value['post_types'] as $type) {
                if (in_array($type, $valid_types) && $type !== 'attachment' && $type !== 'llms_txt') {
                    $clean['post_types'][] = sanitize_key($type);
                }
            }
        }
        
        // Validate that at least one post type is selected
        if (empty($clean['post_types'])) {
            add_settings_error(
                'llms_generator_settings',
                'no_post_types',
                __('Please select at least one post type to include in the LLMS.txt file.', 'wp-llms-txt'),
                'error'
            );
            
            // Keep previous value if validation fails
            $old_settings = get_option('llms_generator_settings');
            if (isset($old_settings['post_types']) && !empty($old_settings['post_types'])) {
                $clean['post_types'] = $old_settings['post_types'];
            }
        }
        
        // Sanitize max posts
        $clean['max_posts'] = isset($value['max_posts']) ? 
            min(max(absint($value['max_posts']), 1), 100000) : 100;

        // Sanitize max posts
        $clean['max_words'] = isset($value['max_words']) ?
            min(max(absint($value['max_words']), 1), 100000) : 250;
        
        // Sanitize boolean values
        $clean['include_meta'] = !empty($value['include_meta']);
        $clean['include_excerpts'] = !empty($value['include_excerpts']);
        $clean['include_taxonomies'] = !empty($value['include_taxonomies']);
        $clean['include_custom_fields'] = !empty($value['include_custom_fields']);
        $clean['exclude_private_taxonomies'] = !empty($value['exclude_private_taxonomies']);

        // Sanitize selected taxonomies
        $clean['selected_taxonomies'] = array();
        if (isset($value['selected_taxonomies']) && is_array($value['selected_taxonomies'])) {
            $valid_taxonomies = get_taxonomies(array('public' => true));
            foreach ($value['selected_taxonomies'] as $tax) {
                if (in_array($tax, $valid_taxonomies)) {
                    $clean['selected_taxonomies'][] = sanitize_key($tax);
                }
            }
        }
        
        // Sanitize custom field keys
        if (isset($value['custom_field_keys'])) {
            $keys = array_map('trim', explode(',', $value['custom_field_keys']));
            $clean['custom_field_keys'] = implode(', ', array_filter(array_map('sanitize_key', $keys)));
        }

        // Sanitize update frequency
        $clean['update_frequency'] = isset($value['update_frequency']) && 
            in_array($value['update_frequency'], array('immediate', 'daily', 'weekly')) ? 
            sanitize_key($value['update_frequency']) : 'immediate';

        return $clean;
    }
    
    /**
     * Modify settings redirect to preserve tab
     */
    public function modify_settings_redirect(string $location, int $status): string {
        // Check if this is our settings page redirect
        if (strpos($location, 'page=llms-file-manager') !== false && 
            strpos($location, 'settings-updated=true') !== false &&
            !empty($_POST['active_tab'])) {
            
            $tab = sanitize_key($_POST['active_tab']);
            $location = add_query_arg('tab', $tab, $location);
        }
        
        return $location;
    }

    public function enqueue_admin_scripts(string $hook): void {
        if (!in_array($hook, ['tools_page_llms-file-manager', 'toplevel_page_llms-file-manager'])) {
            return;
        }

        // Enqueue jQuery UI Sortable
        wp_enqueue_script('jquery-ui-sortable');

        // Enqueue admin styles with dashicons dependency
        wp_enqueue_style(
            'llms-admin-styles',
            LLMS_PLUGIN_URL . 'admin/admin-styles.css',
            array('dashicons'),
            LLMS_VERSION
        );
        
        // Enqueue progress tracker styles
        wp_enqueue_style(
            'llms-progress-styles',
            LLMS_PLUGIN_URL . 'admin/css/llms-progress.css',
            array(),
            LLMS_VERSION
        );

        // Register and enqueue admin script
        wp_register_script(
            'llms-admin-script',
            LLMS_PLUGIN_URL . 'admin/admin-script.js',
            array('jquery', 'jquery-ui-sortable'),
            LLMS_VERSION,
            true
        );

        wp_enqueue_script('llms-admin-script');
        
        // Enqueue new progress tracker script
        wp_enqueue_script(
            'llms-progress-tracker',
            LLMS_PLUGIN_URL . 'admin/js/llms-progress.js',
            array('wp-api-request'),
            LLMS_VERSION,
            true
        );
        
        // Localize script with REST API data
        wp_localize_script('llms-progress-tracker', 'wpApiSettings', array(
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest')
        ));
        
        // Enqueue validation script
        wp_enqueue_script(
            'llms-validation-script',
            LLMS_PLUGIN_URL . 'admin/validation.js',
            array('jquery'),
            LLMS_VERSION,
            true
        );
        
        // Localize validation messages
        wp_localize_script('llms-validation-script', 'llmsValidation', array(
            'messages' => array(
                'validationFailed' => __('Validation failed. Please fix the following errors:', 'wp-llms-txt'),
                'selectPostType' => __('Please select at least one post type.', 'wp-llms-txt'),
                'invalidMaxPosts' => __('Maximum posts must be between 1 and 100,000.', 'wp-llms-txt'),
                'invalidMaxWords' => __('Maximum words must be between 1 and 100,000.', 'wp-llms-txt'),
                'invalidCustomFields' => __('Custom field keys can only contain letters, numbers, underscores, hyphens, and commas.', 'wp-llms-txt'),
                'numberOutOfRange' => __('Value is out of allowed range.', 'wp-llms-txt')
            )
        ));
    }


    public function add_admin_menu(): void {
        add_submenu_page(
            'tools.php',
            'Llms.txt',
            'Llms.txt',
            'manage_options',
            'llms-file-manager',
            array($this, 'render_admin_page')
        );
    }

    public function add_settings_link(array $links): array {
        $settings_link = '<a href="admin.php?page=llms-file-manager">' . __('Settings', 'wp-llms-txt') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function render_admin_page(): void {
        include LLMS_PLUGIN_DIR . 'admin/modern-admin-page.php';
    }

    public function handle_cache_clearing(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('clear_caches', 'clear_caches_nonce');
        do_action('llms_clear_seo_caches');
        $this->add_rewrite_rule();
        flush_rewrite_rules();

        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'] . '/llms.txt';
        if (file_exists($upload_path)) {
            unlink($upload_path);
        }

        wp_clear_scheduled_hook('llms_update_llms_file_cron');
        wp_schedule_single_event(time() + 2, 'llms_update_llms_file_cron');


        $redirect_args = array(
            'page' => 'llms-file-manager',
            'cache_cleared' => 'true',
            '_wpnonce' => wp_create_nonce('llms_cache_cleared')
        );
        
        if (!empty($_POST['active_tab'])) {
            $redirect_args['tab'] = sanitize_key($_POST['active_tab']);
        }
        
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
    
    public function handle_clear_error_log(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('clear_error_log', 'clear_error_log_nonce');
        
        // Clear error log transient
        delete_transient('llms_generation_errors');
        
        // Redirect back with success message
        wp_safe_redirect(add_query_arg(array(
            'page' => 'llms-file-manager',
            'error_log_cleared' => 'true',
            '_wpnonce' => wp_create_nonce('llms_error_log_cleared')
        ), admin_url('admin.php')));
        exit;
    }
    
    public function handle_populate_cache(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('populate_llms_cache', 'populate_llms_cache_nonce');
        
        // Schedule cache population
        wp_schedule_single_event(time() + 2, 'llms_populate_cache');
        
        // Redirect back with success message
        $redirect_args = array(
            'page' => 'llms-file-manager',
            'cache_populated' => 'true',
            '_wpnonce' => wp_create_nonce('llms_cache_populated')
        );
        
        if (!empty($_POST['active_tab'])) {
            $redirect_args['tab'] = sanitize_key($_POST['active_tab']);
        }
        
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
    
    public function handle_warm_cache(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('warm_llms_cache', 'warm_llms_cache_nonce');
        
        // Schedule cache warming
        wp_schedule_single_event(time() + 2, 'llms_warm_cache');
        
        // Redirect back with success message
        $redirect_args = array(
            'page' => 'llms-file-manager',
            'cache_warmed' => 'true',
            '_wpnonce' => wp_create_nonce('llms_cache_warmed')
        );
        
        if (!empty($_POST['active_tab'])) {
            $redirect_args['tab'] = sanitize_key($_POST['active_tab']);
        }
        
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
    
    public function handle_generate_file(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('generate_llms_file', 'generate_llms_file_nonce');
        
        // Generate a unique progress ID
        $progress_id = 'file_generation_' . time();
        
        // Store progress ID in transient for 1 hour
        set_transient('llms_current_progress_id', $progress_id, HOUR_IN_SECONDS);
        
        // Create initial progress entry in database
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'llms_txt_progress',
            [
                'id' => $progress_id,
                'status' => 'pending',
                'current_item' => 0,
                'total_items' => 100, // Will be updated when generation starts
                'started_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );
        
        // Schedule the generation to run in background
        wp_schedule_single_event(time() + 1, 'llms_update_llms_file_cron');
        
        // Redirect with progress ID
        $redirect_args = array(
            'page' => 'llms-file-manager',
            'progress' => $progress_id,
            'tab' => !empty($_POST['active_tab']) ? sanitize_key($_POST['active_tab']) : 'management',
            '_wpnonce' => wp_create_nonce('llms_progress_' . $progress_id)
        );
        
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public function add_rewrite_rule(): void {
        global $wp_rewrite;

        if($wp_rewrite) {
            $wp_rewrite->add_rule('llms.txt', 'index.php?llms_txt=1', 'top');
        }
    }

    public function add_query_vars(array $vars): array {
        $vars[] = 'llms_txt';
        return $vars;
    }

    public function handle_llms_request(): void {
        if (get_query_var('llms_txt')) {
            $latest_post = apply_filters('get_llms_content', '');
            if ($latest_post) {
                header('Content-Type: text/plain');
                echo esc_html($latest_post);
                exit;
            }
        }
    }
    
    public function handle_export_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('llms_export_settings', 'llms_export_nonce');
        
        $settings = get_option('llms_generator_settings', array());
        
        // Add plugin version for compatibility checks
        $export_data = array(
            'plugin_version' => LLMS_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'settings' => $settings
        );
        
        $filename = 'wp-llms-txt-settings-' . date('Y-m-d-His') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    public function handle_import_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('llms_import_settings', 'llms_import_nonce');
        
        // Check if file was uploaded
        if (!isset($_FILES['llms_import_file']) || $_FILES['llms_import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_safe_redirect(add_query_arg(array(
                'page' => 'llms-file-manager',
                'error' => 'import_file_error'
            ), admin_url('admin.php')));
            exit;
        }
        
        $file = $_FILES['llms_import_file'];
        
        // Validate file type
        if ($file['type'] !== 'application/json' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json') {
            wp_safe_redirect(add_query_arg(array(
                'page' => 'llms-file-manager',
                'error' => 'import_invalid_file'
            ), admin_url('admin.php')));
            exit;
        }
        
        // Validate file size (1MB max)
        if ($file['size'] > 1048576) {
            wp_safe_redirect(add_query_arg(array(
                'page' => 'llms-file-manager',
                'error' => 'import_file_too_large'
            ), admin_url('admin.php')));
            exit;
        }
        
        // Read and parse file
        $json_content = file_get_contents($file['tmp_name']);
        $import_data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($import_data)) {
            wp_safe_redirect(add_query_arg(array(
                'page' => 'llms-file-manager',
                'error' => 'import_invalid_json'
            ), admin_url('admin.php')));
            exit;
        }
        
        // Validate structure
        if (!isset($import_data['settings']) || !is_array($import_data['settings'])) {
            wp_safe_redirect(add_query_arg(array(
                'page' => 'llms-file-manager',
                'error' => 'import_invalid_format'
            ), admin_url('admin.php')));
            exit;
        }
        
        // Backup current settings
        $current_settings = get_option('llms_generator_settings', array());
        update_option('llms_generator_settings_backup', $current_settings);
        
        // Import settings
        $new_settings = $this->sanitize_settings($import_data['settings']);
        update_option('llms_generator_settings', $new_settings);
        
        // Clear cache after import
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'] . '/llms.txt';
        if (file_exists($upload_path)) {
            unlink($upload_path);
        }
        
        // Schedule regeneration
        wp_clear_scheduled_hook('llms_update_llms_file_cron');
        wp_schedule_single_event(time() + 2, 'llms_update_llms_file_cron');
        
        // Redirect with success message
        wp_safe_redirect(add_query_arg(array(
            'page' => 'llms-file-manager',
            'import_success' => 'true',
            '_wpnonce' => wp_create_nonce('llms_import_success')
        ), admin_url('admin.php')));
        exit;
    }
}