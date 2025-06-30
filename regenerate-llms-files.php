<?php
/**
 * Quick regeneration of llms.txt files with correct format
 */

// Load WordPress
$wp_load_paths = [
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

global $wpdb;

echo "=== Regenerating llms.txt files with correct format ===\n\n";

// Load generator class
require_once dirname(__FILE__) . '/includes/class-llms-generator.php';
require_once dirname(__FILE__) . '/includes/class-llms-content-cleaner.php';
require_once dirname(__FILE__) . '/includes/class-llms-logger.php';

// Create progress ID
$progress_id = 'manual_regen_' . time();
set_transient('llms_current_progress_id', $progress_id, HOUR_IN_SECONDS);

// Insert progress record
$progress_table = $wpdb->prefix . 'llms_txt_progress';
$wpdb->insert(
    $progress_table,
    [
        'id' => $progress_id,
        'status' => 'running',
        'message' => 'Manual regeneration'
    ]
);

// Initialize generator
$generator = new LLMS_Generator();

echo "1. Generating standard llms.txt (with ALL content)...\n";
$start = microtime(true);

// Force regeneration
if (method_exists($generator, 'update_llms_file')) {
    $result = $generator->update_llms_file();
    
    $time = round(microtime(true) - $start, 2);
    echo "   Generation completed in {$time} seconds\n";
} else {
    echo "   ERROR: update_llms_file method not found\n";
}

// Check results
$files = [
    ABSPATH . 'llms.txt' => 'Standard llms.txt',
    ABSPATH . 'llms-full.txt' => 'Full llms-full.txt'
];

echo "\n2. Checking generated files:\n";

foreach ($files as $path => $name) {
    if (file_exists($path)) {
        $size = filesize($path);
        $lines = count(file($path));
        echo "   ✓ {$name}: {$size} bytes, {$lines} lines\n";
        
        // Show first few lines
        $content = file_get_contents($path);
        $preview = substr($content, 0, 500);
        echo "   Preview:\n";
        echo "   " . str_replace("\n", "\n   ", $preview) . "...\n\n";
    } else {
        echo "   ✗ {$name}: NOT FOUND\n";
    }
}

// Verify content structure
echo "3. Verifying standard llms.txt structure:\n";

if (file_exists(ABSPATH . 'llms.txt')) {
    $content = file_get_contents(ABSPATH . 'llms.txt');
    
    $checks = [
        '## Pages' => 'Pages section',
        '## Posts' => 'Posts section', 
        '## Topics' => 'Topics section',
        '## Metadata' => 'Metadata section'
    ];
    
    foreach ($checks as $section => $name) {
        if (strpos($content, $section) !== false) {
            // Count items in section
            preg_match("/{$section}\n\n(.*?)\n\n/s", $content, $matches);
            if (!empty($matches[1])) {
                $items = substr_count($matches[1], "\n- ");
                echo "   ✓ {$name}: found ({$items} items)\n";
            } else {
                echo "   ✓ {$name}: found (empty)\n";
            }
        } else {
            echo "   ✗ {$name}: MISSING\n";
        }
    }
}

echo "\n=== Regeneration complete ===\n";