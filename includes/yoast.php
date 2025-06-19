<?php
/**
 * Yoast SEO Integration - Modern PHP 8.3+ Implementation
 * 
 * @package WP_LLMs_txt
 * @since 2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LLMS_Yoast_Integration {
    private function __construct() {
        add_action('init', [$this, 'add_rewrite_rules'], 5);
        add_action('template_redirect', [$this, 'maybe_generate_sitemap']);
        add_filter('wpseo_sitemap_index', [$this, 'add_to_index']);
        add_filter('wpseo_sitemap_llms_content', [$this, 'generate_sitemap']);
        add_action('llms_clear_seo_caches', [$this, 'clear_sitemap_cache']);
        add_filter('query_vars', [$this, 'query_vars']);
    }

    public function query_vars(array $vars): array {
        $vars[] = 'sitemap';
        return $vars;
    }

    public function add_rewrite_rules(): void {
        global $wp_rewrite;
        $existing_rules = $wp_rewrite->wp_rewrite_rules();
        if (!isset($existing_rules['^llms-sitemap\.xml$'])) {
            add_rewrite_rule('^llms-sitemap\.xml$', 'index.php?sitemap=llms', 'top');
        }
    }

    public function maybe_generate_sitemap(): void {
    	$sitemap = get_query_var('sitemap');
    	if ($sitemap === 'llms') {
            status_header(200);
        	header('Content-Type: application/xml; charset=utf-8');
        	echo $this->generate_sitemap();
        	exit;
    	}
	}

    public function generate_sitemap(): string {
        $latest_post = get_posts([
            'post_type' => 'llms_txt',
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ]);

        if (empty($latest_post)) {
            return '';
        }

        $url = [
            'loc' => home_url('/llms.txt'),
            'lastmod' => get_post_modified_time('c', true, $latest_post[0]),
            'changefreq' => 'weekly',
            'priority' => '0.8'
        ];

        $xsl_url = esc_url(home_url('main-sitemap.xsl'));
        $loc = esc_url($url['loc']);
        $lastmod = esc_xml($url['lastmod']);
        $changefreq = esc_xml($url['changefreq']);
        $priority = esc_xml($url['priority']);

        return <<<SEO
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="{$xsl_url}"?>
<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
    <url>
        <loc>{$loc}</loc>
        <lastmod>{$lastmod}</lastmod>
        <changefreq>{$changefreq}</changefreq>
        <priority>{$priority}</priority>
    </url>
</urlset>
SEO;
    }

    public function add_to_index($sitemap) {
        $latest_post = get_posts([
            'post_type' => 'llms_txt',
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ]);

        if (!empty($latest_post)) {
            $entry = "\n<sitemap>";
            $entry .= "\n\t<loc>" . esc_url(home_url('llms-sitemap.xml')) . "</loc>";
            $entry .= "\n\t<lastmod>" . esc_xml(get_post_modified_time('c', true, $latest_post[0])) . "</lastmod>";
            $entry .= "\n</sitemap>\n";
            return $sitemap . $entry;
        }

        return $sitemap;
    }

    public function clear_sitemap_cache() {
        do_action('wpseo_cache_clear_sitemap');
    }

    public static function get_instance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }
}

LLMS_Yoast_Integration::get_instance();