<?php
/**
 * LLMS Logger Class
 * 
 * Handles logging and progress tracking for file generation
 * 
 * @package WP_LLMs_txt
 * @since 2.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LLMS_Logger {
    private string $progress_id = '';
    private float $start_time;
    private int $start_memory;
    
    public function __construct() {
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage();
        
        // REST API routes are now registered in LLMS_REST_API class
    }
    
    /**
     * Start a new progress tracking session
     */
    public function start_progress(string $id, int $total_items): void {
        global $wpdb;
        
        $this->progress_id = $id;
        
        $wpdb->replace(
            $wpdb->prefix . 'llms_txt_progress',
            [
                'id' => $id,
                'status' => 'running',
                'total_items' => $total_items,
                'current_item' => 0,
                'started_at' => current_time('mysql'),
                'memory_peak' => memory_get_peak_usage()
            ],
            ['%s', '%s', '%d', '%d', '%s', '%d']
        );
        
        $this->log('INFO', sprintf('Started %s with %d items to process', $id, $total_items));
    }
    
    /**
     * Update progress
     */
    public function update_progress(int $current, ?int $post_id = null, ?string $post_title = null): void {
        global $wpdb;
        
        if (empty($this->progress_id)) {
            return;
        }
        
        $data = [
            'current_item' => $current,
            'memory_peak' => memory_get_peak_usage(),
            'updated_at' => current_time('mysql')
        ];
        
        $format = ['%d', '%d', '%s'];
        
        if ($post_id !== null) {
            $data['current_post_id'] = $post_id;
            $format[] = '%d';
        }
        
        if ($post_title !== null) {
            $data['current_post_title'] = $post_title;
            $format[] = '%s';
        }
        
        $wpdb->update(
            $wpdb->prefix . 'llms_txt_progress',
            $data,
            ['id' => $this->progress_id],
            $format,
            ['%s']
        );
    }
    
    /**
     * Complete progress
     */
    public function complete_progress(string $status = 'completed'): void {
        global $wpdb;
        
        if (empty($this->progress_id)) {
            return;
        }
        
        $wpdb->update(
            $wpdb->prefix . 'llms_txt_progress',
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
                'memory_peak' => memory_get_peak_usage()
            ],
            ['id' => $this->progress_id],
            ['%s', '%s', '%d'],
            ['%s']
        );
        
        $execution_time = microtime(true) - $this->start_time;
        $this->log('INFO', sprintf('Completed %s in %.2f seconds', $this->progress_id, $execution_time));
    }
    
    /**
     * Log a message
     */
    public function log(string $level, string $message, ?array $context = null, ?int $post_id = null): void {
        global $wpdb;
        
        $execution_time = microtime(true) - $this->start_time;
        
        $wpdb->insert(
            $wpdb->prefix . 'llms_txt_logs',
            [
                'level' => $level,
                'message' => $message,
                'context' => $context ? json_encode($context) : null,
                'post_id' => $post_id,
                'memory_usage' => memory_get_usage(),
                'execution_time' => $execution_time,
                'timestamp' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%d', '%f', '%s']
        );
        
        // Update error/warning counts in progress
        if (!empty($this->progress_id) && in_array($level, ['ERROR', 'WARNING'])) {
            $field = $level === 'ERROR' ? 'errors' : 'warnings';
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}llms_txt_progress SET $field = $field + 1 WHERE id = %s",
                $this->progress_id
            ));
        }
    }
    
    /**
     * Convenience methods
     */
    public function info(string $message, ?array $context = null, ?int $post_id = null): void {
        $this->log('INFO', $message, $context, $post_id);
    }
    
    public function warning(string $message, ?array $context = null, ?int $post_id = null): void {
        $this->log('WARNING', $message, $context, $post_id);
    }
    
    public function error(string $message, ?array $context = null, ?int $post_id = null): void {
        $this->log('ERROR', $message, $context, $post_id);
    }
    
    public function debug(string $message, ?array $context = null, ?int $post_id = null): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log('DEBUG', $message, $context, $post_id);
        }
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        register_rest_route('wp-llms-txt/v1', '/progress/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_get_progress'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        register_rest_route('wp-llms-txt/v1', '/logs', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_get_logs'],
            'permission_callback' => [$this, 'rest_permission_check'],
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
        ]);
        
        register_rest_route('wp-llms-txt/v1', '/logs', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'rest_clear_logs'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        register_rest_route('wp-llms-txt/v1', '/progress/(?P<id>[a-zA-Z0-9_-]+)/cancel', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_cancel_progress'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }
    
    /**
     * REST API permission check
     */
    public function rest_permission_check(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * REST endpoint for getting progress
     */
    public function rest_get_progress(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        
        $progress_id = $request->get_param('id');
        
        $progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}llms_txt_progress WHERE id = %s",
            $progress_id
        ), ARRAY_A);
        
        if (!$progress) {
            return new WP_REST_Response(['error' => 'Progress not found'], 404);
        }
        
        // Calculate percentage
        $progress['percentage'] = $progress['total_items'] > 0 
            ? round(($progress['current_item'] / $progress['total_items']) * 100) 
            : 0;
        
        // Format memory
        $progress['memory_peak_formatted'] = size_format($progress['memory_peak']);
        
        // Calculate elapsed time
        $started = strtotime($progress['started_at']);
        $elapsed = time() - $started;
        $progress['elapsed_time'] = $this->format_time($elapsed);
        
        // Estimate remaining time
        if ($progress['current_item'] > 0 && $progress['status'] === 'running') {
            $per_item = $elapsed / $progress['current_item'];
            $remaining = ($progress['total_items'] - $progress['current_item']) * $per_item;
            $progress['estimated_remaining'] = $this->format_time((int)$remaining);
        } else {
            $progress['estimated_remaining'] = null;
        }
        
        return new WP_REST_Response($progress, 200);
    }
    
    /**
     * REST endpoint for getting logs
     */
    public function rest_get_logs(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        
        $last_id = $request->get_param('last_id');
        $level = $request->get_param('level');
        $limit = min(100, $request->get_param('limit'));
        
        $where = "id > %d";
        $params = [$last_id];
        
        if ($level && $level !== 'ALL') {
            $where .= " AND level = %s";
            $params[] = $level;
        }
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}llms_txt_logs 
            WHERE $where 
            ORDER BY id DESC 
            LIMIT %d",
            array_merge($params, [$limit])
        ), ARRAY_A);
        
        // Format logs
        foreach ($logs as &$log) {
            $log['memory_formatted'] = size_format($log['memory_usage']);
            $log['time_formatted'] = number_format($log['execution_time'], 2) . 's';
            if ($log['context']) {
                $log['context'] = json_decode($log['context'], true);
            }
        }
        
        return new WP_REST_Response([
            'logs' => array_reverse($logs),
            'has_more' => count($logs) === $limit
        ], 200);
    }
    
    /**
     * REST endpoint for clearing logs
     */
    public function rest_clear_logs(): WP_REST_Response {
        global $wpdb;
        
        // Keep only last 24 hours of logs
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}llms_txt_logs 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        return new WP_REST_Response([
            'deleted' => $wpdb->rows_affected,
            'message' => 'Logs older than 24 hours have been deleted'
        ], 200);
    }
    
    /**
     * REST endpoint for cancelling progress
     */
    public function rest_cancel_progress(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        
        $progress_id = $request->get_param('id');
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'llms_txt_progress',
            [
                'status' => 'cancelled',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $progress_id],
            ['%s', '%s'],
            ['%s']
        );
        
        if ($updated === false) {
            return new WP_REST_Response(['error' => 'Failed to cancel progress'], 500);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Progress cancelled successfully'
        ], 200);
    }
    
    /**
     * Format time in human readable format
     */
    private function format_time(int $seconds): string {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . ' minutes';
        } else {
            return floor($seconds / 3600) . ' hours ' . floor(($seconds % 3600) / 60) . ' minutes';
        }
    }
    
    /**
     * Clean old logs
     */
    public function clean_old_logs(): void {
        global $wpdb;
        
        // Delete logs older than 7 days
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}llms_txt_logs 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Delete completed progress older than 24 hours
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}llms_txt_progress 
            WHERE status != 'running' AND updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
}