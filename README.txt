=== WP LLMs.txt - Make Your WordPress Site AI-Friendly ===
Contributors: tomrobak
Tags: ai, llms, seo, sitemap, artificial-intelligence, chatgpt, claude, llm, machine-learning
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.3
Stable tag: 2.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate llms.txt files for AI systems like ChatGPT, Claude & Perplexity. A supercharged fork with security fixes, performance boosts & WooCommerce support.

== Changelog ==

= 2.5.1 =
* Fixed: Fatal error on plugin activation - debug-fix.php missing from repository
* Fixed: PHP warning for undefined $upload_path variable in generator
* Maintenance: Added debug-fix.php to repository (required for admin functionality)

= 2.5.0 =
* UI/UX: Complete redesign following shadcn/ui neutral color palette
* UI/UX: Replaced all colorful elements with professional zinc/gray tones
* Fixed: Multiple fatal PHP syntax errors (missing semicolons and braces)
* Fixed: Generator class not initializing - causing empty cache (0 posts)
* Fixed: Race conditions between REST API and cron with new database locking
* Fixed: Settings not being respected (post_types, max_posts, max_words)
* Fixed: Standard llms.txt now includes ALL posts/pages as per specification
* Improved: Changed default max_words from 1000 to 250 (recommended for AI)
* Improved: Better memory management during large site generation
* Improved: Enhanced error handling and logging throughout
* Added: LLMS_Generation_Lock class to prevent concurrent access issues
* Changed: Plugin menu location from Settings to Tools → Llms.txt
* Note: Plugin is distributed via GitHub only with built-in auto-updates

= 2.4.0 =
* NEW: Dual-file system - generates both standard llms.txt and comprehensive llms-full.txt
* NEW: Standard llms.txt follows llmstxt.org specification for AI crawlers
* NEW: Comprehensive llms-full.txt contains full content for advanced AI training
* Added: Support for accessing both files via /llms.txt and /llms-full.txt
* Added: Admin UI now shows status for both file types
* Added: View buttons for both standard and full versions
* Improved: File generation now creates both formats in one operation
* Improved: Cache clearing and regeneration handles both files
* Updated: File status display to show information for both files

= 2.3.0 =
* CRITICAL: Moved llms.txt file location from wp-content/uploads to website root (proper location)
* Fixed: File is now served from /llms.txt instead of /wp-content/uploads/llms.txt
* Added: Automatic migration to remove old file locations
* Added: Root directory writability check with error handling
* Improved: File permissions handling for root directory access
* Note: You may need to ensure your web root is writable for the plugin to create llms.txt

= 2.2.3 =
* Fixed: Progress tracking now shows correct global count across all post types
* Improved: Increased default max_words from 250 to 1000 for better AI training
* Improved: Increased default max_posts from 100 to 500 to include more content
* Fixed: Content truncation now shows clearer message and processes more content
* Added: Better debugging info showing both per-type and global progress counts

= 2.2.2 =
* CRITICAL FIX: Resolved visibility filtering that was hiding most content from generation
* Fixed: SEO plugin integration now correctly handles noindex settings
* Fixed: File naming inconsistency - consistent llms.txt filename throughout
* Fixed: Content serving now properly outputs unescaped text content
* Added: Comprehensive debugging for cache visibility statistics
* Added: Automatic cache refresh on version updates
* Improved: Less aggressive SEO visibility filtering (only excludes truly noindexed content)
* Improved: Better error logging and debugging information

= 2.2.1 =
* Fixed: Critical content generation bug that limited output to 1 post per type
* Fixed: Post type counter now resets properly for each content type
* Fixed: 400 error on REST API generate/start endpoint
* Fixed: Overview and detailed content generation now process all selected posts
* Improved: Better progress tracking with per-type counters
* Improved: More robust REST API error handling

= 2.2.0 =
* BREAKING: Removed all hardcoded custom post types from defaults
* Fixed: Plugin now properly uses saved settings without hardcoded fallbacks
* Fixed: Default post types now only include 'page' and 'post'
* Fixed: Removed hardcoded 'wedding_lounge' and 'documentation' post types
* Improved: Settings now properly merge with defaults using wp_parse_args
* Improved: All custom post types must be explicitly selected by user

= 2.1.9 =
* Fixed: Fatal error in logs endpoint - number_format() type mismatch
* Fixed: Better type casting for numeric values in logs
* Added: More debug information for cache population
* Added: Display post type machine names in settings for clarity
* Improved: Debug logging for custom post types cache issues

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
