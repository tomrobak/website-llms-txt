<?php
/**
 * LLMS Content Cleaner - Modern PHP 8.3+ Implementation
 *
 * Handles content cleaning with type safety and modern features
 *
 * @package WP_LLMs_txt
 * @since 2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LLMS_Content_Cleaner {
    /**
     * List of page builder patterns to clean
     */
    private array $builder_patterns;

    public function __construct()
    {
        $this->builder_patterns = [
            // Basic Layout Shortcodes
            '/\[([^\]]*)\](.*?)\[\/\1\]/s',  // Match any paired shortcodes with content
            '/\[[^\]]+\]/',                   // Match any single shortcodes
            '/\[row[^\]]*\].*?\[\/row\]/s',
            '/\[col[^\]]*\].*?\[\/col\]/s',
            '/\[column[s]?[^\]]*\].*?\[\/column[s]?\]/s',
            '/\[one[^\]]*\].*?\[\/one[^\]]*\]/s',
            '/\[two[^\]]*\].*?\[\/two[^\]]*\]/s',
            '/\[three[^\]]*\].*?\[\/three[^\]]*\]/s',
            '/\[four[^\]]*\].*?\[\/four[^\]]*\]/s',
            '/\[half[^\]]*\].*?\[\/half[^\]]*\]/s',
            '/\[third[^\]]*\].*?\[\/third[^\]]*\]/s',
            '/\[fourth[^\]]*\].*?\[\/fourth[^\]]*\]/s',
            '/\[quarters[^\]]*\].*?\[\/quarters[^\]]*\]/s',
            '/\[section[^\]]*\].*?\[\/section[^\]]*\]/s',
            '/\[container[^\]]*\].*?\[\/container[^\]]*\]/s',

            // Gutenberg Blocks
            '/<!-- wp:.*?\/-->/s',
            '/<!-- wp:.*?-->.*?<!-- \/wp:.*?-->/s',
            
            // WPBakery
            '/\[vc_[^\]]*\].*?\[\/vc_[^\]]*\]/s',
            
            // Elementor
            '/<!-- elementor.*?-->/s',
            '/data-elementor-[^\"]*=\"[^\"]*\"/',
            '/class=\"elementor-.*?\"/',
            
            // Beaver Builder
            '/<!-- wp:fl-builder\/layout.*?-->/s',
            '/class=\"fl-builder-content.*?\"/',
            
            // Divi
            '/\[et_pb_[^\]]*\].*?\[\/et_pb_[^\]]*\]/s',
            
            // Oxygen
            '/<!-- wp:oxygen.*?-->/s',
            '/\[oxygen.*?\].*?\[\/oxygen\]/s',
            
            // Common HTML cleanup
            '/class=\"[^\"]*\"/i',
            '/style=\"[^\"]*\"/i',
            '/id=\"[^\"]*\"/i',
            '/data-[^=]*=\"[^\"]*\"/i',
            
            // Extra HTML cleanup
            '/\s*<div[^>]*>\s*/',
            '/\s*<\/div>\s*/',
            '/\s*<span[^>]*>\s*/',
            '/\s*<\/span>\s*/'
        ];
    }

    /**
     * Clean content from a post
     */
    public function clean(string $content): string {
        if (empty($content)) {
            return '';
        }

        // Store original content
        $original_content = $content;

        // FIRST: Process shortcodes to get their content
        $content = do_shortcode($content);

        // SECOND: Remove any remaining raw shortcodes
        $content = preg_replace('/\[[^\]]*\]/', '', $content);

        // THIRD: Convert HTML entities early
        $content = html_entity_decode($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $content = str_replace(
            ['&hellip;', '&ndash;', '&mdash;', '&nbsp;', '…', '...', '&quot;', '&apos;', '&lt;', '&gt;', '&amp;'],
            ['...', '-', '-', ' ', '...', '...', '"', "'", '<', '>', '&'],
            $content
        );

        // FOURTH: Remove any remaining HTML entities
        $content = preg_replace('/&[a-z0-9#]+;/i', '', $content);

        // FIFTH: Clean up page builder content
        $content = $this->remove_page_builder_content($content);

        // SIXTH: Clean WordPress-specific content
        $content = $this->clean_wordpress_content($content);

        // SEVENTH: Final cleanup
        $content = $this->final_cleanup($content);

        // If content is completely empty after cleaning, return a portion of original
        if (empty(trim($content))) {
            return wp_trim_words(strip_tags($original_content), 20, '...');
        }

        return $content;
    }

    /**
     * Remove page builder specific content
     */
    private function remove_page_builder_content(string $content): string {
        foreach ($this->builder_patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }
        return $content;
    }

    /**
     * Clean WordPress-specific content
     */
    private function clean_wordpress_content(string $content): string {
        // Remove WordPress gallery shortcodes but keep content
        $content = preg_replace('/\[gallery[^\]]*\]/', '', $content);
        
        // Remove caption shortcodes but keep content
        $content = preg_replace('/\[caption[^\]]*\](.*?)\[\/caption\]/s', '$1', $content);
        
        // Remove WordPress blocks
        $content = preg_replace('/<!-- wp:.*?-->/s', '', $content);
        $content = preg_replace('/<!-- \/wp:.*?-->/s', '', $content);
        
        return $content;
    }

    /**
     * Final content cleanup
     */
    private function final_cleanup(string $content): string {
        // Strip remaining HTML tags
        $content = strip_tags($content);
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove special Unicode characters
        $content = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}\x{202A}-\x{202E}\x{2060}]/u', ' ', $content);
        
        // Clean up line breaks
        $content = preg_replace('/\n\s*\n/', "\n", $content);
        
        // Trim and return
        return trim($content);
    }

    /**
     * Add a custom cleaning pattern
     */
    public function add_cleaning_pattern(string $pattern): void {
        $this->builder_patterns[] = $pattern;
    }
}