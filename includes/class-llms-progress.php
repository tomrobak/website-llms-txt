<?php
/**
 * LLMS Progress Class - Modern PHP 8.3+ Implementation
 * 
 * Handles progress tracking for long-running operations with type safety
 * 
 * @package WP_LLMs_txt
 * @since 2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LLMS_Progress {
    /**
     * Current progress data
     */
    private static array $progress = [];
    
    /**
     * Initialize progress tracking
     */
    public function __construct() {
        add_action('wp_ajax_llms_get_progress', [$this, 'ajax_get_progress']);
        add_action('wp_ajax_nopriv_llms_get_progress', [$this, 'ajax_get_progress_nopriv']);
    }
    
    /**
     * Set progress for current operation
     */
    public static function set_progress(string $operation, int $current, int $total, string $message = ''): void {
        $progress_data = [
            'operation' => $operation,
            'current' => $current,
            'total' => $total,
            'percentage' => ($total > 0) ? round(($current / $total) * 100) : 0,
            'message' => $message,
            'timestamp' => current_time('timestamp')
        ];
        
        // Store in transient for 5 minutes
        set_transient('llms_progress_' . get_current_user_id(), $progress_data, 5 * MINUTE_IN_SECONDS);
    }
    
    /**
     * Clear progress data
     */
    public static function clear_progress(): void {
        delete_transient('llms_progress_' . get_current_user_id());
    }
    
    /**
     * AJAX handler for getting progress
     */
    public function ajax_get_progress(): void {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        check_ajax_referer('llms_progress_nonce', 'nonce');
        
        $progress = get_transient('llms_progress_' . get_current_user_id());
        
        if (false === $progress) {
            wp_send_json_success([
                'status' => 'idle',
                'message' => __('No operation in progress', 'wp-llms-txt')
            ]);
        }
        
        // Check if progress is stale (older than 2 minutes)
        if (isset($progress['timestamp']) && (current_time('timestamp') - $progress['timestamp']) > 120) {
            self::clear_progress();
            wp_send_json_success([
                'status' => 'completed',
                'message' => __('Operation completed or timed out', 'wp-llms-txt')
            ]);
        }
        
        $progress['status'] = 'in_progress';
        wp_send_json_success($progress);
    }
    
    /**
     * AJAX handler for non-logged in users (should not have access)
     */
    public function ajax_get_progress_nopriv() {
        wp_send_json_error('Not authorized');
    }
}

// Initialize progress tracking
new LLMS_Progress();