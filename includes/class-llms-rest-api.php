<?php
/**
 * LLMS REST API Handler
 * 
 * Centralizes REST API registration to ensure proper timing
 * 
 * @package WP_LLMs_txt
 * @since 2.1.2
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LLMS_REST_API {
    private static ?self $instance = null;
    
    public static function init(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register REST routes at the correct time
        add_action('rest_api_init', [$this, 'register_routes'], 10);
    }
    
    /**
     * Register all REST routes
     */
    public function register_routes(): void {
        // Progress endpoint
        register_rest_route('wp-llms-txt/v1', '/progress/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_progress'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Logs endpoints
        register_rest_route('wp-llms-txt/v1', '/logs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_logs'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'last_id' => [
                        'default' => 0,
                        'sanitize_callback' => 'absint'
                    ],
                    'level' => [
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'limit' => [
                        'default' => 50,
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'clear_logs'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);
        
        // Cancel progress endpoint
        register_rest_route('wp-llms-txt/v1', '/progress/(?P<id>[a-zA-Z0-9_-]+)/cancel', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'cancel_progress'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Test endpoint
        register_rest_route('wp-llms-txt/v1', '/test', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function() {
                return new WP_REST_Response(['status' => 'ok', 'message' => 'REST API is working'], 200);
            },
            'permission_callback' => '__return_true'
        ]);
        
        // Start generation endpoint
        register_rest_route('wp-llms-txt/v1', '/generate/start', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'start_generation'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }
    
    /**
     * Permission check
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Get progress
     */
    public function get_progress(WP_REST_Request $request): WP_REST_Response {
        $logger = llms_get_logger();
        if ($logger && method_exists($logger, 'rest_get_progress')) {
            return $logger->rest_get_progress($request);
        }
        
        return new WP_REST_Response(['error' => 'Logger not available'], 500);
    }
    
    /**
     * Get logs
     */
    public function get_logs(WP_REST_Request $request): WP_REST_Response {
        $logger = llms_get_logger();
        if ($logger && method_exists($logger, 'rest_get_logs')) {
            return $logger->rest_get_logs($request);
        }
        
        return new WP_REST_Response(['error' => 'Logger not available'], 500);
    }
    
    /**
     * Clear logs
     */
    public function clear_logs(): WP_REST_Response {
        $logger = llms_get_logger();
        if ($logger && method_exists($logger, 'rest_clear_logs')) {
            return $logger->rest_clear_logs();
        }
        
        return new WP_REST_Response(['error' => 'Logger not available'], 500);
    }
    
    /**
     * Cancel progress
     */
    public function cancel_progress(WP_REST_Request $request): WP_REST_Response {
        $logger = llms_get_logger();
        if ($logger && method_exists($logger, 'rest_cancel_progress')) {
            return $logger->rest_cancel_progress($request);
        }
        
        return new WP_REST_Response(['error' => 'Logger not available'], 500);
    }
    
    /**
     * Start generation process
     */
    public function start_generation(WP_REST_Request $request): WP_REST_Response {
        // Get progress ID from transient or create one
        $progress_id = get_transient('llms_current_progress_id');
        if (!$progress_id) {
            // Create new progress ID if none exists
            $progress_id = 'api_generation_' . time();
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
        }
        
        // Check if already running
        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}llms_txt_progress WHERE id = %s",
            $progress_id
        ));
        
        if ($status === 'running') {
            return new WP_REST_Response(['error' => 'Generation already running', 'progress_id' => $progress_id], 409);
        }
        
        // Get generator instance
        $generator = new LLMS_Generator();
        
        // Set max execution time for long operations
        @set_time_limit(300);
        
        // Run generation
        try {
            $generator->update_llms_file();
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Generation completed',
                'progress_id' => $progress_id
            ], 200);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'error' => 'Generation failed: ' . $e->getMessage(),
                'progress_id' => $progress_id
            ], 500);
        }
    }
}