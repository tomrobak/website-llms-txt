=== WP LLMs.txt - Make Your WordPress Site AI-Friendly ===
Contributors: tomrobak
Tags: ai, llms, seo, sitemap, artificial-intelligence, chatgpt, claude, llm, machine-learning
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.3
Stable tag: 2.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate llms.txt files for AI systems like ChatGPT, Claude & Perplexity. A supercharged fork with security fixes, performance boosts & WooCommerce support.

== Changelog ==

= 2.0.2 =
* Fixed: Posts not being processed from cache table
* Fixed: Edge case when max_posts setting is 0
* Fixed: Added post_status check to SQL queries
* Added: Comprehensive debugging throughout generation process
* Added: Cache table validation and logging

= 2.0.1 =
* Fixed: Fatal error in update checker when WordPress passes false instead of object
* Fixed: Empty llms.txt file generation due to undefined variable
* Fixed: Content extraction with fallback to post_content
* Added: Better debugging for content generation issues
* Added: Post titles to detailed content section

= 2.0.0 =
* Major: Complete PHP 8.3+ modernization
* Added: GitHub-powered automatic updates
* Added: Dismissible admin notifications
* Fixed: Multiple compatibility issues

= 1.0.0 =
* Initial release with security fixes and performance improvements
