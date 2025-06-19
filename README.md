# 🤖 WP LLMs.txt - Make Your WordPress Site AI-Friendly (Finally!)

[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![GitHub Release](https://img.shields.io/github/v/release/tomrobak/wp-llms-txt)](https://github.com/tomrobak/website-llms-txt/releases)

*Because even AI needs a roadmap to find the good stuff on your website* 🗺️

## What's This About? 🤔

Remember when you had to create sitemaps for Google? Well, now ChatGPT, Claude, and their AI buddies want their own special menu too. This plugin creates an `llms.txt` file that tells AI systems exactly what content on your site is worth reading (and what's just... not).

Think of it as a "VIP list" for your website content, but for robots. The cool robots that can actually understand what you're saying.

## 🚀 This Fork is Different (And Better!)

This is a **supercharged fork** of the original "Website LLMs.txt" plugin, lovingly maintained by the original genius [Website LLMs.txt team](https://github.com/RRyanHoward/website-llms-txt). I took their brilliant idea and... well, let's just say I gave it some serious upgrades:

### ✨ What I Added (The Good Stuff):
- **🛡️ Security Fort Knox**: Fixed security vulnerabilities that would make your hosting provider cry
- **⚡ Speed Demon**: Performance optimizations that make it 75% faster (your server will thank you)
- **🎨 Pretty Interface**: Real-time progress bars because waiting is so 2023
- **🛠️ Developer Paradise**: Filters and hooks for developers who like to tinker
- **📦 Import/Export Magic**: Move settings between sites like a WordPress wizard
- **🛒 WooCommerce Love**: Full e-commerce support including sale prices and stock levels
- **🔍 Error Detective**: Comprehensive error logging that actually tells you what went wrong
- **🧠 Smart Validation**: Forms that prevent you from breaking things (you're welcome)

### 💝 Credits Where Credits Are Due
Massive props to the original [Website LLMs.txt team](https://github.com/RRyanHoward/website-llms-txt) for creating this brilliant concept. I'm just the guy who couldn't leave well enough alone and decided to add "a few small improvements" (narrator: it was not a few small improvements).

## Why Your Website Needs This 🎯

### For Your Website:
- **AI Discovery**: Your content gets found by AI systems automatically
- **Better AI Responses**: When someone asks AI about your topic, your site gets mentioned
- **SEO 2.0**: Like regular SEO, but for the AI-powered future
- **Professional Credibility**: Shows you're forward-thinking (and slightly obsessed with technology)

### For AI Systems:
- **Clean Content**: They get your best stuff without the navigation menus and cookie notices
- **Structured Data**: Everything organized the way AI brains like it
- **Respectful Crawling**: Only the content you want to share gets shared
- **Context-Rich**: Full product data, taxonomies, and metadata included

## Installation (The Easy Way) 📥

1. **Download** the latest release from my [releases page](https://github.com/tomrobak/website-llms-txt/releases)
2. **Upload** to your WordPress site (Plugins → Add New → Upload Plugin)
3. **Activate** the plugin
4. **Go to** Tools → Llms.txt in your WordPress admin
5. **Configure** your settings (or don't, the defaults are pretty smart)
6. **Watch** the magic happen ✨

## Configuration (The Fun Part) ⚙️

The plugin works great out of the box, but if you like tweaking things:

- **Select Post Types**: Choose what content types to include
- **Set Limits**: Decide how many posts per type (we suggest not going crazy)
- **Customize Fields**: Include custom fields and taxonomies
- **WooCommerce Settings**: Product data, prices, stock status - it's all there
- **Update Frequency**: Real-time or scheduled updates

## For Developers (The Technical Folks) 👩‍💻

### Available Filters:
```php
// Customize which post types to include
add_filter('llms_txt_post_types', function($post_types) {
    return array_merge($post_types, ['custom_type']);
});

// Modify content before output
add_filter('llms_txt_content', function($content, $post_id, $post_type) {
    return $content . "\n\n[Custom footer text]";
}, 10, 3);

// Override post inclusion logic
add_filter('llms_txt_include_post', function($include, $post_id, $post) {
    return $post->post_status === 'publish' && !get_post_meta($post_id, 'exclude_from_ai', true);
}, 10, 3);
```

### Available Actions:
```php
// Hook before generation starts
add_action('llms_txt_before_generate', function($settings) {
    error_log('LLMS generation starting with settings: ' . print_r($settings, true));
});

// Hook after generation completes
add_action('llms_txt_after_generate', function($file_path, $settings) {
    // Maybe notify external services, update cache, etc.
});
```

## Changelog 📝

### v1.0.0 - The "Finally Fixed Everything" Release
- 🛡️ **Security**: Fixed all the scary security vulnerabilities
- ⚡ **Performance**: 75% faster queries with database indexing
- 🎨 **UX**: Real-time progress indicators and better error messages
- 🛠️ **Extensibility**: Full filter and action system for developers
- 📦 **Portability**: Import/export settings between sites
- 🛒 **E-commerce**: Extended WooCommerce support with pricing and inventory
- 🔍 **Debugging**: Comprehensive error logging and validation
- 🧹 **Cleanup**: Removed all the technical debt (there was a lot)

## Support & Contributing 🤝

- **Issues**: Found a bug? [Create an issue](https://github.com/tomrobak/website-llms-txt/issues)
- **Feature Requests**: Got ideas? I love ideas!
- **Pull Requests**: Code contributions welcome (tests required, sanity optional)
- **Community**: Join [WPLove.co](https://wplove.co) - a passionate community of WordPress users, especially photographers and creatives. It's a niche community packed with knowledge and real-world WordPress wisdom!

## License 📄

GPL v2 or later - Because sharing is caring, and WordPress said so.

## Fun Fact 🎉

This plugin has been battle-tested on sites with over 10,000 posts. Your AI overlords will be pleased with the efficiency.

---

*Made with ❤️ (and lots of coffee) by Tom Robak, who believes the future is AI-powered, but the present is still delightfully human.*

**Want to connect?** Find me and other passionate WordPress users at [WPLove.co](https://wplove.co) - where photographers, creatives, and WordPress enthusiasts share knowledge and build amazing things together! 📸✨