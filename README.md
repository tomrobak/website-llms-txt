# WP LLMs.txt Plugin

**Version: 2.1.8** | **Last Updated: 2025-06-30**

A WordPress plugin that generates LLMS.txt files for AI/LLM content consumption with SEO plugin integration. Originally created by Website LLM, forked and maintained by Tom Robak.

## 🚀 Features

### Core Functionality
- **Automated LLMS.txt generation** from WordPress content
- **SEO plugin integration** with Yoast SEO and RankMath sitemaps
- **Flexible content selection** - Choose post types, taxonomies, and custom fields
- **Content optimization** - Word limits, excerpt inclusion, meta data control
- **Real-time updates** - Immediate or scheduled regeneration options
- **WooCommerce support** - Include product data in AI-friendly format

### Modern Admin Interface
- **Tab-based navigation** - Organized settings across Content, Management, Import/Export, and Debug sections
- **Responsive design** - Works seamlessly on all screen sizes
- **Dark mode support** - Automatic system preference detection
- **Accessibility focused** - WCAG AA compliant with proper focus management

### Content Management
- **Smart content filtering** - Include/exclude specific post types and taxonomies
- **Custom field support** - Include publicly visible custom field data
- **Content limits** - Control maximum posts per type and words per post
- **Taxonomy handling** - Include categories, tags, and custom taxonomies
- **Meta information** - Optional publish dates, authors, and other metadata

## 📋 Requirements

- **WordPress**: 6.7 or higher
- **PHP**: 8.3 or higher
- **Memory**: 128MB minimum (256MB recommended)
- **Permissions**: Write access to wp-content/uploads directory

## 🛠️ Installation

1. Download the plugin zip file
2. Upload via WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Configure settings in **Tools → llms.txt**
5. Click "Clear Caches" to generate your first LLMS.txt file

## ⚙️ Configuration

### Content Settings
- **Post Types**: Select which content types to include (Pages, Posts, Custom Post Types)
- **Content Options**: Choose meta information, excerpts, and taxonomies
- **Advanced Options**: Custom fields and private taxonomy handling
- **Limits**: Set maximum posts per type and words per post
- **Update Frequency**: Choose immediate, daily, or weekly regeneration

### SEO Integration
- **Yoast SEO**: Automatic sitemap integration with `/llms-sitemap.xml`
- **RankMath**: Native sitemap support for LLMS.txt discovery
- **Cache Management**: Clear SEO plugin caches automatically

### Import/Export
- **Settings Backup**: Export current configuration as JSON
- **Site Migration**: Import settings from other installations
- **Version Control**: Track configuration changes over time

## 📁 File Structure

```
/admin/                 # Admin interface files
  ├── modern-admin-page.php      # Main settings page
  ├── modern-admin-styles.css    # shadcn-inspired CSS
  └── admin-script.js            # JavaScript functionality

/includes/              # Core PHP classes
  ├── class-llms-core.php        # Main plugin class
  ├── class-llms-generator.php   # Content generation
  ├── class-llms-cache-manager.php # Cache handling
  └── class-llms-content-cleaner.php # Content processing

/docs/                  # Documentation
  ├── ui/shadcn-components.md    # UI component documentation
  └── fixes-log.md               # Change history
```

## 🌐 Community

### WPLove.co
Join our passionate community of WordPress users, photographers, and creatives:
- Share knowledge and get inspired
- Real-world WordPress wisdom
- Niche community focused on practical solutions
- **Visit**: [WPLove.co](https://wplove.co) 📸

### Development
- **GitHub**: [tomrobak/website-llms-txt](https://github.com/tomrobak/website-llms-txt)
- **Issues**: Report bugs and feature requests
- **Contributions**: Pull requests welcome

## 🔧 Technical Details

### Generated Files
- **Primary**: `/wp-content/uploads/llms.txt` - Main AI training file
- **Sitemap**: `/llms-sitemap.xml` - SEO integration endpoint
- **Cache**: Database-stored content cache for performance

### Integration Points
- **WordPress Hooks**: Integrated with post save, update, and delete actions
- **SEO Plugins**: Native sitemap providers for major SEO plugins
- **WooCommerce**: Product data inclusion with proper formatting
- **Custom Post Types**: Automatic detection and inclusion options

### Performance Features
- **Database Caching**: Intelligent content caching system
- **Batch Processing**: Memory-efficient content generation
- **Background Updates**: Scheduled regeneration via WordPress cron
- **Error Handling**: Comprehensive logging and recovery systems

## 📊 File Output Format

The generated LLMS.txt follows AI training standards:
- **Structured Headers**: Clear content organization
- **Metadata**: Post types, dates, authors, categories
- **Clean Content**: HTML stripped, shortcodes processed
- **URL References**: Direct links to original content
- **Taxonomy Information**: Categories, tags, custom taxonomies

## 🐛 Troubleshooting

### Common Issues
- **File Not Generated**: Check wp-content/uploads directory permissions
- **Empty Content**: Verify post type selections in settings
- **SEO Integration**: Clear SEO plugin caches after configuration
- **Memory Issues**: Increase PHP memory limit or reduce content limits

### Debug Information
Access debug panel (WP_DEBUG mode) for:
- System information
- Plugin version details
- File permissions status
- PHP configuration
- WordPress compatibility

## 📄 License

GPL v2 or later - WordPress Plugin License

---

**Made with ❤️ for the WordPress community**

*Contributing to the AI-friendly web, one site at a time.*