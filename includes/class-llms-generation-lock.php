<?php
/**
 * Generation Lock Manager
 * Prevents race conditions between REST API and cron jobs
 */

if (!defined('ABSPATH')) {
    exit;
}

class LLMS_Generation_Lock {
    
    private const LOCK_TIMEOUT = 300; // 5 minutes
    
    /**
     * Acquire a generation lock
     * @param string $progress_id Progress ID to lock
     * @return bool True if lock acquired, false if already locked
     */
    public static function acquire(string $progress_id): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'llms_txt_progress';
        
        // Try to acquire lock using atomic database operation
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} 
             SET status = 'locked', 
                 updated_at = NOW() 
             WHERE id = %s 
             AND (status != 'locked' 
                  OR TIMESTAMPDIFF(SECOND, updated_at, NOW()) > %d)",
            $progress_id,
            self::LOCK_TIMEOUT
        ));
        
        return $result > 0;
    }
    
    /**
     * Release a generation lock
     * @param string $progress_id Progress ID to unlock
     * @param string $new_status New status to set
     */
    public static function release(string $progress_id, string $new_status = 'pending'): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'llms_txt_progress';
        
        $wpdb->update(
            $table,
            [
                'status' => $new_status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $progress_id],
            ['%s', '%s'],
            ['%s']
        );
    }
    
    /**
     * Check if a lock is active
     * @param string $progress_id Progress ID to check
     * @return bool True if locked and not expired
     */
    public static function is_locked(string $progress_id): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'llms_txt_progress';
        
        $lock = $wpdb->get_row($wpdb->prepare(
            "SELECT status, updated_at 
             FROM {$table} 
             WHERE id = %s 
             AND status = 'locked'",
            $progress_id
        ));
        
        if (!$lock) {
            return false;
        }
        
        // Check if lock is expired
        $lock_time = strtotime($lock->updated_at);
        $now = time();
        
        return ($now - $lock_time) < self::LOCK_TIMEOUT;
    }
    
    /**
     * Clean up stale locks
     */
    public static function cleanup_stale_locks(): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'llms_txt_progress';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} 
             SET status = 'cancelled' 
             WHERE status IN ('locked', 'running', 'starting') 
             AND TIMESTAMPDIFF(SECOND, updated_at, NOW()) > %d",
            self::LOCK_TIMEOUT
        ));
    }
}