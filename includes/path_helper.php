<?php

/**
 * Get the base URL of the application
 * @return string The base URL
 */
function get_base_url()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

    // Remove everything after /ampyang-app from the path
    $base_path = explode('/ampyang-app', $script_name)[0] . '/ampyang-app';

    return "$protocol://$host$base_path";
}

/**
 * Get the relative path from current file to application root
 * @return string The relative path to root (e.g., "../../" when in modules/user/)
 */
function get_relative_path_to_root()
{
    $current_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
    $root_path = str_replace('\\', '/', realpath(__DIR__ . '/..'));

    // Count directory levels from current path to root
    $relative_path = str_replace($root_path, '', $current_path);
    $levels = substr_count($relative_path, '/');

    return str_repeat('../', $levels);
}

/**
 * Convert a relative path to an absolute URL
 * @param string $path The relative path (e.g., "modules/user/index.php")
 * @return string The absolute URL
 */
function to_url($path)
{
    return rtrim(get_base_url(), '/') . '/' . ltrim($path, '/');
}
