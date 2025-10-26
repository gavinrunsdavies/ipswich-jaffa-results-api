<?php

function getDailyCache($key, $callback, $date = null, $cache_dir = __DIR__ . '/cache/') {
    // Ensure cache directory exists
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    if ($date === null) {
        $date = date('Y-m-d'); // default to today
    }

    $twoWeeksAgo = date('Y-m-d', strtotime('-14 day'));

    $filename = $cache_dir . sanitize_file_name($key . '-' . $date) . '.cache';
    $old_filename = $cache_dir . sanitize_file_name($key . '-' . $twoWeeksAgo) . '.cache';

    // Delete older cache
    if (file_exists($old_filename)) {
        unlink($old_filename);
    }

    // Return cached data if available
    if (file_exists($filename)) {
        return unserialize(file_get_contents($filename));
    }

    // Generate and cache new data
    $data = $callback($date);
    file_put_contents($filename, serialize($data));

    return $data;
}
