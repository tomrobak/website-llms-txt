=== WP LLMs.txt - Make Your WordPress Site AI-Friendly ===
Contributors: tomrobak
Tags: ai, llms, seo, sitemap, artificial-intelligence, chatgpt, claude, llm, machine-learning
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.3
Stable tag: 2.1.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate llms.txt files for AI systems like ChatGPT, Claude & Perplexity. A supercharged fork with security fixes, performance boosts & WooCommerce support.

== Changelog ==

= 2.1.8 =
* Fixed: Missing null check after get_post() that could cause fatal error
* Improved: Better error handling throughout the codebase
* Security: Additional validation and sanitization checks

= 2.1.7 =
* Fixed: 500 error on logs REST endpoint when parameters are missing
* Fixed: Better error handling in logs endpoint with try-catch
* Added: Check if logs table exists before querying
* Improved: More robust parameter handling with null coalescing

= 2.1.6 =
* Fixed: Critical wpdb::prepare() usage error with dynamic placeholders in cache warming
* Fixed: PHP Notice about incorrect wpdb::prepare() usage without placeholders in admin page
* Fixed: Removed unnecessary wpdb::prepare() call for queries without parameters
* Improved: Safer database query construction for post type filtering

= 2.1.5 =
* Added: Memory usage monitoring and automatic optimization during generation
* Added: Cache warming feature to update stale cache entries
* Added: Better batch processing for large sites with memory constraints
* Improved: Performance optimization with smaller batch sizes when memory is limited
* Improved: Cache statistics display showing stale entries
* Improved: Progress logging with memory usage and execution time
* Fixed: Memory leaks during large content generation

= 2.1.4 =
* Fixed: Active tab preservation when saving settings or generating files
* Added: Custom fields support in content generation
* Added: Private taxonomies exclusion option now functional
* Improved: All frontend options are now fully implemented
* Improved: Better UX with tab state management using localStorage
* Fixed: Update frequency scheduling works correctly

= 2.1.3 =
* Fixed: Progress tracking now works properly with REST API
* Fixed: Generation process starts immediately via REST endpoint
* Added: Logs tab in admin interface for viewing generation logs
* Added: Initial progress entry creation to prevent 404 errors
* Added: Auto-start generation when progress page is loaded
* Improved: Better error handling for generation process

= 2.1.2 =
* Fixed: REST API architecture - centralized registration to prevent timing issues
* Fixed: Logger initialization - uses singleton pattern to prevent duplicate instances
* Added: Dedicated REST API handler class for better organization
* Added: Test endpoint at /wp-json/wp-llms-txt/v1/test for debugging
* Improved: Logger null checks throughout codebase
* Improved: Cache population error handling

= 2.1.1 =
* Fixed: REST API 404 errors by adjusting initialization priority
* Fixed: Empty cache causing "No posts found" error
* Fixed: Automatic cache population when cache is empty
* Added: Better error logging and cache status information
* Improved: More robust cache management

= 2.1.0 =
* Fixed: Critical table creation timing issue - moved to activation hook
* Fixed: File writing logic - proper overwrite on first write
* Added: Cache population mechanism for existing posts
* Added: Manual cache population button in admin
* Added: Cache statistics display
* Improved: Error handling and memory management

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
