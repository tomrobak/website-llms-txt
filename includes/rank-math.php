<?php
/**
 * RankMath Integration - Modern PHP 8.3+ Implementation
 * 
 * @package WP_LLMs_txt
 * @since 2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Include the provider class
require_once plugin_dir_path(__FILE__) . 'class-llms-provider.php';

/**
 * Register the LLMS sitemap provider with Rank Math
 */
add_filter('rank_math/sitemap/providers', function(array $providers): array {
    // Only add provider if RankMath is available and class exists
    if (class_exists('LLMS_Sitemap_Provider')) {
        $providers['llms'] = new LLMS_Sitemap_Provider();
    }
    return $providers;
});

/**
 * Clear SEO plugin sitemap caches when LLMS.txt is updated
 */
add_action('llms_clear_seo_caches', function(): void {
    // Clear RankMath cache if active
    if (class_exists('\RankMath\Sitemap\Cache')) {
        \RankMath\Sitemap\Cache::invalidate_storage();
    }
    
    // Clear Yoast cache if active
    if (class_exists('WPSEO_Sitemaps_Cache')) {
        WPSEO_Sitemaps_Cache::clear();
    }
});

// Explicitly exclude from sitemap generation
add_filter('rank_math/sitemap/exclude_post_type', function(bool $exclude, string $post_type): bool {
    if ($post_type === 'llms_txt') {
        return true;
    }
    return $exclude;
}, 20, 2);