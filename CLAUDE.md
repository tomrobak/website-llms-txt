# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**WP LLMs.txt** - A WordPress plugin that automatically generates LLMS.txt files for AI/LLM content consumption. This is a fork of the "Website LLMs.txt" plugin, now maintained by Tom Robak.

The plugin helps websites become discoverable by AI systems like ChatGPT, Claude, and Perplexity by creating structured lists of important public URLs optimized for how AI agents read and learn from the web.

## Architecture

### Core Components

1. **Main Plugin Entry**: `website-llms-txt.php` - Bootstraps the plugin and loads dependencies
2. **Core Class**: `includes/class-llms-core.php` - Main plugin orchestrator implementing singleton pattern
3. **Generator**: `includes/class-llms-generator.php` - Handles content generation and file creation
4. **Content Cleaner**: `includes/class-llms-content-cleaner.php` - HTML/shortcode processing for clean output
5. **Cache Manager**: `includes/class-llms-cache-manager.php` - Database caching with custom table
6. **SEO Integrations**: `includes/class-llms-provider.php`, `includes/rank-math.php`, `includes/yoast.php`
7. **Admin Interface**: `admin/admin-page.php` - Settings page with jQuery UI sortable for post type ordering

### Key Design Patterns

- **Singleton Pattern**: Used for integration classes to ensure single instances
- **Hook-based Architecture**: Extensive use of WordPress actions/filters with `llms_*` prefix for extensibility
- **Provider Pattern**: SEO plugin integrations implement common interface
- **Cache-first Approach**: Database caching (`{prefix}llms_txt_cache` table) with on-demand file generation

## Development Commands

Since this is a traditional WordPress plugin without build tools:

```bash
# No build process required - plugin works directly
# To develop, copy plugin to WordPress installation:
cp -r . /path/to/wordpress/wp-content/plugins/wp-llms-txt/

# Activate plugin via WP-CLI (if available):
wp plugin activate wp-llms-txt

# Clear cache after changes:
wp cache flush
```

## Critical Implementation Details

### Security Requirements
- All files must start with `if (!defined('ABSPATH')) { exit; }`
- Use `wp_verify_nonce()` for all admin actions
- Sanitize inputs: `sanitize_text_field()`, `sanitize_key()`, `absint()`
- Escape outputs: `esc_html()`, `esc_url()`, `esc_attr()`
- Check capabilities: `current_user_can('manage_options')`

### Database Operations
- Always use `$wpdb->prepare()` for queries
- Custom table: `{prefix}llms_txt_cache` created via `dbDelta()`
- Options stored with `update_option()` / `get_option()`
- Transients for temporary caching

### Content Processing Flow
1. Admin selects post types and ordering
2. Generator queries posts respecting SEO plugin noindex/nofollow settings
3. Content cleaner processes HTML, removes shortcodes, special characters
4. Cache manager stores in database
5. Files generated on-demand at `/llms.txt` and `/llms-sitemap.xml` endpoints

### WordPress Hooks Used
- `init`: Register custom endpoints and post types
- `admin_menu`: Add settings page
- `save_post`: Trigger cache updates
- `delete_post`: Clean cache entries
- `template_redirect`: Serve LLMS files
- `wpseo_sitemaps_index`: Yoast sitemap integration
- `rank_math/sitemap/index`: RankMath integration

### Multisite Considerations
- Files served via trailing slash URLs: `example.com/llms.txt/`
- Per-site cache storage and cron jobs
- Network activation supported

## Common Tasks

### Adding New SEO Plugin Integration
1. Create new provider class in `includes/` implementing provider interface
2. Add detection logic in `class-llms-core.php`
3. Implement sitemap integration hooks
4. Add cache clearing hooks for the plugin

### Modifying Content Processing
1. Edit `class-llms-content-cleaner.php` for HTML/shortcode handling
2. Update `class-llms-generator.php` for content selection logic
3. Clear cache after changes via admin interface

### Debugging Issues
1. Check WordPress debug log for errors
2. Verify database table exists: `{prefix}llms_txt_cache`
3. Check rewrite rules are flushed
4. Verify file permissions in uploads directory
5. Test with different SEO plugins active/inactive

## Performance Limits
- Maximum 500 items per LLMS.txt file
- Content limited to 250 words per post (configurable)
- Memory-efficient chunked processing
- Database queries optimized with direct SQL