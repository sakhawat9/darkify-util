<?php

/**
 * Server-side render for darkify-util/social-share.
 *
 * Copied into build/ by wp-scripts and named by block.json's `render` field.
 * Deliberately thin: the markup lives in templates/social-share.php with the
 * rest of this plugin's markup, and everything else is the block class's job.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Social_Share')) {
    return;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every value it prints.
echo Darkify_Util_Social_Share::instance()->render_block($attributes, $content, $block);
