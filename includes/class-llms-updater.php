<?php
/**
 * LLMS Updater Class - Modern PHP 8.3+ Implementation
 * 
 * WordPress Auto-Updater for WP LLMs.txt with GitHub integration
 * Checks GitHub releases for updates and handles WordPress update notifications
 * 
 * @package WP_LLMs_txt
 * @since 2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LLMS_Updater {
    private readonly string $plugin_slug;
    private readonly string $plugin_file;
    private readonly string $version;
    private readonly string $github_repo;
    private readonly string $github_api_url;
    
    public function __construct(string $plugin_file, string $github_repo) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = LLMS_VERSION;
        $this->github_repo = $github_repo;
        $this->github_api_url = "https://api.github.com/repos/{$github_repo}/releases/latest";
        
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('site_transient_update_plugins', [$this, 'update_available']);
        add_action('upgrader_process_complete', [$this, 'after_update'], 10, 2);
        
        // Add custom update message
        add_action('in_plugin_update_message-' . $this->plugin_slug, [$this, 'update_message'], 10, 2);
        
        // Add "Check for updates" link
        add_filter('plugin_action_links_' . $this->plugin_slug, [$this, 'add_check_update_link']);
    }
    
    /**
     * Add "Check for updates" link to plugin actions
     */
    public function add_check_update_link(array $links): array {
        $check_update_link = sprintf(
            '<a href="%s" class="llms-check-updates">%s</a>',
            wp_nonce_url(admin_url('admin.php?page=llms-file-manager&action=check_updates'), 'llms_check_updates'),
            __('Check for Updates', 'wp-llms-txt')
        );
        
        array_unshift($links, $check_update_link);
        return $links;
    }
    
    /**
     * Get release information from GitHub API
     */
    private function get_github_release_info(): ?array {
        $transient_key = 'llms_github_release_' . md5($this->github_repo);
        $cached_info = get_transient($transient_key);
        
        if ($cached_info !== false) {
            return $cached_info;
        }
        
        $response = wp_remote_get($this->github_api_url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'WP-LLMs-txt-Plugin/' . $this->version . '; ' . home_url(),
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('[WP LLMs.txt] GitHub API request failed: ' . $response->get_error_message());
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('[WP LLMs.txt] GitHub API returned status code: ' . $response_code);
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $release_data = json_decode($body, true);
        
        if (!$release_data || !isset($release_data['tag_name'])) {
            error_log('[WP LLMs.txt] Invalid GitHub release data received');
            return null;
        }
        
        // Process release data into our format
        $processed_data = [
            'version' => ltrim($release_data['tag_name'], 'v'),
            'download_url' => $this->get_download_url($release_data),
            'release_notes' => $release_data['body'] ?? '',
            'published_at' => $release_data['published_at'] ?? '',
            'prerelease' => $release_data['prerelease'] ?? false,
            'requires_php' => '8.3',
            'requires_wp' => '6.7',
            'tested_up_to' => '6.7'
        ];
        
        // Cache for 12 hours
        set_transient($transient_key, $processed_data, 12 * HOUR_IN_SECONDS);
        
        return $processed_data;
    }
    
    /**
     * Get the download URL for the plugin zip file
     */
    private function get_download_url(array $release_data): string {
        // Look for assets with .zip extension first
        if (isset($release_data['assets']) && is_array($release_data['assets'])) {
            foreach ($release_data['assets'] as $asset) {
                if (str_ends_with($asset['name'], '.zip') && str_contains($asset['name'], 'wp-llms-txt')) {
                    return $asset['browser_download_url'];
                }
            }
        }
        
        // Fallback to zipball_url
        return $release_data['zipball_url'] ?? '';
    }
    
    /**
     * Check if update is available
     */
    public function update_available(object $transient): object {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_info = $this->get_github_release_info();
        
        if (!$remote_info || !isset($remote_info['version'])) {
            return $transient;
        }
        
        // Skip prereleases unless explicitly enabled
        if ($remote_info['prerelease'] && !defined('LLMS_ALLOW_PRERELEASES')) {
            return $transient;
        }
        
        if (version_compare($this->version, $remote_info['version'], '<')) {
            $transient->response[$this->plugin_slug] = (object) [
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_info['version'],
                'tested' => $remote_info['tested_up_to'],
                'requires_php' => $remote_info['requires_php'],
                'requires' => $remote_info['requires_wp'],
                'url' => "https://github.com/{$this->github_repo}",
                'package' => $remote_info['download_url'],
                'icons' => [
                    '1x' => 'https://cdn-icons-png.flaticon.com/512/2103/2103658.png',
                    '2x' => 'https://cdn-icons-png.flaticon.com/512/2103/2103658.png'
                ],
                'banners' => [],
                'banners_rtl' => [],
                'compatibility' => []
            ];
        }
        
        return $transient;
    }
    
    /**
     * Get plugin information for update popup
     */
    public function plugin_info(mixed $res, string $action, object $args): mixed {
        if ($action !== 'plugin_information') {
            return $res;
        }
        
        if ($args->slug !== dirname($this->plugin_slug)) {
            return $res;
        }
        
        $remote_info = $this->get_github_release_info();
        
        if (!$remote_info) {
            return $res;
        }
        
        $res = (object) [
            'name' => 'WP LLMs.txt',
            'slug' => dirname($this->plugin_slug),
            'version' => $remote_info['version'],
            'tested' => $remote_info['tested_up_to'],
            'requires' => $remote_info['requires_wp'],
            'requires_php' => $remote_info['requires_php'],
            'author' => '<a href="https://github.com/' . $this->github_repo . '">Tom Robak</a>',
            'author_profile' => 'https://wplove.co',
            'last_updated' => $remote_info['published_at'],
            'homepage' => 'https://github.com/' . $this->github_repo,
            'short_description' => 'Make your WordPress site AI-friendly with automatic LLMS.txt generation for ChatGPT, Claude, and other AI systems.',
            'sections' => [
                'Description' => $this->get_plugin_description(),
                'Changelog' => $this->format_release_notes($remote_info['release_notes']),
                'Installation' => $this->get_installation_instructions()
            ],
            'download_link' => $remote_info['download_url'],
            'trunk' => $remote_info['download_url'],
            'icons' => [
                '1x' => 'https://cdn-icons-png.flaticon.com/512/2103/2103658.png',
                '2x' => 'https://cdn-icons-png.flaticon.com/512/2103/2103658.png'
            ],
            'banners' => [],
            'banners_rtl' => [],
        ];
        
        return $res;
    }
    
    /**
     * Get plugin description for the update popup
     */
    private function get_plugin_description(): string {
        return '<h3>🤖 Make Your WordPress Site AI-Discoverable!</h3>
        
        <p><strong>WP LLMs.txt</strong> automatically generates LLMS.txt files that help AI systems like ChatGPT, Claude, and Perplexity discover and understand your website content.</p>
        
        <h4>✨ What it does:</h4>
        <ul>
            <li>🚀 <strong>Automatic Generation</strong> - Creates LLMS.txt files without manual work</li>
            <li>🔄 <strong>Real-time Updates</strong> - Syncs with your content changes automatically</li>
            <li>🛠️ <strong>SEO Integration</strong> - Works with Yoast SEO and RankMath</li>
            <li>⚡ <strong>Performance Optimized</strong> - Cached generation for fast loading</li>
            <li>🎯 <strong>Content Control</strong> - Choose what content to include</li>
            <li>📱 <strong>Modern UI</strong> - Beautiful, intuitive admin interface</li>
        </ul>
        
        <h4>🎉 Why your AI overlords will love it:</h4>
        <p>This plugin speaks fluent AI - it formats your content exactly how language models prefer to consume it. No more being invisible to the robots! 🤖✨</p>';
    }
    
    /**
     * Format release notes for display
     */
    private function format_release_notes(string $release_notes): string {
        if (empty($release_notes)) {
            return '<p>Latest updates and improvements.</p>';
        }
        
        // Convert markdown to HTML basics
        $formatted = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $release_notes);
        $formatted = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $formatted);
        $formatted = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $formatted);
        $formatted = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $formatted);
        $formatted = nl2br($formatted);
        
        return $formatted;
    }
    
    /**
     * Get installation instructions
     */
    private function get_installation_instructions(): string {
        return '<h4>📥 Installation</h4>
        <ol>
            <li>Click "Update Now" to download the latest version</li>
            <li>WordPress will automatically replace the old version</li>
            <li>Your settings and generated files will be preserved</li>
            <li>Visit the plugin admin page to verify everything works</li>
        </ol>
        
        <h4>🔧 Requirements</h4>
        <ul>
            <li>PHP 8.3 or higher</li>
            <li>WordPress 6.7 or higher</li>
            <li>Write permissions in wp-content/uploads/</li>
        </ul>';
    }
    
    /**
     * Display custom update message
     */
    public function update_message(array $plugin_data, object $response): void {
        echo '<div class="llms-update-message" style="margin-top: 10px; padding: 10px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px;">';
        echo '<p><strong>🎉 New version available!</strong> This update includes important improvements and bug fixes.</p>';
        echo '<p><em>Your settings and generated files will be preserved during the update.</em></p>';
        echo '</div>';
    }
    
    /**
     * Handle post-update actions
     */
    public function after_update(object $upgrader, array $options): void {
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }
        
        if (!isset($options['plugins']) || !is_array($options['plugins'])) {
            return;
        }
        
        if (in_array($this->plugin_slug, $options['plugins'])) {
            // Clear any cached update info
            delete_transient('llms_github_release_' . md5($this->github_repo));
            
            // Clear plugin caches
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // Trigger cache clearing
            do_action('llms_clear_seo_caches');
            
            // Log successful update
            error_log('[WP LLMs.txt] Plugin updated to version ' . LLMS_VERSION);
        }
    }
    
    /**
     * Force check for updates (called manually)
     */
    public function force_check_for_updates(): ?array {
        // Clear cached data
        delete_transient('llms_github_release_' . md5($this->github_repo));
        
        // Get fresh data
        return $this->get_github_release_info();
    }
}