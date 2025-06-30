# WP LLMs.txt

[![GitHub release](https://img.shields.io/github/v/release/tomrobak/website-llms-txt)](https://github.com/tomrobak/website-llms-txt/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.3-8892BF.svg)](https://php.net)
[![WordPress Version](https://img.shields.io/badge/WordPress-%3E%3D6.7-blue.svg)](https://wordpress.org)

Modern WordPress plugin that automatically generates and maintains llms.txt files for AI/LLM consumption, following the llmstxt.org specification. Seamlessly integrates with popular SEO plugins (Yoast SEO, RankMath) to respect content visibility settings.

## Features

### 🚀 Core Features
- **Automatic llms.txt Generation**: Creates both standard and comprehensive versions
- **Real-time Updates**: Content updates trigger automatic regeneration
- **SEO Plugin Integration**: Respects noindex settings from Yoast SEO and RankMath
- **Smart Caching**: Database-level caching for optimal performance
- **Batch Processing**: Handles large sites with thousands of posts efficiently

### 📄 File Formats
- **`/llms.txt`**: Standard format with all posts and pages listed with descriptions
- **`/llms-full.txt`**: Comprehensive format including full content, metadata, and custom fields

### 🎛️ Configuration Options
- **Content Types**: Select which post types to include
- **Word Limits**: Control content length in llms-full.txt
- **Post Limits**: Set maximum posts per type
- **Update Frequency**: Immediate, daily, or weekly updates
- **Custom Fields**: Option to include/exclude custom field data

### 🔧 Advanced Features
- **Memory Management**: Smart batch processing with memory monitoring
- **Progress Tracking**: Real-time generation progress with REST API
- **Cache Management**: Integrates with popular caching plugins
- **Content Cleaning**: Removes shortcodes, page builder markup, and formatting
- **Multi-language Support**: i18n ready with text domain support

## Requirements

- WordPress 6.7 or higher
- PHP 8.3 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Write permissions on WordPress root directory

## Installation

### From GitHub (Recommended)
1. Download the latest release from [GitHub Releases](https://github.com/tomrobak/website-llms-txt/releases)
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Select the downloaded zip file and click Install Now
4. Activate the plugin

### Auto-Updates
The plugin includes built-in auto-update functionality that checks GitHub for new releases:
- Updates appear in your WordPress admin just like plugins from WordPress.org
- Your settings and generated files are preserved during updates
- Use the "Check for Updates" link in the plugins list to manually check

### Manual Installation
1. Clone or download this repository
2. Upload the `website-llms-txt` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin

## Configuration

### Basic Setup
1. Navigate to **Tools → Llms.txt**
2. Select post types to include
3. Configure content options
4. Click "Generate LLMS.txt File"

### Content Settings
- **Post Types**: Choose which content types to include (posts, pages, products, etc.)
- **Maximum Posts**: Limit posts per type (default: 500)
- **Maximum Words**: Word limit for full content (default: 250)
- **Include Options**: Meta data, excerpts, taxonomies, custom fields

### SEO Integration
The plugin automatically detects and respects:
- Yoast SEO noindex settings
- RankMath robots meta
- Custom SEO plugin settings via filters

## File Structure

### Standard llms.txt
```
# Site Name

> Site description

This is a WordPress-powered website focused on [automatically detected site focus].

## Pages

- [Page Title](URL): Brief description...

## Posts  

- [Post Title](URL): Brief description...

## Topics

- **Category Name** (post count)

## Tags

Tag1 (5), Tag2 (3), ...

## Metadata

- Total pages: X
- Total posts: Y
- Last updated: timestamp
```

### Full llms-full.txt
Includes everything from standard plus:
- Full post content (respecting word limits)
- Custom fields
- Meta descriptions
- Publication dates
- Author information
- Taxonomies

## Developer Guide

### Hooks & Filters

#### Actions
```php
// Before generation starts
do_action('llms_txt_before_generate', $settings);

// After generation completes
do_action('llms_txt_after_generate', $file_path, $settings);

// Clear caches
do_action('llms_clear_seo_caches');
```

#### Filters
```php
// Modify post types
add_filter('llms_txt_post_types', function($post_types) {
    $post_types[] = 'custom_type';
    return $post_types;
});

// Modify content before output
add_filter('llms_txt_content', function($content, $post_id, $post_type) {
    // Custom content modifications
    return $content;
}, 10, 3);

// Set max posts per type
add_filter('llms_txt_max_posts_per_type', function($max, $post_type) {
    return $post_type === 'product' ? 1000 : $max;
}, 10, 2);
```

### REST API Endpoints

```
GET  /wp-json/llms/v1/progress/{id}
POST /wp-json/llms/v1/generate/start
POST /wp-json/llms/v1/progress/{id}/cancel
GET  /wp-json/llms/v1/logs
```

### Database Tables

#### wp_llms_txt_cache
Stores processed content for quick generation
- `post_id`: Post ID (primary key)
- `content`: Cleaned content
- `meta`: Meta description
- `is_visible`: SEO visibility flag

#### wp_llms_txt_progress
Tracks generation progress
- `id`: Progress ID
- `status`: pending|running|completed|error
- `current_item`: Current processing position
- `total_items`: Total items to process

#### wp_llms_txt_logs
System logs for debugging
- `timestamp`: Log time
- `level`: INFO|WARNING|ERROR
- `message`: Log message
- `context`: Additional data

## Troubleshooting

### Common Issues

#### Files not generating
1. Check file permissions on WordPress root
2. Ensure cron is running: `wp cron test`
3. Check PHP error logs
4. Run manual generation from admin panel

#### Empty cache
1. Verify post types are published
2. Check SEO plugin settings
3. Clear all caches and regenerate
4. Check database tables exist

#### Memory errors
1. Increase PHP memory limit
2. Reduce batch size in settings
3. Lower max posts per type
4. Use WP-CLI for large sites

### Debug Mode
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Performance

### Optimization Tips
- Set reasonable post limits (100-500 per type)
- Use daily/weekly updates for large sites
- Enable object caching (Redis/Memcached)
- Monitor memory usage in logs

### Benchmarks
- 1,000 posts: ~5 seconds
- 10,000 posts: ~45 seconds
- 50,000 posts: ~3-5 minutes

## Security

- All database queries use prepared statements
- File permissions checked before writing
- Nonce verification on all admin actions
- Capability checks for admin functions
- Content sanitization and escaping

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup
1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `composer test`
4. Check code standards: `composer phpcs`

## Support

- **Documentation**: [GitHub Wiki](https://github.com/tomrobak/website-llms-txt/wiki)
- **Issues**: [GitHub Issues](https://github.com/tomrobak/website-llms-txt/issues)
- **Releases**: [GitHub Releases](https://github.com/tomrobak/website-llms-txt/releases)

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Originally created by Website LLM, forked and enhanced by [Tom Robak](https://wplove.co).

### Contributors
- Tom Robak - Lead Developer
- Original Website LLM team
- [All Contributors](https://github.com/tomrobak/website-llms-txt/graphs/contributors)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.