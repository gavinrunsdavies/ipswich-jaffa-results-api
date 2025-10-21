<?php

function getDailyCache($key, $callback, $cache_dir = __DIR__ . '/cache/') {
    // Ensure cache directory exists
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $filename = $cache_dir . sanitize_file_name($key . '-' . $today) . '.cache';
    $old_filename = $cache_dir . sanitize_file_name($key . '-' . $yesterday) . '.cache';

    // Delete yesterday's cache
    if (file_exists($old_filename)) {
        unlink($old_filename);
    }

    // Return cached data if available
    if (file_exists($filename)) {
        return unserialize(file_get_contents($filename));
    }

    // Generate and cache new data
    $data = $callback();
    file_put_contents($filename, serialize($data));

    return $data;
}
