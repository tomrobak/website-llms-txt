<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Auto-Updater for WP LLMs.txt
 * Checks GitHub releases for updates and handles WordPress update notifications
 */
class LLMS_Updater {
    
    private $plugin_slug;
    private $plugin_file;
    private $version;
    private $github_repo;
    private $update_check_url;
    
    public function __construct($plugin_file, $github_repo) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = LLMS_VERSION;
        $this->github_repo = $github_repo;
        $this->update_check_url = "https://{$github_repo}.github.io/update-check.json";
        
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('site_transient_update_plugins', array($this, 'update_available'));
        add_action('upgrader_process_complete', array($this, 'after_update'), 10, 2);
        
        // Add custom update message
        add_action('in_plugin_update_message-' . $this->plugin_slug, array($this, 'update_message'), 10, 2);
    }
    
    /**
     * Check if update is available
     */
    public function update_available($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_info = $this->get_remote_info();
        
        if (!$remote_info || !isset($remote_info['version'])) {
            return $transient;
        }
        
        if (version_compare($this->version, $remote_info['version'], '<')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_info['version'],
                'tested' => $remote_info['tested'],
                'requires_php' => $remote_info['requires_php'],
                'url' => $remote_info['details_url'],
                'package' => $remote_info['download_url'],
                'icons' => array(
                    '1x' => 'https://cdn-icons-png.flaticon.com/512/2103/2103658.png',
                    '2x' => 'https://cdn-icons-png.flaticon.com/512/2103/2103658.png'
                ),
                'banners' => array(),
                'banners_rtl' => array(),
                'compatibility' => array()
            );
        }
        
        return $transient;
    }
    
    /**
     * Get plugin information for update popup
     */
    public function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information') {
            return $res;
        }
        
        if ($args->slug !== dirname($this->plugin_slug)) {
            return $res;
        }
        
        $remote_info = $this->get_remote_info();
        
        if (!$remote_info) {
            return $res;
        }
        
        $res = (object) array(
            'name' => 'WP LLMs.txt',
            'slug' => dirname($this->plugin_slug),
            'version' => $remote_info['version'],
            'tested' => $remote_info['tested'],
            'requires' => $remote_info['requires'],
            'requires_php' => $remote_info['requires_php'],
            'author' => '<a href="https://github.com/' . $this->github_repo . '">WP LLMs.txt Team</a>',
            'author_profile' => 'https://github.com/' . $this->github_repo,
            'last_updated' => date('Y-m-d'),
            'homepage' => 'https://github.com/' . $this->github_repo,
            'short_description' => 'Make your WordPress site AI-friendly with automatic llms.txt generation.',
            'sections' => array(
                'Description' => $remote_info['sections']['description'] ?? 'WP LLMs.txt - Make Your WordPress Site AI-Friendly',
                'Changelog' => $this->parse_changelog($remote_info['sections']['changelog'] ?? 'Latest updates and improvements.')
            ),
            'download_link' => $remote_info['download_url'],
            'trunk' => $remote_info['download_url'],
            'icons' => array(
                '1x' => 'https://cdn-icons-png.flaticon.com/512/2103/2103658.png',
                '2x' => 'https://cdn-icons-png.flaticon.com/512/2103/2103658.png'
            ),
            'banners' => array(),
            'banners_rtl' => array(),
        );
        
        return $res;
    }
    
    /**
     * Get remote version info from GitHub
     */
    private function get_remote_info() {
        $transient_key = 'llms_update_info';
        $cached_info = get_transient($transient_key);
        
        if ($cached_info !== false) {
            return $cached_info;
        }
        
        $request = wp_remote_get($this->update_check_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WP-LLMs-txt-Updater/' . $this->version
            )
        ));
        
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // Cache for 12 hours
        set_transient($transient_key, $data, 12 * HOUR_IN_SECONDS);
        
        return $data;
    }
    
    /**
     * Parse changelog for better display
     */
    private function parse_changelog($changelog) {
        if (empty($changelog)) {
            return 'No changelog available.';
        }
        
        // Convert markdown-style headers to HTML
        $changelog = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $changelog);
        $changelog = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $changelog);
        $changelog = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $changelog);
        
        // Convert bullet points
        $changelog = preg_replace('/^- (.+)$/m', '<li>$1</li>', $changelog);
        $changelog = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $changelog);
        
        // Convert line breaks
        $changelog = nl2br($changelog);
        
        return $changelog;
    }
    
    /**
     * Show custom update message
     */
    public function update_message($plugin_data, $response) {
        if (empty($response->package)) {
            return;
        }
        
        echo '<br><strong>🚀 New features and improvements available!</strong> ';
        echo 'This update includes enhanced AI compatibility and performance optimizations.';
    }
    
    /**
     * Clear update cache after successful update
     */
    public function after_update($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            if (isset($options['plugins']) && in_array($this->plugin_slug, $options['plugins'])) {
                delete_transient('llms_update_info');
            }
        }
    }
    
    /**
     * Force check for updates (for manual checking)
     */
    public function force_update_check() {
        delete_transient('llms_update_info');
        delete_site_transient('update_plugins');
        wp_update_plugins();
    }
}