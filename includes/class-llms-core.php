<?php
if (!defined('ABSPATH')) {
    exit;
}

class LLMS_Core {
    /** @var LLMS_Generator */
    private $generator;

    public function __construct()
    {
        // Register activation hook
        register_activation_hook(LLMS_PLUGIN_FILE, array($this, 'activate'));

        // Initialize core functionality
        add_action('init', array($this, 'init'), 0);

        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('plugin_action_links_' . plugin_basename(LLMS_PLUGIN_FILE), array($this, 'add_settings_link'));

        // Handle cache clearing
        add_action('admin_post_clear_caches', array($this, 'handle_cache_clearing'));
        add_action('admin_post_clear_error_log', array($this, 'handle_clear_error_log'));
        
        // Handle import/export
        add_action('admin_post_llms_export_settings', array($this, 'handle_export_settings'));
        add_action('admin_post_llms_import_settings', array($this, 'handle_import_settings'));

        // Initialize SEO integrations before post type registration
        add_action('init', array($this, 'init_seo_integrations'), -1);

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add required scripts for admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        add_action('wp_head', array($this, 'wp_head'));

        add_action('all_admin_notices', array($this, 'all_admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_notice_script'));
        add_action('wp_ajax_dismiss_llms_admin_notice', array($this, 'dismiss_llms_admin_notice'));
    }

    public function all_admin_notices() {
        if (get_user_meta(get_current_user_id(), 'llms_notice_dismissed', true)) {
            return;
        }
        ?>
        <div class="notice updated is-dismissible llms-admin-notice">
            <p><?php _e('WP LLMs.txt - Want new features? Suggest and vote to shape our plugin development roadmap.', 'wp-llms-txt'); ?>
                <a href="https://x.com/ryhowww/status/1909712881387462772" target="_blank">Twitter</a> |
                <a href="https://wordpress.org/support/?post_type=topic&p=18406423">WP Forums</a>
            </p>
        </div>
        <?php
    }

    public function enqueue_notice_script() {
        wp_enqueue_script('llms-notice-script', LLMS_PLUGIN_URL . 'admin/notice-dismiss.js', array('jquery'), LLMS_VERSION, true);
        wp_localize_script('llms-notice-script', 'llmsNoticeAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('llms_dismiss_notice')
        ));
    }

    public function dismiss_llms_admin_notice() {
        // Security check: ensure user has proper capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        check_ajax_referer('llms_dismiss_notice', 'nonce');
        update_user_meta(get_current_user_id(), 'llms_notice_dismissed', 1);
        wp_send_json_success();
    }

    public function wp_head() {
        echo '<link rel="llms-sitemap" href="' . esc_url( home_url( '/llms.txt' ) ) . '" />' . "\n";
    }

    public function get_llms_post() {
        $posts = get_posts(array(
            'post_type' => 'llms_txt',
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));

        return !empty($posts) ? $posts[0] : null;
    }

    public function init() {
        // Register post type
        $this->create_post_type();
        // Initialize generator after post type
        require_once LLMS_PLUGIN_DIR . 'includes/class-llms-generator.php';
        $this->generator = new LLMS_Generator();

        // Add rewrite rules
        $this->add_rewrite_rule();
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_llms_request'));
    }

    public function create_post_type() {
        register_post_type('llms_txt', array(
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
            'supports' => array('title', 'editor'),
            'exclude_from_sitemap' => true
        ));
    }

    public function init_seo_integrations() {
        if (class_exists('RankMath')) {
            require_once LLMS_PLUGIN_DIR . 'includes/class-llms-provider.php';
            require_once LLMS_PLUGIN_DIR . 'includes/rank-math.php';
        }

        if (defined('WPSEO_VERSION') && class_exists('WPSEO_Sitemaps')) {
            require_once LLMS_PLUGIN_DIR . 'includes/yoast.php';
        }
    }

    public function register_settings() {
        register_setting(
            'llms_generator_settings',
            'llms_generator_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array(
                    'post_types' => array('page', 'documentation', 'post'),
                    'max_posts' => 100,
                    'max_words' => 250,
                    'include_meta' => true,
                    'include_excerpts' => true,
                    'include_taxonomies' => true,
                    'update_frequency' => 'immediate',
                    'need_check_option' => true,
                )
            )
        );
    }

    public function sanitize_settings($value) {
        if (!is_array($value)) {
            add_settings_error(
                'llms_generator_settings',
                'invalid_data',
                __('Invalid settings data format.', 'wp-llms-txt'),
                'error'
            );
            return array();
        }
        $clean = array();
        
        // Ensure post_types is an array and contains only valid post types
        $clean['post_types'] = array();
        if (isset($value['post_types']) && is_array($value['post_types'])) {
            $valid_types = get_post_types(array('public' => true));
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

    public function enqueue_admin_scripts($hook) {
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

        // Register and enqueue admin script
        wp_register_script(
            'llms-admin-script',
            LLMS_PLUGIN_URL . 'admin/admin-script.js',
            array('jquery', 'jquery-ui-sortable'),
            LLMS_VERSION,
            true
        );

        wp_enqueue_script('llms-admin-script');
        
        // Enqueue progress script
        wp_enqueue_script(
            'llms-progress-script',
            LLMS_PLUGIN_URL . 'admin/progress.js',
            array('jquery'),
            LLMS_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('llms-progress-script', 'llmsProgress', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('llms_progress_nonce')
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

    public function activate() {
        flush_rewrite_rules();
    }

    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Llms.txt',
            'Llms.txt',
            'manage_options',
            'llms-file-manager',
            array($this, 'render_admin_page'),
            'dashicons-media-text'
        );
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=llms-file-manager">' . __('Settings', 'wp-llms-txt') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function render_admin_page() {
        include LLMS_PLUGIN_DIR . 'admin/admin-page.php';
    }

    public function handle_cache_clearing() {
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


        wp_safe_redirect(add_query_arg(array(
            'page' => 'llms-file-manager',
            'cache_cleared' => 'true',
            '_wpnonce' => wp_create_nonce('llms_cache_cleared')
        ), admin_url('admin.php')));
        exit;
    }
    
    public function handle_clear_error_log() {
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

    public function add_rewrite_rule() {
        global $wp_rewrite;

        if($wp_rewrite) {
            $wp_rewrite->add_rule('llms.txt', 'index.php?llms_txt=1', 'top');
        }
    }

    public function add_query_vars($vars) {
        $vars[] = 'llms_txt';
        return $vars;
    }

    public function handle_llms_request() {
        if (get_query_var('llms_txt')) {
            $latest_post = apply_filters('get_llms_content', '');
            if ($latest_post) {
                header('Content-Type: text/plain');
                echo esc_html($latest_post);
                exit;
            }
        }
    }
    
    public function handle_export_settings() {
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
    
    public function handle_import_settings() {
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