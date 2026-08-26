<?php
/*
*   Plugin Name: Darkify Util
*   Description: A utility plugin to add dark mode functionality to your WordPress site.
*   Version: 1.1
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

define('DARKIFY_UTIL_FILE', __FILE__);
define('DARKIFY_UTIL_PATH', plugin_dir_path(__FILE__));
define('DARKIFY_UTIL_URL', plugin_dir_url(__FILE__));
define('DARKIFY_UTIL_VERSION', '1.1');

// enqueue the plugin's CSS and JavaScript files
function darkify_enqueue_scripts()
{
    wp_enqueue_style('darkify-util-style', plugin_dir_url(__FILE__) . 'css/darkify.css');
}

add_action('wp_enqueue_scripts', 'darkify_enqueue_scripts');

/**
 * The [darkify_demo] shortcode — a live, interactive Darkify demo that runs the
 * real plugin engine inside an isolated preview frame.
 */
require_once DARKIFY_UTIL_PATH . 'includes/class-darkify-util-demo.php';

add_action('plugins_loaded', array('Darkify_Util_Demo', 'instance'));
