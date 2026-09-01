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
    wp_enqueue_style('darkify-util-style', plugin_dir_url(__FILE__) . 'assets/css/darkify.css');
    wp_enqueue_script('darkify-util-script', plugin_dir_url(__FILE__) . 'assets/js/custom-script.js', array(), DARKIFY_UTIL_VERSION, true);
}

add_action('wp_enqueue_scripts', 'darkify_enqueue_scripts');

/**
 * The preview shortcodes. Both render a sample site into an isolated frame and
 * run the real Darkify engine inside it — [darkify_demo] for the visitor to
 * play with, [darkify_hero_demo] flipping itself on a loop — front end, then
 * wp-admin.
 */
require_once DARKIFY_UTIL_PATH . 'includes/class-darkify-util-preview.php';
require_once DARKIFY_UTIL_PATH . 'includes/class-darkify-util-demo.php';
require_once DARKIFY_UTIL_PATH . 'includes/class-darkify-util-hero.php';

add_action('plugins_loaded', array('Darkify_Util_Demo', 'instance'));
add_action('plugins_loaded', array('Darkify_Util_Hero', 'instance'));

/**
 * The changelog block and its [darkify_changelog] shortcode, plus the one-time
 * migration off the third-party block the Changelogs page used to depend on.
 *
 * The parser is shared: the block's editor parses in JavaScript, the migration
 * parses in PHP, and both read the same category table from
 * includes/changelog-categories.json.
 */
require_once DARKIFY_UTIL_PATH . 'includes/class-darkify-util-changelog-parser.php';
require_once DARKIFY_UTIL_PATH . 'includes/class-darkify-util-changelog.php';
require_once DARKIFY_UTIL_PATH . 'includes/class-darkify-util-changelog-migrator.php';

add_action('plugins_loaded', array('Darkify_Util_Changelog', 'instance'));
add_action('plugins_loaded', array('Darkify_Util_Changelog_Migrator', 'instance'));

/**
 * The collection block and its [darkify_collection] shortcode: a filterable,
 * searchable grid of items written into the block itself — roundups, showcases,
 * directories — rather than queried out of a post type.
 *
 * Filtering, search and paging are all answered in PHP, over AJAX, from the
 * items stored in the block comment; every control degrades to the plain link or
 * form it is rendered as.
 */
require_once DARKIFY_UTIL_PATH . 'includes/class-darkify-util-collection.php';

add_action('plugins_loaded', array('Darkify_Util_Collection', 'instance'));
